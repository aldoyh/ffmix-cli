<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/stream', 'stream-dashboard')->name('stream');
Route::view('/stream/record', 'stream-dashboard')->name('stream.record');
