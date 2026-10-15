<?php

namespace App\Http\Middleware;

use App\Actions\Setup\CreateFirstAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the admin account exists, every panel address leads to the
 * first-run page, where it is created.
 */
final class RedirectToSetupWhenNoAdmin
{
    public function __construct(private readonly CreateFirstAdmin $createFirstAdmin) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->createFirstAdmin->isPending()) {
            return redirect()->route('setup.create');
        }

        return $next($request);
    }
}
