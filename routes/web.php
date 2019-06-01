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
	$router->get('auth', 'AuthController@auth');
	$router->post('user_info', 'AuthController@decryptUserInfo');

	$router->group(['middleware' => 'miniprogram.auth'], function ($router) {
	    $router->post('code_check', 'ApiController@codeCheck');
		$router->post('info', 'ApiController@info');
		$router->post('point_record', 'ApiController@pointRecord');
		$router->post('point_receive', 'ApiController@pointReceive');

		// 抽奖
		$router->post('lottery_prize', 'ApiController@lotteryPrizes');
		$router->post('lottery', 'ApiController@lottery');
		$router->post('user_lottery_prize', 'ApiController@userLotteryPrizeList');
		$router->post('store_address', 'ApiController@storeUserPrizeAddress');

		// 兑换
        $router->post('exchange_prize', 'ApiController@exchangePrizes');
        $router->post('exchange', 'ApiController@exchange');
        $router->post('user_exchange_prize', 'ApiController@userExchangePrizeList');
	});
});

Route::group([
    'prefix'    => 'web',
    'namespace' => 'Web',
    'middleware' => ['web']
], function (\Illuminate\Routing\Router $router) {
    $router->post('auth', 'IndexController@auth');
    $router->post('login', 'IndexController@login');
    $router->get('jssdk', 'IndexController@jssdk');

    $router->post('prize_info', 'IndexController@prizeInfo');
    $router->post('verify_prize', 'IndexController@verifyPrize');
    $router->post('verify_record', 'IndexController@verifyRecord');
});
