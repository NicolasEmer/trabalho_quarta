<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SyncController;



Route::redirect('/', '/events');

Route::get('/users/create', fn() => view('users.create'))->name('users.create');
Route::get('/login', fn() => view('users.login'))->name('login');
Route::get('/complete-profile', fn() => view('users.complete'))->name('users.complete');
Route::get('/profile', fn() => view('users.profile'))->name('users.profile');
Route::get('/my-events', fn() => view('users.my-events'))->name('users.my-events');

Route::get('/events', fn() => view('events.index'))->name('events.index');
Route::get('/events/create', fn() => view('events.create'))->name('events.create');
Route::get('/events/{id}', fn($id) => view('events.show', ['id' => $id]))
    ->whereNumber('id')->name('events.show');
Route::get('/events/{id}/edit', fn($id) => view('events.edit', ['id' => $id]))
    ->whereNumber('id')->name('events.edit');


Route::post('/admin/sync', [SyncController::class, 'run'])
    ->name('admin.sync.run');
