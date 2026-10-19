<?php

namespace App\Http\Controllers;

use App\Actions\Documents\PrepareCircleSheets;
use App\Actions\Documents\PrintedCircleSheets;
use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Concerns\ReturnsToPanel;
use App\Models\Circle;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The sheets of a course's circles on A4 paper (one circle, or every circle
 * of the course, each on its own pages): the list of the students with
 * their phones and their records, or their attendance codes.
 */
final class CircleSheetController extends Controller
{
    use ReturnsToPanel;

    public function students(Request $request, PrepareCircleSheets $prepareCircleSheets): View
    {
        return $this->page($request, 'documents.circle-students', $prepareCircleSheets->handle($request->query()), 'كشف');
    }

    public function codes(Request $request, PrepareCircleSheets $prepareCircleSheets): View
    {
        return $this->page($request, 'documents.circle-codes', $prepareCircleSheets->handle($request->query(), withCodes: true), 'رموز حضور');
    }

    private function page(Request $request, string $view, PrintedCircleSheets $printed, string $what): View
    {
        return view($view, [
            'sheets' => $printed->sheets,
            'options' => $printed->options,
            'heading' => $what.' '.$this->scope($request),
            'homeUrl' => Dashboard::getUrl(),
            'backUrl' => $this->backUrl($request),
        ]);
    }

    /**
     * "حلقة النور — دورة الخريف", or "حلقات دورة الخريف".
     */
    private function scope(Request $request): string
    {
        $circle = Circle::query()->with('course')->find($request->query('circle_id'));

        if ($circle !== null) {
            return "{$circle->name} — {$circle->course->name}";
        }

        return 'حلقات '.Course::query()->find($request->query('course_id'))?->name;
    }
}
