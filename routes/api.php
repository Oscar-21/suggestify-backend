<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('foobar', 'SpotifyController@foobar');
Route::get('login', 'SpotifyController@login');
Route::get('foo', function() {
    $redis = Redis::connection();
    $redis->set('user', 'oscar');
    return $redis->get('user');
});
Route::get('callback', 'SpotifyController@callback');
Route::get('test', 'SpotifyController@test');
Route::get('damn', function () {
    return view('welcome');
});
