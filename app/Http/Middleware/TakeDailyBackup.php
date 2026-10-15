<?php

namespace App\Http\Middleware;

use App\Actions\Backups\GuardDailyBackup;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes the automatic backup of the day the first time the admin opens a
 * page of the panel that day. The system runs on a laptop that is not
 * always on, so the backup is tied to use rather than to a scheduler.
 *
 * The backup is taken after the page has been sent, so the admin never
 * waits for it.
 */
final class TakeDailyBackup
{
    public function __construct(
        private readonly GuardDailyBackup $dailyBackup,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Page loads only: the background requests of an open page are skipped.
        if ($this->config->get('rawda.backups.automatic') && $request->isMethod('GET') && ! $request->ajax()) {
            $this->dailyBackup->handle();
        }
    }
}
