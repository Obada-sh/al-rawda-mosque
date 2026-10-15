<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

/*
| The whole system lives in the admin panel (/admin). The only pages
| outside it are the first-run setup, which creates the admin account.
*/

Route::redirect('/', '/admin');

Route::controller(SetupController::class)->group(function (): void {
    Route::get('/setup', 'create')->name('setup.create');
    Route::post('/setup', 'store')->name('setup.store')->middleware('throttle:10,1');
});
