<?php

namespace App\Http\Controllers;

use App\Actions\Cards\PrepareTeacherCards;
use App\Http\Controllers\Concerns\PrintsCards;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The printable attendance cards of a course's teachers (opened from the
 * teachers' list or the course), in the mosque's card design without the
 * circle.
 */
final class TeacherCardController extends Controller
{
    use PrintsCards;

    public function __invoke(Request $request, PrepareTeacherCards $prepareTeacherCards): View
    {
        $cards = $prepareTeacherCards->handle($request->query());
        $course = Course::query()->find($request->query('course_id'));

        return $this->printPage(
            $request,
            $cards,
            "بطاقات أساتذة {$course?->name}",
            'لا يوجد أساتذة لطباعة بطاقاتهم: البطاقة لمن يشرف على حلقة نشطة من الدورة أو يساعد فيها.',
        );
    }
}
