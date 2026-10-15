<?php

namespace App\Http\Middleware;

use App\Actions\Settings\RemindAboutIncompleteProfiles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the periodic reminders when the admin opens a page of the panel.
 * The system runs on a laptop that is not always on, so reminders are
 * tied to use rather than to a scheduler.
 */
final class RemindAdmin
{
    public function __construct(private readonly RemindAboutIncompleteProfiles $incompleteProfiles) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Page loads only: the background requests of an open page are skipped.
        if ($request->isMethod('GET') && ! $request->ajax()) {
            $this->incompleteProfiles->handle();
        }

        return $next($request);
    }
}
