<?php

namespace App\Http\Controllers;

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

    public function responseFail($message = 'Success')
    {
        return response()->json([
            'result' 	=> 1000,
            'message' 	=> $message,
            'data' 		=> [],
        ]);
    }
}
