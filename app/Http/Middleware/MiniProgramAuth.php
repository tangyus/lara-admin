<?php

namespace App\Http\Middleware;

use App\Model\ApiLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MiniProgramAuth
{
	public function handle($request, \Closure $next, $guard = null)
	{
		if (!Auth::user()) {
			return response()->json(['message' => 'Permission Not Allowed!', 'result' => 1000]);
		}
        ApiLog::insert([
            'user_id'   => Auth::user() ? Auth::id() : null,
            'path'      => request()->path(),
            'method'    => request()->method(),
            'ip'        => request()->ip(),
            'input'     => json_encode(array_merge(request()->except(['head']), ['s3rd' => request()->header('s3rd')])),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

		return $next($request);
	}
}