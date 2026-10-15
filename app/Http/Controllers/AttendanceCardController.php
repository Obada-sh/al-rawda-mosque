<?php

namespace App\Http\Controllers;

use App\Actions\Cards\PrepareAttendanceCards;
use App\Http\Controllers\Concerns\PrintsCards;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The printable attendance cards of a course's students (opened from the
 * panel), in the mosque's card design: nine to an A4 sheet, or one to a page.
 */
final class AttendanceCardController extends Controller
{
    use PrintsCards;

    public function __invoke(Request $request, PrepareAttendanceCards $prepareAttendanceCards): View
    {
        $cards = $prepareAttendanceCards->handle($request->query());
        $course = Course::query()->find($request->query('course_id'));

        return $this->printPage($request, $cards, "بطاقات {$course?->name}", 'لا يوجد طلاب لطباعة بطاقاتهم.');
    }
}
