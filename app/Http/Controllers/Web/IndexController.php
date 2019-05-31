<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\model\Shop;
use App\Model\UserPrize;
use EasyWeChat\Factory;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    private $shop;

    private function checkLogin()
    {
        $shop = Shop::leftJoin('accounts', 'a_id', 's_account_id')->where('s_token', request()->header('token', ''))->first();
        $this->shop = $shop;
    }

    /**
     * 判断登录态
     * @return \Illuminate\Http\JsonResponse
     */
    public function auth()
    {
        $this->checkLogin();

        if ($this->shop && $this->shop->s_expired > time()) {
            return $this->responseSuccess([
                'token'         => $this->shop->s_token,
                'name'          => $this->shop->s_name,
                'number'        => $this->shop->s_number,
                'address'       => $this->shop->s_address,
                'manager'       => $this->shop->s_manager,
                'managerPhone'  => $this->shop->s_manager_phone,
                'shopManager'   => $this->shop->a_manager_phone
            ]);
        } else {
            return $this->responseSuccess([]);
        }
    }

    /**
     * 登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $number = $request->input('username');
        $password = $request->input('password');

        $shop = Shop::leftJoin('accounts', 'a_id', 's_account_id')->where(['s_number' => $number])->first();
        if (!$shop) {
            return $this->responseFail('用户不存在');
        } elseif ($shop->s_password != $password) {
            return $this->responseFail('密码不正确');
        }

        $shop->s_token = sha1(time() . uniqid());
        $shop->s_expired = time() + 7000;
        $shop->save();

        return $this->responseSuccess([
            'token'         => $shop->s_token,
            'name'          => $shop->s_name,
            'number'        => $shop->s_number,
            'address'       => $shop->s_address,
            'manager'       => $shop->s_manager,
            'managerPhone'  => $shop->s_manager_phone,
            'shopManager'   => $shop->a_manager_phone
        ]);
    }

    /**
     * 券码奖品信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function prizeInfo(Request $request)
    {
        $this->checkLogin();
        if (!$this->shop) {
            return $this->responseFail('请先登录，再进行操作!');
        }

        $prizeCode = $request->input('code');
        $userPrize = UserPrize::with(['prize', 'user'])->where(['up_code' => $prizeCode, 'up_received' => 0])->first();
        if (!$userPrize) {
            return $this->responseFail('Invalid Code!');
        }

        return $this->responseSuccess([
            'id'            => $userPrize->up_id,
            'nickName'      => $userPrize->user->u_nick,
            'prizeName'     => $userPrize->prize->p_name,
            'prizeThumb'    => $userPrize->prize->p_thumb
        ]);
    }

    /**
     * 奖品核销
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPrize(Request $request)
    {
        $this->checkLogin();
        if (!$this->shop) {
            return $this->responseFail('请先登录，再进行操作!');
        }

        $id = $request->input('id');
        $number = $request->input('number');
        $password = $request->input('password');

        if (($number != $this->shop->s_number) || ($password != $this->shop->s_password)) {
            return $this->responseFail('门店序号或密码不正确!');
        }

        $res = UserPrize::where(['up_id' => $id, 'up_received' => 0])->update(['up_received' => 1, 'up_shop_id' => $this->shop->s_id]);
        if ($res) {
            return $this->responseSuccess($res);
        } else {
            return $this->responseFail('核销异常，核销码不正确!');
        }
    }

    /**
     * 奖品核销记录
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyRecord()
    {
        $this->checkLogin();
        if (!$this->shop) {
            return $this->responseFail('请先登录，再进行操作!');
        }

        $data = [];
        UserPrize::with(['prize', 'user'])
            ->where(['up_received' => 1, 'up_shop_id' => $this->shop->s_id])
            ->get()
            ->map(function ($item) use (&$data) {
                $data[] = [
                    'nick'      => $item->user->u_nick,
                    'phone'     => $item->user->u_phone,
                    'prizeType' => $item->prize->p_type,
                    'prizeName' => $item->prize->p_name,
                    'date'      => $item->up_updated->format('Y-m-d'), // diffForHumans
                    'time'      => $item->up_updated->format('H:i:s'), // diffForHumans
                ];
            });

        return $this->responseSuccess($data);
    }

    public function jssdk()
    {
        // TY wx1c46b3106e3c6bc5 11c7d0796822535df1a4d7eabb3c6fdc
        // 百事 wx97ba3ea86432e115 d268f390922bffd60c98bc93705ed6c7
        $config = [
            'app_id' => 'wx1c46b3106e3c6bc5',
            'secret' => '11c7d0796822535df1a4d7eabb3c6fdc',

            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            //...
        ];
        $app = Factory::officialAccount($config);
        $jssdkApi = ['checkJsApi', 'scanQRCode'];
        $app->jssdk->setUrl('https://jifenyouli.pamierde.com/web/index.html');
        $response = $app->jssdk->buildConfig($jssdkApi, false, false, true);
        return $response;
    }
}