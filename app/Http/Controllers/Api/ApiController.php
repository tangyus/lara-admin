<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\model\Code;
use App\Model\PointRecord;
use App\model\Prize;
use App\Model\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    /**
     * 扫描二维码，处理券码积分
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function codeCheck(Request $request)
    {
        $code = $request->input('code');

        $user = Auth::user();
        $codeInfo = Code::leftJoin('qrcodes', 'q_id', 'c_qrcode_id')
            ->where(['c_code' => $code, 'c_used' => 0])->first();
        if ($codeInfo) {
            if (time() > strtotime($codeInfo->q_expired)) {
                return $this->responseJson([], 1000, '该兑换码已超出兑换有效期');
            }

            $qPoint = $codeInfo->q_point;
            $pointRecordAttributes = [];
            // 判断是否为会员日
            $isDayOfWeek = (new Carbon())->isDayOfWeek($codeInfo->q_member_date);
            $point = $isDayOfWeek ? ($qPoint * 2) : $qPoint;

            if (!empty($user->u_city) && $user->u_city != $codeInfo->q_city) {
                // 不同城市，重新建立用户账号
                $cityUserExists = User::where(['u_openid' => $user->u_openid, 'u_city' => $codeInfo->q_city])->first();
                if ($cityUserExists) {
                    $token = $cityUserExists->u_token;
                    Auth::setUser($cityUserExists);
                } else {
                    $token = md5(time() . uniqid());
                    $insertId = User::insertGetId([
                        'u_openid'          => $user->u_openid,
                        'u_nick'            => $user->u_nick,
                        'u_headimg'         => $user->u_headimg,
                        'u_phone'           => $user->u_phone,
                        'u_token'           => $token,
                        'u_expired'         => time() + 7000,
                        'u_current_point'   => 0,
                        'u_total_point'     => 0,
                        'u_ip'              => $request->ip()
                    ]);
                    Auth::setUser(User::find($insertId));
                }
                $user = Auth::user();
            }

            User::where('u_id', Auth::id())->update([
                'u_current_point'   => $user->u_current_point + $point,
                'u_total_point'     => $user->u_total_point + $point,
                'u_city'            => $codeInfo->q_city,
                'u_account_id'      => $codeInfo->q_account_id
            ]);

            $pointRecord = new PointRecord();
            // 是否第一次扫码
            $isFirst = PointRecord::where(['pr_uid' => Auth::id(), 'pr_prize_type' => '闪电新人礼'])->first();
            if (!$isFirst) {
                // 闪电进阶礼
                $pointRecordAttributes[] = $pointRecord->setAttributes('闪电新人礼', $qPoint.'积分奖励', 0, $qPoint, 0);
            }
            $pointRecordAttributes[] = $pointRecord->setAttributes('闪电会员礼', $qPoint.'积分奖励', 1, $qPoint, $user->u_current_point + $qPoint);
            // 判断是否为会员日
            if ($isDayOfWeek) {
                $pointRecordAttributes[] = $pointRecord->setAttributes('闪电会员礼', '双倍'.$qPoint.'积分奖励', 1, $qPoint, $user->u_current_point + $qPoint * 2);
            }
            // 增加积分记录
            PointRecord::insert($pointRecordAttributes);

            // 券码设置为已使用
            $codeInfo->c_used = 1;
            $codeInfo->save();

            return $this->responseJson([
                'double'    => $isDayOfWeek ? 1 : 0,
                'point'     => $point,
                'first'     => $isFirst ? 0 : 1,
                'diff'      => !empty($token) ? 1 : 0,
                'token'     => !empty($token) ? $token : ''
            ]);
        } else {
            return $this->responseJson([], 1000, 'Invalid Code!');
        }
    }
    
    /**
     * 用户基本信息
     * @return \Illuminate\Http\JsonResponse
     */
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

		// 活动规则

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

    /**
     * 积分记录
     * @param Request $request [type | 1 积分获取记录，2 积分使用记录]
     * @return \Illuminate\Http\JsonResponse
     */
	public function pointRecord(Request $request)
	{
		$type = $request->input('type', 1);
		$page = $request->input('page', 1);
		$pageSize = $request->input('pageSize', 10);
		$data = PointRecord::where(function ($query) use ($type) {
                    $query->where('pr_uid', Auth::id());
                    $query->where('pr_received', 1);
                    $query->where('pr_point', $type == 1 ? '>' : '<', 0);
                })
                ->orderBy('pr_updated', 'asc')
                ->select(DB::raw('pr_id, pr_point, pr_current_point, pr_created, pr_prize_type, pr_prize_name'))
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)->get();

		$list = [];
		if (count($data) > 0) {
			foreach ($data as $item) {
                $list[] = [
                    'id'            => $item['pr_id'],
					'point' 		=> $item['pr_point'],
					'currentPoint' 	=> $item['pr_current_point'],
					'date' 			=> date('Y-m-d', strtotime($item['pr_created'])),
					'type' 			=> $item['pr_prize_type'],
					'name'			=> $item['pr_prize_name']
				];
			}
		}

		return $this->responseJson(['currentPoint' => Auth::user()->u_current_point, 'list' => $list]);
	}

    /**
     * 领取闪电礼
     * @param Request $request [pointType | 礼品类型(闪电新人礼/闪电进阶礼)]
     * @return \Illuminate\Http\JsonResponse
     */
    public function pointReceive(Request $request)
    {
        $pointType = $request->input('pointType');

        $pointRecord = PointRecord::where(['pr_prize_type' => $pointType, 'pr_uid' => Auth::id(), 'pr_received' => 0])->first();
        if ($pointRecord) {
            $user = Auth::user();
            $currentPoint = $user->u_current_point + $pointRecord->pr_point;
            User::where('u_id', $user->u_id)->update([
                'u_current_point'   => $currentPoint,
                'u_total_point'     => $user->u_total_point + $pointRecord->pr_point
            ]);

            PointRecord::where('pr_id', $pointRecord->pr_id)->update([
                'pr_current_point'  => $currentPoint,
                'pr_received'       => 1
            ]);

            return $this->responseJson($pointRecord->pr_point);
        } else {
            return $this->responseJson([], 1000, 'No Type Point Received!');
        }
	}

    public function cityPrizes()
    {
        $user = Auth::user();
        $awardList = Prize::where(['p_type' => '闪电传奇礼', 'p_account_id' => $user->u_account_id])->get();
        $data = [];
        if (count($awardList) > 0) {
            foreach ($awardList as $award) {
                $data = [

                ];
            }
        }
	}

    public function lottery()
    {
        $user = Auth::user();
        try {
            $user->u_current_point = $user->u_current_point - 5;
            $user->save();

            $awardList = Prize::where(['p_type' => '闪电传奇礼', 'p_account_id' => $user->u_account_id])->get();
            $randArr = [];
            foreach ($awardList as $item) {
                $randArr[] = (int) ($item->p_rate * 100);
            }

            //概率数组的总概率精度
            $proSum = array_sum($randArr);
            //概率数组循环
            foreach ($randArr as $key => $value) {
                $rand = mt_rand(1, $proSum);
                if ($rand <= $value) {
                    $result = $key;
                    break;
                } else {
                    $proSum -= $value;
                }
            }

            $pointRecord = new PointRecord();
            $pointRecordAttribute = $pointRecord->setAttributes('闪电传奇礼', '50积分抽奖', 1, -5, $user->u_current_point);
            PointRecord::insert($pointRecordAttribute);

            return $this->responseJson([
                'awardId' => $awardList[$result]->p_id,
                'awardName' => $awardList[$result]->p_name,
            ]);
//            DB::table('lottery_res')->insert(['key' => $result]);
        } catch (\Exception $e) {
            return $this->responseJson([], 1000, '没有足够积分抽奖');
        }
	}

    public function awardList()
    {
        dd('奖品列表开发中');
	}
}