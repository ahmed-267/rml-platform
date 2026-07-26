<?php

namespace App\Support;

use App\Models\LeadEvidenceFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ensures demo/seeded evidence paths have a real file on disk so view/download works.
 */
final class EvidencePlaceholderStorage
{
    public static function ensureOnDisk(LeadEvidenceFile $evidence): bool
    {
        $disk = $evidence->disk ?: FilesystemDisk::uploads();
        $path = (string) $evidence->path;

        if ($path === '') {
            return false;
        }

        if (Storage::disk($disk)->exists($path)) {
            return true;
        }

        $bytes = self::bytesForMime($evidence->mime_type);
        Storage::disk($disk)->put($path, $bytes);

        if ($evidence->size === null || (int) $evidence->size <= 0) {
            $evidence->forceFill(['size' => strlen($bytes)])->saveQuietly();
        }

        return Storage::disk($disk)->exists($path);
    }

    public static function ensurePath(string $path, ?string $disk = null, ?string $mimeType = 'image/jpeg'): void
    {
        $disk ??= FilesystemDisk::uploads();

        if ($path === '' || Storage::disk($disk)->exists($path)) {
            return;
        }

        Storage::disk($disk)->put($path, self::bytesForMime($mimeType));
    }

    private static function bytesForMime(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        if (str_starts_with($mime, 'image/')) {
            // Minimal valid JPEG (1×1).
            return base64_decode(
                '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
                true,
            ) ?: 'JPEG';
        }

        if ($mime === 'application/pdf' || str_contains($mime, 'pdf')) {
            return "%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        }

        return "RML demo evidence placeholder\n";
    }
}
