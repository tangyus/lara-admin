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
	'prefix'    => 'api',
	'namespace' => 'Api',
	'middleware' => ['api']
], function (\Illuminate\Routing\Router $router) {
	$router->post('auth', 'AuthController@auth');
	$router->post('user_info', 'AuthController@decryptUserInfo');

	$router->group(['middleware' => 'miniprogram.auth'], function ($router) {
	    $router->post('code_check', 'ApiController@codeCheck');
		$router->post('info', 'ApiController@info');
		$router->post('point_record', 'ApiController@pointRecord');
		$router->post('point_receive', 'ApiController@pointReceive');
		$router->post('lottery', 'ApiController@lottery');
	});
});

Route::get('/', function () {
    return view('welcome');
});
