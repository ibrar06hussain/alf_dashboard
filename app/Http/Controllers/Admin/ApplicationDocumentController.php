<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    public function download(Application $application, ApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->application_id === $application->id, 404);

        $user = auth()->user();

        if ($user->hasRole('District Officer') && $application->district_id !== $user->district_id) {
            abort(403);
        }

        if ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            $application->loadMissing('scheme');
            if ($user->department_id && $application->scheme->department_id !== $user->department_id) {
                abort(403);
            }
        }

        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name ?: basename($document->file_path)
        );
    }
}
