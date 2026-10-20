<?php

namespace App\Http\Controllers;

use App\Actions\Documents\PrepareStudentFiles;
use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Concerns\ReturnsToPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The files of many students on A4 paper, each starting on a page of its
 * own: every student, the students of a course or of a circle, or those
 * ticked in the list. Printed together, or saved as one PDF.
 */
final class StudentFilesController extends Controller
{
    use ReturnsToPanel;

    public function __invoke(Request $request, PrepareStudentFiles $prepareStudentFiles): View
    {
        $files = $prepareStudentFiles->handle($request->query());

        return view('documents.student-files', [
            'files' => $files,
            'heading' => "ملفات الطلاب — {$files->scope}",
            'homeUrl' => Dashboard::getUrl(),
            'backUrl' => $this->backUrl($request),
        ]);
    }
}
