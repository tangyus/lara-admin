<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\model\Code;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Http\StreamResponse;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->row(Dashboard::title())
            ->row(function (Row $row) {

                $row->column(4, function (Column $column) {
                    $column->append(Dashboard::environment());
                });

                $row->column(4, function (Column $column) {
                    $column->append(Dashboard::extensions());
                });

                $row->column(4, function (Column $column) {
                    $column->append(Dashboard::dependencies());
                });
            });
    }

    public function createCodes()
    {
        try {
            $last = Code::orderBy('c_id', 'desc')->first();
            $begin = $last ? $last->c_id : 0;

            $attributes = [];
            for ($i = $begin; $i < $begin + 10000; $i++) {
                $attributes[] = [
                    'c_code' => strtoupper(substr(md5($i), 0, 20))
                ];
            }

            Code::insert($attributes);
            return 1;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function wxCode()
    {
        $config = [
            'app_id' => 'wx82de84528e164c9b', // wx82de84528e164c9b wxfe49d510a7e7853b
            'secret' => '74f2e0be5ec582ba0b858c8c6bb7fd47', // 74f2e0be5ec582ba0b858c8c6bb7fd47 2aadf5a404e444e45408adafee76f2a0

            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            'log' => [
                'level' => 'debug',
                'file' => public_path().'/wechat.log',
            ],
        ];

        $app = Factory::miniProgram($config);
        $response = $app->app_code->getUnlimit('SDBJJFYL_SXSWDSA', [
            'page'  => 'Pages/Index/Index',
            'width' => 430,
        ]);
        $response->saveAs(public_path() . '/upload', 'demo.png');


        $codes = Code::whereNull('c_path')->limit(10)->get();
        foreach ($codes as $code) {
            $response = $app->app_code->getUnlimit('SDBJJFYL_' . $code->c_code, [
                'page'  => 'pages/index/index',
                'width' => 430,
            ]);
            if ($response instanceof StreamResponse) {
                $filename = $code->c_code . '_' . $code->c_id . '.png';
                $response->saveAs(public_path() . '/codes', $filename);

                $code->c_path = '/codes/' . $filename;
                $code->c_filename = $filename;
                $code->save();
            }
        }
    }
}
