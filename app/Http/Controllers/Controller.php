<?php

namespace App\Http\Controllers;

use App\model\Code;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Http\StreamResponse;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

	public function responseSuccess($data, $message = 'Success')
	{
		return response()->json([
			'result' 	=> 900,
			'message' 	=> $message,
			'data' 		=> $data,
		]);
    }

    public function responseFail($message = 'Fail')
    {
        return response()->json([
            'result' 	=> 1000,
            'message' 	=> $message,
            'data' 		=> [],
        ]);
    }

    public function responseLogin($message = '请先登录，再进行操作!')
    {
        return response()->json([
            'result' 	=> 800,
            'message' 	=> $message,
            'data' 		=> [],
        ]);
    }

    public function responseDefine($result, $message, $data = [])
    {
        return response()->json([
            'result' 	=> $result,
            'message' 	=> $message,
            'data' 		=> $data,
        ]);
    }

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

    public function test()
    {
        $config = [
            'app_id' => 'wx82de84528e164c9b',
            'secret' => '74f2e0be5ec582ba0b858c8c6bb7fd47',

            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            'log' => [
                'driver' => 'daily',
                'level' => 'info',
                'file' => public_path() . '/wechat.log',
            ],
        ];
        $app = Factory::miniProgram($config);
        $response = $app->app_code->getUnlimit('SDBJJFYL_', [
            'page'  => 'Pages/Index/Index',
            'width' => 280,
        ]);
        if ($response instanceof StreamResponse) {
            $filename = 'demo.jpg';
            $response->saveAs(public_path(), $filename);
        }
        dd(1);
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
                'driver' => 'daily',
                'level' => 'info',
                'file' => 'd:/wechat.log',
            ],
        ];
        $app = Factory::miniProgram($config);

        $codes = Code::whereNull('c_path')->where('c_id', '>', '330000')->limit(10)->get();
        $i = 0;
        foreach ($codes as $code) {
            $response = $app->app_code->getUnlimit('SDBJJFYL_' . $code->c_code, [
                'page'  => 'Pages/Index/Index',
                'width' => 280,
            ]);
            if ($response instanceof StreamResponse) {
                $filename = $code->c_id.'_'.$code->c_code.'.jpg';
                $response->saveAs('d:/codes', $filename);

                $code->c_path = '/codes/' . $filename;
                $code->c_filename = $filename;
                $code->save();
                $i++;
            }
        }
        return ['count' => $i];
    }

    public function wxCodeCopy()
    {
        $config = [
            'app_id' => 'wx82de84528e164c9b',
            'secret' => '74f2e0be5ec582ba0b858c8c6bb7fd47',

            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            'log' => [
                'driver' => 'daily',
                'level' => 'info',
                'file' => 'd:/wechat.log',
            ],
        ];
        $app = Factory::miniProgram($config);

        $codes = Code::where('c_id', '<=', '330000')->whereNull('c_path')->limit(10)->get();
        $i = 0;
        foreach ($codes as $code) {
            $response = $app->app_code->getUnlimit('SDBJJFYL_' . $code->c_code, [
                'page'  => 'Pages/Index/Index',
                'width' => 280,
            ]);
            if ($response instanceof StreamResponse) {
                $filename = $code->c_id.'_'.$code->c_code.'.jpg';
                $response->saveAs('d:/codes', $filename);

                $code->c_path = '/codes/' . $filename;
                $code->c_filename = $filename;
                $code->save();
                $i++;
            }
        }
        return ['count' => $i];
    }
}
