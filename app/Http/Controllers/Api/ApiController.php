<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Model\PointRecord;
use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
	public function info()
	{
		$user = Auth::user();
		$userAll = User::where('u_openid', $user->u_openid)->get();
		$cities = [];
		foreach ($userAll as $item) {
			if (!empty($item->u_city)) {
				array_push($cities, $item->u_city);
			}
		}

		return $this->responseJson([
			'user' 	=> [
				'nick' 			=> $user->u_nick,
				'phone' 		=> $user->u_phone,
				'currentPoint' 	=> $user->u_current_point,
				'currentCity'  	=> $user->u_city
			],
			'cities' => $cities
		]);
	}

	public function pointRecord(Request $request)
	{
		$type = $request->input('type', 1);
		$data = PointRecord::leftJoin('prizes', 'p_id', 'pr_prize_id')
			->where(function ($query) use ($type) {
				$query->where('pr_uid', Auth::id());
				$query->where('pr_point', $type == 1 ? '>' : '<', 0);
			})
			->orderBy('pr_id', 'asc')
			->select(DB::raw('pr_point, pr_current_point, pr_created, p_type, p_name'))
			->get();

		$responseData = [];
		if (count($data) > 0) {
			foreach ($data as $item) {
				$responseData[] = [
					'point' 		=> $item['pr_point'],
					'currentPoint' 	=> $item['pr_current_point'],
					'date' 			=> date('Y-m-d', strtotime($item['pr_created'])),
					'type' 			=> $item['p_type'],
					'name'			=> $item['p_name']
				];
			}
		}

		return $this->responseJson($responseData);
	}
}