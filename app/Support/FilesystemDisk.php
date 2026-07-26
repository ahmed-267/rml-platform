<?php

namespace App\Support;

/**
 * Resolves the disk used for durable app uploads (evidence, invoices, etc.).
 * Local: FILESYSTEM_DISK=local. Laravel Cloud: injected FILESYSTEM_DISK=private (bucket).
 */
final class FilesystemDisk
{
    public static function uploads(): string
    {
        $disk = (string) config('filesystems.default', 'local');

        return $disk !== '' ? $disk : 'local';
    }
}
