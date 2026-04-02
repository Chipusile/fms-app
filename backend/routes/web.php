<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/readyz', function () {
    $checks = [];
    $healthy = true;

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable $exception) {
        report($exception);
        $checks['database'] = 'failed';
        $healthy = false;
    }

    if (config('security.readiness.check_cache')) {
        $store = config('security.readiness.cache_store', config('cache.default'));
        $probeKey = 'readiness:'.bin2hex(random_bytes(8));

        try {
            Cache::store($store)->put($probeKey, 'ok', 10);
            $checks['cache'] = Cache::store($store)->get($probeKey) === 'ok' ? 'ok' : 'failed';
            Cache::store($store)->forget($probeKey);
        } catch (\Throwable $exception) {
            report($exception);
            $checks['cache'] = 'failed';
        }

        if ($checks['cache'] !== 'ok') {
            $healthy = false;
        }
    }

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
});
