<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

uses(RefreshDatabase::class);

/**
 * 未認証で auth:sanctum 保護ルートを叩いたとき、Accept が JSON でなくても
 * 401 JSON を返す (route('login') への 500 にならない)。かつ、その際に
 * 発生する RouteNotFoundException が laravel.log を汚染しないこと。
 *
 * 背景: Authenticate ミドルウェアは未認証かつ非JSON要求のとき route('login')
 * を引くが、本APIに login 名前付きルートは無いため RouteNotFoundException を投げる。
 * bootstrap/app.php の render フックで 401 に変換しつつ dontReport で報告を抑止する。
 */
test('unauthenticated api request returns 401 json even when Accept is not json', function () {
    // Accept: text/html → expectsJson() = false → route('login') 経路を踏む
    $response = $this->get('/api/my-classrooms', ['Accept' => 'text/html']);

    $response->assertStatus(401);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJson(['message' => 'Unauthenticated.']);
});

test('login-redirect RouteNotFoundException is not reported (no laravel.log pollution)', function () {
    $handler = app(ExceptionHandler::class);

    expect($handler->shouldReport(new RouteNotFoundException('Route [login] not defined.')))
        ->toBeFalse();
});
