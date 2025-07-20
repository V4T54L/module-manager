<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UsersController;
use Modules\Users\Livewire\Pages\AboutPage;
use Modules\Users\Livewire\Pages\HomePage;

// Route::middleware(['auth', 'verified'])->group(function () {
//     // Route::resource('users', UsersController::class)->names('users');
//     Route::get('home', HomePage::class)->name('home');
//     Route::get('about', AboutPage::class)->name('about');
// });

Route::prefix('users')->middleware('web')->group(function () {
    Route::get('/home',   HomePage::class)->name('users.home'); // existing
    Route::get('/about', AboutPage::class)->name('users.about');     // new
});
