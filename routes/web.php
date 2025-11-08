<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;

Route::view('/events', 'events.index')->name('events.index');
Route::view('/events/create', 'events.create')->name('events.create');
Route::view('/events/{id}', 'events.show')->whereNumber('id')->name('events.show');
Route::view('/events/{id}/edit', 'events.edit')->whereNumber('id')->name('events.edit');

// (Opcional) redireciona raiz para a lista
Route::redirect('/', '/events');
