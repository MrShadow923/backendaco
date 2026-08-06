<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return redirect()->away(env('FRONTEND_URL', 'http://localhost:3000') . '/login');
})->name('login');
