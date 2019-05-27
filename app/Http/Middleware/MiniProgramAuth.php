<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;

class MiniProgramAuth
{
	public function handle($request, \Closure $next, $guard = null)
	{
		if (!Auth::user()) {
			return response()->json(['message' => 'Permission Not Allowed!', 'result' => 1000]);
		}

		return $next($request);
	}
}