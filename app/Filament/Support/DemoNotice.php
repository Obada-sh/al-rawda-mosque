<?php

namespace App\Filament\Support;

use App\Services\Demo\DemoAccount;
use App\Services\Demo\DemoMode;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;

/**
 * How the demo says that it is the demo: a mark next to the name of the
 * mosque on every page, and on the sign-in page the account to get in
 * with. The real system shows neither.
 */
final class DemoNotice
{
    public function __construct(private readonly DemoMode $demo) {}

    public function badge(): ?View
    {
        return $this->demo->isOn() ? view('filament.demo.badge') : null;
    }

    public function signIn(): ?View
    {
        if (! $this->demo->isOn()) {
            return null;
        }

        $account = ['username' => DemoAccount::USERNAME, 'password' => DemoAccount::PASSWORD];

        // Someone trying the "my account" page may have changed it: the note
        // never shows an account that no longer opens the demo.
        return view('filament.demo.sign-in', [
            'account' => Filament::auth()->validate($account) ? $account : null,
        ]);
    }
}
