<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\Cards\AttendanceCard;
use App\Enums\CardPaper;
use App\Filament\Pages\Dashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The page that prints attendance cards, shared by the students' and the
 * teachers' cards.
 */
trait PrintsCards
{
    use ReturnsToPanel;

    /**
     * @param  Collection<int, AttendanceCard>  $cards
     */
    private function printPage(Request $request, Collection $cards, string $heading, string $emptyText): View
    {
        // How to print is a way of looking at the cards, not something that is stored:
        // anything else than a known answer is the sheet.
        $paper = CardPaper::tryFrom((string) $request->query('paper')) ?? CardPaper::Sheet;

        $back = $this->backUrl($request);

        return view('cards.print', [
            'cards' => $cards,
            'heading' => $heading,
            'emptyText' => $emptyText,
            'paper' => $paper,
            'papers' => collect(CardPaper::cases())->mapWithKeys(fn (CardPaper $choice): array => [
                $choice->value => $request->fullUrlWithQuery(['paper' => $choice->value, 'back' => $back]),
            ]),
            'homeUrl' => Dashboard::getUrl(),
            'backUrl' => $back,
        ]);
    }
}
