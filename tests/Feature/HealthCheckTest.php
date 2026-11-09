<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

test('database and pgvector extension are available on postgresql 18', function () {
    $extension = DB::select("SELECT extname FROM pg_extension WHERE extname = 'vector'");
    expect($extension)->not->toBeEmpty('The pgvector extension must be installed in PostgreSQL 18.');
});

test('redis connection works', function () {
    Redis::set('health_check', 'ok');
    expect(Redis::get('health_check'))->toBe('ok');
});

test('mercure hub configuration is present', function () {
    expect(config('broadcasting.connections.mercure'))->not->toBeNull();
});
