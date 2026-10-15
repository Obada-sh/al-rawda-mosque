<?php

namespace App\Http\Controllers\Concerns;

use App\Filament\Pages\Dashboard;
use Illuminate\Http\Request;

/**
 * The "back" button of the pages printed outside the panel (cards, files,
 * sheets): it leads to the page of the panel the admin came from, or home.
 */
trait ReturnsToPanel
{
    /**
     * The page of the panel the admin came from (kept in the address as
     * "back" when the print page links to itself), or the home page. Only a
     * page of this panel on this same address is followed.
     */
    private function backUrl(Request $request): string
    {
        foreach ([$request->query('back'), $request->headers->get('referer')] as $candidate) {
            if (is_string($candidate) && $this->isPanelPage($candidate, $request)) {
                return $candidate;
            }
        }

        return Dashboard::getUrl();
    }

    private function isPanelPage(string $url, Request $request): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)) {
            return false;
        }

        $scheme = $parts['scheme'];

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $parts['path'] ?? '';

        return ($parts['host'] ?? null) === $request->getHost()
            && $port === $request->getPort()
            && str_starts_with($path, '/admin')
            // The print pages themselves are not where the admin came from.
            && ! str_contains($path, '-cards')
            && ! str_starts_with($path, '/admin/print/');
    }
}
