<?php

namespace App\Http\Controllers;

use App\Models\FieldVisit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HouseholdVisitController extends Controller
{
    public function show(FieldVisit $fieldVisit): View
    {
        abort_unless($fieldVisit->household, 404);
        Gate::authorize('view', $fieldVisit);
        $fieldVisit->loadMissing(['household.purok.barangay', 'recordedBy']);

        $photos = collect($fieldVisit->photos ?? [])->map(function (mixed $photo, int $index) use ($fieldVisit): array {
            $path = is_array($photo) ? $this->photoPath($photo) : null;
            $available = $path !== null && Storage::disk('local')->exists($path);
            if (! $available) {
                $this->logMissingPhoto($fieldVisit, $index);
            }

            return ['index' => $index, 'available' => $available];
        });

        return view('field-visits.show', [
            'visit' => $fieldVisit,
            'photos' => $photos,
            'routePrefix' => request()->user()->role,
            'layout' => request()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.portal',
        ]);
    }

    public function photo(FieldVisit $fieldVisit, int $photoIndex): StreamedResponse
    {
        abort_unless($fieldVisit->household, 404);
        Gate::authorize('view', $fieldVisit);
        $photo = ($fieldVisit->photos ?? [])[$photoIndex] ?? null;
        $path = is_array($photo) ? $this->photoPath($photo) : null;
        abort_unless($path !== null, 404);

        if (! Storage::disk('local')->exists($path)) {
            $this->logMissingPhoto($fieldVisit, $photoIndex);
            abort(404);
        }

        $mimeType = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            default => 'image/jpeg',
        };

        return Storage::disk('local')->response($path, basename($path), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function photoPath(array $photo): ?string
    {
        $path = $photo['path'] ?? null;

        return is_string($path)
            && preg_match('~\Avisit-photos/[0-9]{4}/[0-9]{2}/[0-9a-fA-F-]{36}\.(?:jpg|png|webp|heic)\z~', $path)
            ? $path : null;
    }

    private function logMissingPhoto(FieldVisit $fieldVisit, int $index): void
    {
        Log::warning('Household visit photo is unavailable.', [
            'field_visit_id' => $fieldVisit->id,
            'photo_index' => $index,
        ]);
    }
}
