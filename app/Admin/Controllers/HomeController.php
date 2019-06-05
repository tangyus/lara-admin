<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\model\Account;
use App\model\Code;
use App\Model\Rule;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Http\StreamResponse;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * 生成二维码券码 code
     * @return int|string
     */
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

    /**
     * 生成小程序码
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     */
    public function wxCode()
    {
        $config = [
            'app_id' => 'wx82de84528e164c9b',
            'secret' => '74f2e0be5ec582ba0b858c8c6bb7fd47',

            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            'log' => [
                'level' => 'debug',
                'file' => public_path().'/wechat.log',
            ],
        ];
        $app = Factory::miniProgram($config);
        $codes = Code::whereNull('c_path')->limit(10)->get();
        foreach ($codes as $code) {
            $response = $app->app_code->getUnlimit('SDBJJFYL_' . $code->c_code, [
                'page'  => 'Pages/Index/Index',
                'width' => 430,
            ]);
            if ($response instanceof StreamResponse) {
                $filename = $code->c_code.'_'.$code->c_id.'.jpg';
                $response->saveAs(public_path() . '/codes', $filename);

                $code->c_path = '/codes/' . $filename;
                $code->c_filename = $filename;
                $code->save();
            }
        }
    }
}
