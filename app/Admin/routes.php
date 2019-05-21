<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    // 区域账号管理
    $router->resource('district/accounts', AccountController::class);
    $router->get('district/accounts_list', 'AccountController@accountsList');
    $router->get('district/accounts_info/{type}', 'AccountController@accountsDetail');

    // 区域门店管理
    $router->resource('district/shops', ShopController::class);
    $router->get('district/shops_list', 'ShopController@shopsList');

    // 区域礼品管理
    $router->resource('district/prizes', PrizeController::class);

    // 用户管理
    $router->get('users/point_record', 'UserController@pointRecord');
    $router->get('users/point_record/{id}', 'UserController@pointRecordShow');
    $router->resource('users', UserController::class);
});
