<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * What an open page of the panel calls every few minutes, so that what
 * is due at a time of day (the evening copy for Google Drive) happens
 * while the panel stays open on one page: the system has no clock of its
 * own. It answers nothing; the work is done by the panel's middleware
 * once the answer was sent.
 */
final class TickController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
