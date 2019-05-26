<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Model\User;
use EasyWeChat\Factory;
use Illuminate\Http\Request;

class AuthController extends Controller
{
	public function auth(Request $request)
	{
		$s3rd = $request->header('s3rd');
		$code = $request->input('code', '');

		$user = User::where('u_token', $s3rd)->first();
		if ($user && $user->u_expired > time()) {
			// token 未过期
			return response()->json([
				'result' 	=> 900,
				'message'	=> 'success',
				'data'		=> $user->u_token
			]);
		}

		$app = Factory::miniProgram(config('miniprogram'));
		$response = $app->auth->session($code);

		dd($response);
	}
}