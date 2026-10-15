<?php

namespace App\Http\Controllers;

use App\Actions\Setup\CreateFirstAdmin;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Auth\AdminSetupRequest;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * First run: the person installing the system creates the admin account.
 */
final class SetupController extends Controller
{
    public function create(CreateFirstAdmin $createFirstAdmin): View|RedirectResponse
    {
        if (! $createFirstAdmin->isPending()) {
            return redirect()->to($this->panelUrl());
        }

        return view('setup.create');
    }

    public function store(AdminSetupRequest $request, CreateFirstAdmin $createFirstAdmin): RedirectResponse
    {
        try {
            $admin = $createFirstAdmin->handle($request->safe()->only(['name', 'username', 'password']));
        } catch (BusinessRuleViolation) {
            // Someone finished the setup in the meantime: sign in instead.
            return redirect()->to($this->panelUrl());
        }

        Filament::getDefaultPanel()->auth()->login($admin);
        $request->session()->regenerate();

        return redirect()->to($this->panelUrl());
    }

    private function panelUrl(): string
    {
        return Filament::getDefaultPanel()->getUrl() ?? url('/');
    }
}
