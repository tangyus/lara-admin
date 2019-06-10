<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('/', function () {
        return redirect('/admin/users');
    });
    // 二维码管理
    $router->get('qrcodes/download', 'QrcodeController@download');
    $router->resource('qrcodes', QrcodeController::class, ['only' => ['index', 'create', 'store']]);

    // 区域账号管理
    $router->get('accounts_list', 'AccountController@accountsList');
    $router->resource('accounts', AccountController::class, ['except' => ['destroy']]);

    // 区域门店管理
    $router->get('shops_list', 'ShopController@shopsList');
    $router->resource('shops', ShopController::class, ['except' => ['destroy']]);

    // 区域礼品管理
    $router->resource('prizes', PrizeController::class, ['except' => ['destroy', 'create', 'store']]);

    // 用户管理
    $router->get('users/point_record', 'UserController@pointRecord');
    $router->get('users/point_record/{id}', 'UserController@pointRecordShow');
    $router->get('users/prize', 'UserController@userPrize');
    $router->put('users/prize/{id}', 'UserController@updateUserPrize');
    $router->resource('users', UserController::class, ['only' => ['index', 'show']]);

    // 数据管理
    $router->get('data/act', 'ActController@index');
    $router->get('data/stats', 'StatsController@data');

    // 规则管理
    $router->resource('rules', RuleController::class, ['except' => ['delete']]);

    $router->get('api_logs', 'ApiLogController@index');
});
