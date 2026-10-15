<?php

namespace App\Http\Controllers;

use App\Actions\Documents\PrepareStudentFile;
use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Concerns\ReturnsToPanel;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A student's file on A4 paper (opened from the student's page): their
 * details, every page they recited and every session they attended or
 * missed, under the mosque's logo.
 */
final class StudentFileController extends Controller
{
    use ReturnsToPanel;

    public function __invoke(Request $request, Student $student, PrepareStudentFile $prepareStudentFile): View
    {
        $file = $prepareStudentFile->handle($student, $request->query());

        return view('documents.student-file', [
            'file' => $file,
            'heading' => "ملف الطالب {$student->full_name}",
            'homeUrl' => Dashboard::getUrl(),
            'backUrl' => $this->backUrl($request),
        ]);
    }
}
