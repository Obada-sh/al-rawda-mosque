<?php

namespace App\Http\Middleware;

use App\Actions\Backups\GuardEveningDriveCopy;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Puts the evening copy in the Google Drive folder when it is due, after
 * a page of the panel was sent: a page the admin opens, or the tick an
 * open page sends every few minutes (TickController). The system runs on
 * a laptop with no scheduler, so the copy is tied to use.
 */
final class TakeEveningDriveCopy
{
    public function __construct(
        private readonly GuardEveningDriveCopy $driveCopy,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Page loads and ticks only: the background requests of a page that is working are skipped.
        if (! $this->config->get('rawda.backups.drive') || ! $request->isMethod('GET')) {
            return;
        }

        try {
            $this->driveCopy->handle();
        } catch (Throwable $failure) {
            // The page was sent already; what went wrong is in the log.
            report($failure);
        }
    }
}
