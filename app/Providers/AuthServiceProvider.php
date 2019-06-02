<?php

namespace App\Providers;

use App\model\Shop;
use App\Model\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

		$user = null;
        if (!empty(request()->input('city', ''))) {
        	$currentUser = User::where('u_token', request()->header('s3rd'))->first();
        	if ($currentUser) {
				// 城市切换
				$user = User::where(['u_openid' => $currentUser->u_openid, 'u_city' => request()->input('city')])->first();
				$user->u_token = sha1($user->u_sessionkey . uniqid() . time());
				$user->save();
			}
        } else {
			$where['u_token'] = request()->header('s3rd');
			$user = User::where($where)->first();
		}

        if ($user) {
            Auth::setUser($user);
        }
    }
}
