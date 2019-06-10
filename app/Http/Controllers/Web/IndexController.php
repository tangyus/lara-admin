<?php

namespace App\Http\Controllers\Web;

use App\Exports\UserPrizeExport;
use App\Http\Controllers\Controller;
use App\model\Prize;
use App\model\Shop;
use App\Model\UserPrize;
use EasyWeChat\Factory;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

        if ($this->shop) {
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

        if (empty($shop->s_token)) {
            $shop->s_token = sha1(time() . uniqid());
            $shop->s_expired = time() + 7000;
            $shop->save();
        }

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
            return $this->responseLogin();
        }

        try {
            $prizeCode = $request->input('code');
            $userPrize = UserPrize::with(['prize', 'user'])->where(['up_code' => $prizeCode])->first();
            if (!$userPrize) {
                return $this->responseFail('该券码不存在');
            }
			if ($userPrize->up_received == 1) {
				return $this->responseFail('礼品已核销');
			}
            if ($userPrize->prize->p_account_id != $this->shop->s_account_id) {
                return $this->responseFail('该奖品不属于本区域核销');
            }

            return $this->responseSuccess([
                'id'            => $userPrize->up_id,
                'nickName'      => !empty($userPrize->user->u_nick) ? $userPrize->user->u_nick : '',
                'prizeName'     => $userPrize->prize->p_name,
                'prizeThumb'    => $userPrize->prize->p_img
            ]);
        } catch (\Exception $e) {
            return $this->responseFail($e->getMessage());
        }
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
            return $this->responseLogin();
        }

        $id = $request->input('id');
        $number = $request->input('number');
        $password = $request->input('password');

        if ($number != $this->shop->s_number) {
            return $this->responseFail('门店序号不正确!');
        }
        if ($password != $this->shop->s_password) {
			return $this->responseFail('门店核销密码不正确!');
		}

        $userPrize = UserPrize::where(['up_id' => $id, 'up_received' => 0])->first();
        if ($userPrize) {
            $userPrize->up_received = 1;
            $userPrize->up_shop_id = $this->shop->s_id;
            $userPrize->save();

            Prize::where(['p_id' => $userPrize->up_prize_id])->increment('p_used_number', 1);
            return $this->responseSuccess(1);
        } else {
            return $this->responseFail('该奖品已核销或券码无效');
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
            return $this->responseLogin();
        }

        $data = [];
        UserPrize::with(['prize', 'user'])
            ->where(['up_received' => 1, 'up_shop_id' => $this->shop->s_id])
            ->get()
            ->map(function ($item) use (&$data) {
                $data[] = [
                    'nick'      => !empty($item->user->u_nick) ? $item->user->u_nick : '',
                    'phone'     => $item->user ? $item->user->u_phone : '',
                    'prizeType' => $item->prize->p_type,
                    'prizeName' => $item->prize->p_name,
                    'date'      => $item->up_updated->format('Y-m-d'), // diffForHumans
                    'time'      => $item->up_updated->format('H:i:s'), // diffForHumans
                ];
            });

        return $this->responseSuccess($data);
    }

    /*
     * 获取jssdk
     * @param Request $request
     * @return array|string
     */
    public function jssdk(Request $request)
    {
        $id = $request->input('id', '');
        // TY wx1c46b3106e3c6bc5 11c7d0796822535df1a4d7eabb3c6fdc
        $config = [
            'app_id' => 'wx1c46b3106e3c6bc5',
            'secret' => '11c7d0796822535df1a4d7eabb3c6fdc',

            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            //...
        ];
        $app = Factory::officialAccount($config);
        $jssdkApi = ['checkJsApi', 'scanQRCode'];
        $url = $id ? 'https://jifenyouli.pamierde.com/web/index.html?id='.$id : 'https://jifenyouli.pamierde.com/web/index.html';
        $app->jssdk->setUrl($url);
        $response = $app->jssdk->buildConfig($jssdkApi, false, false, true);

        return $response;
    }

    /**
     * 导出excel
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $sId = $request->get('sid', '');
        $shop = Shop::where('s_token', $sId)->first();
        return Excel::download(new UserPrizeExport($shop ? $shop->s_id : 0), '核销记录.xlsx');
    }
}