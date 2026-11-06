<?php

namespace App\Http\Controllers\English;

use App\Actions\English\Cards\PrepareEnglishCards;
use App\Enums\CardPaper;
use App\Filament\English\Pages\Dashboard;
use App\Http\Controllers\Controller;
use App\Models\English\EnglishCourse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The printable attendance cards of an English course's students (opened
 * from the English section), in the design of the course's level: nine to
 * an A4 sheet, or one to a page. The page is the mosque's card page with
 * the English card in it.
 */
final class EnglishCardController extends Controller
{
    public function __invoke(Request $request, PrepareEnglishCards $prepareCards): View
    {
        $cards = $prepareCards->handle($request->query());
        $course = EnglishCourse::query()->find($request->query('english_course_id'));

        // How to print is a way of looking at the cards, not something that is stored.
        $paper = CardPaper::tryFrom((string) $request->query('paper')) ?? CardPaper::Sheet;
        $back = $this->backUrl($request);

        return view('cards.print', [
            'cards' => $cards,
            'badge' => 'cards.english-badge',
            'heading' => "بطاقات {$course?->name}",
            'emptyText' => 'لا يوجد طلاب لطباعة بطاقاتهم.',
            'paper' => $paper,
            'papers' => collect(CardPaper::cases())->mapWithKeys(fn (CardPaper $choice): array => [
                $choice->value => $request->fullUrlWithQuery(['paper' => $choice->value, 'back' => $back]),
            ]),
            'homeUrl' => Dashboard::getUrl(panel: 'english'),
            'backUrl' => $back,
        ]);
    }

    /**
     * The page of the English section the admin came from, or its home.
     * Only a page of this section on this same address is followed.
     */
    private function backUrl(Request $request): string
    {
        foreach ([$request->query('back'), $request->headers->get('referer')] as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $parts = parse_url($candidate);

            if ($parts === false || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)) {
                continue;
            }

            $port = $parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80);
            $path = $parts['path'] ?? '';

            if (($parts['host'] ?? null) === $request->getHost()
                && $port === $request->getPort()
                && str_starts_with($path, '/english')
                && ! str_starts_with($path, '/english/cards')) {
                return $candidate;
            }
        }

        return Dashboard::getUrl(panel: 'english');
    }
}
