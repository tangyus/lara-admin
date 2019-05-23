<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('create_codes', 'HomeController@createCodes');
    $router->get('wx_codes', 'HomeController@wxCode');

    // 二维码管理
    $router->get('qrcodes/download', 'QrcodeController@download');
    $router->resource('qrcodes', QrcodeController::class, ['only' => ['index', 'create', 'store']]);

    // 区域账号管理
    $router->get('accounts_list', 'AccountController@accountsList');
    $router->get('accounts_cities/{account_id}', 'AccountController@accountsCities');
    $router->get('accounts_info/{type}', 'AccountController@accountsDetail');
    $router->resource('accounts', AccountController::class, ['except' => ['destroy']]);

    // 区域门店管理
    $router->get('shops_list', 'ShopController@shopsList');
    $router->resource('shops', ShopController::class, ['except' => ['destroy']]);

    // 区域礼品管理
    $router->resource('prizes', PrizeController::class, ['except' => ['destroy']]);

    // 用户管理
    $router->get('users/point_record', 'UserController@pointRecord');
    $router->get('users/point_record/{id}', 'UserController@pointRecordShow');
    $router->resource('users', UserController::class, ['only' => ['index', 'show']]);

//    $router->get('data/act', 'PrizeController@data');
//    $router->get('data/stats', 'StatsController@data');
});
