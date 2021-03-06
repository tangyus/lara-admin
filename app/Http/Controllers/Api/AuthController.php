<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Model\Stats;
use App\Model\User;
use EasyWeChat\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * 用户授权
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
	public function auth(Request $request)
	{
		$code = $request->input('code', '');

		if (Auth::user() && Auth::user()->u_expired > time()) {
			// access_token 未过期
			return $this->responseSuccess(Auth::user()->u_token);
		}

		try {
            $app = Factory::miniProgram(config('miniprogram'));
            $response = $app->auth->session($code);
            if (!empty($response['openid'])) {
                // access_token 需确保数据库唯一
                $token = sha1($response['session_key'] . uniqid() . time());
                $expired = time() + 7000;

                // 3. 判断 openID 对应用户是否存在
                $user = User::where('u_openid', $response['openid'])->first();
                if ($user) {
                    $where = ['u_openid' => $response['openid']];
                    if (Auth::user()) {
                        $where = array_merge($where, ['u_id' => Auth::id()]);
                    }
                    // 存在则更新 token
                    User::where($where)->update([
                        'u_sessionkey'  => $response['session_key'],
                        'u_token'       => $token,
                        'u_expired'     => $expired,
                    ]);
                } else {
                    // 新用户，插入用户信息
                    $attribute = [
                        'u_openid'          => $response['openid'],
                        'u_sessionkey'      => $response['session_key'], // 这个需要存储在数据库，后面解密用户信息需要使用
                        'u_token'           => $token,
                        'u_expired'         => $expired,
                        'u_ip'              => $request->ip(),
                    ];
                    $user = User::create($attribute);
                    Stats::insert([
                        's_time'        => strtotime('today'),
                        's_type'        => 'uv',
                        's_account_id'  => null,
                        's_uid'         => $user->u_id
                    ]);
                }

                return $this->responseSuccess($token);
            } else {
                return $this->responseFail($response['errmsg']);
            }
        } catch (\Exception $e) {
            return $this->responseFail($e->getMessage());
        }
	}

    /**
     * 解密用户信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function decryptUserInfo(Request $request)
    {
        $iv = $request->input('iv');
        $encryptData = $request->input('encryptData');
        $phone = $request->input('phone');

        $user = User::where('u_token', $request->header('s3rd', ''))->first();
        if ($user) {
            try {
                $app = Factory::miniProgram(config('miniprogram'));
                $response = $app->encryptor->decryptData($user->u_sessionkey, $iv, $encryptData);

                $nick = preg_replace_callback(
                    '/./u',
                    function (array $match) {
                        return strlen($match[0]) >= 4 ? '' : $match[0];
                    },
                    $response['nickName']
                );
                User::where('u_id', $user->u_id)->update([
                    'u_nick'    => $nick,
                    'u_headimg' => $response['avatarUrl'],
                    'u_phone'   => $phone
                ]);

                return $this->responseSuccess($response);
            } catch (\Exception $e) {
                return $this->responseFail($e->getMessage());
            }
        } else {
            return $this->responseFail('Permission Not Allowed!');
        }
    }
}