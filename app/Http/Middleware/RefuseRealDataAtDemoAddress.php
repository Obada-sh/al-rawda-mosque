<?php

namespace App\Http\Middleware;

use App\Services\Demo\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The demo has an address of its own (demo.bat serves it on a port of its
 * own). If the system that answers there holds another database than the
 * demo's, it refuses and says why, instead of letting someone "try
 * things" on the real data.
 */
final class RefuseRealDataAtDemoAddress
{
    public function __construct(private readonly DemoMode $demo) {}

    public function handle(Request $request, Closure $next): Response
    {
        // The port the web server listens on, not the one the browser names.
        $port = (int) $request->server('SERVER_PORT');

        if ($this->demo->wouldShowRealDataAt($port)) {
            return response()->view('demo.misplaced', status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
