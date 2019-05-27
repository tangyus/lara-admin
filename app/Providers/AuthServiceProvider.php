<?php

namespace App\Providers;

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

        $where['u_token'] = request()->header('s3rd', '');
        if (!empty(request()->header('city', ''))) {
			// 城市切换
			$where['u_city'] = request()->header('city');
		}

		$user = User::where($where)->first();
        if ($user) {
			Auth::setUser($user);
		}
    }
}
