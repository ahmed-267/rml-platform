<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadEvidenceFile;
use App\Support\EvidencePlaceholderStorage;
use App\Support\FilesystemDisk;
use App\Support\LeadEvidenceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadEvidenceController extends Controller
{
    public function view(Request $request, LeadEvidenceFile $evidence): StreamedResponse
    {
        $this->authorizeEvidence($request, $evidence);

        return $this->stream($evidence, disposition: 'inline');
    }

    public function download(Request $request, LeadEvidenceFile $evidence): StreamedResponse
    {
        $this->authorizeEvidence($request, $evidence);

        return $this->stream($evidence, disposition: 'attachment');
    }

    private function authorizeEvidence(Request $request, LeadEvidenceFile $evidence): void
    {
        $user = $request->user();
        abort_unless($user && LeadEvidenceAccess::canView($user, $evidence), 403);
    }

    private function stream(LeadEvidenceFile $evidence, string $disposition): StreamedResponse
    {
        $disk = $evidence->disk ?: FilesystemDisk::uploads();

        if (! Storage::disk($disk)->exists($evidence->path)) {
            // Demo/seed rows often lack physical files — materialize a placeholder locally.
            EvidencePlaceholderStorage::ensureOnDisk($evidence);
        }

        abort_unless(Storage::disk($disk)->exists($evidence->path), 404, __('rml.documents.evidence_missing'));

        $filename = $evidence->original_name ?: basename($evidence->path);

        return Storage::disk($disk)->response(
            $evidence->path,
            $filename,
            [
                'Content-Type' => $evidence->mime_type ?: 'application/octet-stream',
            ],
            $disposition,
        );
    }
}
