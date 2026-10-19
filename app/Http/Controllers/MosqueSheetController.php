<?php

namespace App\Http\Controllers;

use App\Actions\Documents\PrepareMosqueSheet;
use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Concerns\ReturnsToPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The sheet of the mosque's students on A4 paper, without choosing a
 * circle: every student in the system, or the students of one course, in
 * one table by name, with the columns the admin chose.
 */
final class MosqueSheetController extends Controller
{
    use ReturnsToPanel;

    public function __invoke(Request $request, PrepareMosqueSheet $prepareMosqueSheet): View
    {
        $sheet = $prepareMosqueSheet->handle($request->query());

        return view('documents.mosque-students', [
            'sheet' => $sheet,
            'heading' => $sheet->course === null ? 'كشف كل طلاب الجامع' : "كشف طلاب {$sheet->course->name}",
            'homeUrl' => Dashboard::getUrl(),
            'backUrl' => $this->backUrl($request),
        ]);
    }
}
