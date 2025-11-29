<?php

namespace App\Http\Controllers;

use App\Models\PhotoStudioGeneration;
use App\Services\PhotoStudioSourceStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PhotoStudioSourceImageController extends Controller
{
    /**
     * Serve a composition source image.
     *
     * For product images, redirects to the external URL.
     * For uploaded images, streams the private content from storage.
     */
    public function __invoke(
        PhotoStudioGeneration $generation,
        int $index,
        PhotoStudioSourceStorage $sourceStorage
    ): Response|RedirectResponse {
        $team = Auth::user()?->currentTeam;

        // Validate team ownership
        abort_if(! $team || $generation->team_id !== $team->id, 404);

        // Validate the generation is a composition
        abort_if(! $generation->isComposition(), 404);

        // Get source references array
        $sourceRefs = $generation->source_references ?? [];
        abort_if(! isset($sourceRefs[$index]), 404);

        $sourceRef = $sourceRefs[$index];

        // Handle product images - redirect to external URL
        if ($sourceRef['type'] === 'product') {
            $url = $sourceRef['source_reference'] ?? null;
            abort_if(! $url || ! filter_var($url, FILTER_VALIDATE_URL), 404);

            return redirect($url);
        }

        // Handle uploaded images - serve from private storage
        if ($sourceRef['type'] === 'upload') {
            $path = $sourceRef['source_reference'] ?? null;
            abort_if(! $path, 404);

            // Legacy uploads only have a filename (no path separator)
            // These were not persisted and are no longer available
            if (! str_contains($path, '/')) {
                abort(404, 'Source image no longer available');
            }

            $content = $sourceStorage->get($path);
            abort_if($content === null, 404);

            return response($content, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        abort(404);
    }
}
