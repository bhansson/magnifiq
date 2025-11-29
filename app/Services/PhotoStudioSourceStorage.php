<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoStudioSourceStorage
{
    /**
     * Store a processed composition source image.
     *
     * Images are stored with private visibility in a team-scoped directory
     * structure to ensure proper access control.
     */
    public function store(string $binary, int $teamId, string $extension = 'jpg'): string
    {
        $disk = $this->getDisk();
        $directory = sprintf('photo-studio-sources/%d/%s', $teamId, now()->format('Y/m/d'));
        $path = $directory.'/'.Str::uuid().'.'.$extension;

        Storage::disk($disk)->put($path, $binary, ['visibility' => 'private']);

        return $path;
    }

    /**
     * Get the configured storage disk for source images.
     */
    public function getDisk(): string
    {
        return config('photo-studio.source_disk', 's3');
    }

    /**
     * Check if a source image exists at the given path.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->getDisk())->exists($path);
    }

    /**
     * Get the binary content of a stored source image.
     */
    public function get(string $path): ?string
    {
        if (! $this->exists($path)) {
            return null;
        }

        return Storage::disk($this->getDisk())->get($path);
    }
}
