<?php

use App\Models\MediaJob;
use App\Services\Ffmix\FolderWatchdogService;
use App\Services\Ffmix\MediaSampleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/stream', 'stream-dashboard')->name('stream');
Route::view('/stream/record', 'stream-dashboard')->name('stream.record');

Route::get('/system/status', function (FolderWatchdogService $watchdog) {
    $runtimeErrors = Cache::get('ffmix.runtime_errors', []);
    $latestError = is_array($runtimeErrors) && $runtimeErrors !== []
        ? $runtimeErrors[count($runtimeErrors) - 1]
        : null;
    $latestJob = MediaJob::latest('id')->first();
    $streamStatus = 'standby';
    if ($latestJob !== null) {
        $status = is_object($latestJob->status)
            ? $latestJob->status->value
            : (string) $latestJob->status;
        $streamStatus = $status === 'running'
            ? 'running'
            : ($status === 'failed' ? 'error' : 'ready');
    }

    return response()->json([
        'web' => 'ready',
        'watchdog' => $watchdog->daemonStatus(),
        'stream' => $streamStatus,
        'latest_error' => $latestError,
    ]);
})->name('system.status');

Route::get('/system/media-samples', function (MediaSampleService $samples) {
    $media = $samples->all();

    return response()->json([
        'count' => count($media),
        'samples' => $media,
    ]);
})->name('system.media-samples');
