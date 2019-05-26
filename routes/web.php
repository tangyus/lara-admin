<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([
	'prefix' => 'api',
	'namespace' => 'Api',
	'middleware' => ['api']
], function (\Illuminate\Routing\Router $router) {
	$router->post('auth', 'AuthController@auth');

	$router->group(['middleware' => 'miniprogram.auth'], function ($router) {
		$router->post('info', 'ApiController@info');
		$router->post('point_record', 'ApiController@pointRecord');
	});
});

Route::get('/', function () {
    return view('welcome');
});
