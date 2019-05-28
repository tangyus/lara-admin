<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\model\Code;
use App\Model\PointRecord;
use App\model\Prize;
use App\Model\User;
use App\Model\UserPrize;
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
            ->where(['c_code' => $code, 'c_used' => 0])
            ->first();
        if ($codeInfo) {
            if (time() > strtotime($codeInfo->q_expired)) {
                return $this->responseFail('Code Is Expired!');
            }

            DB::beginTransaction();
            try {
                $qPoint = $codeInfo->q_point;
                $attributes = [];
                // 判断是否为会员日
                $isDayOfWeek = (new Carbon())->isDayOfWeek($codeInfo->q_member_date);
                $point = $isDayOfWeek ? ($qPoint * 2) : $qPoint;

                $codeCity = $codeInfo->q_city;
                if (!empty($user->u_city) && $user->u_city != $codeCity) {
                    // 不同城市，重新建立用户账号
                    $cityUserExists = User::where(['u_openid' => $user->u_openid, 'u_city' => $codeCity])->first();
                    if ($cityUserExists) {
                        $token = $cityUserExists->u_token;
                        Auth::setUser($cityUserExists);
                    } else {
                        $token = sha1(time() . uniqid());
                        Auth::setUser(User::create([
                            'u_openid'          => $user->u_openid,
                            'u_nick'            => $user->u_nick,
                            'u_headimg'         => $user->u_headimg,
                            'u_phone'           => $user->u_phone,
                            'u_token'           => $token,
                            'u_expired'         => time() + 7000,
                            'u_current_point'   => 0,
                            'u_total_point'     => 0,
                            'u_ip'              => $request->ip()
                        ]));
                    }
                    $user = Auth::user();
                }

                User::where('u_id', Auth::id())->update([
                    'u_current_point'   => $user->u_current_point + $point,
                    'u_total_point'     => $user->u_total_point + $point,
                    'u_city'            => $codeCity,
                    'u_account_id'      => $codeInfo->q_account_id
                ]);

                $pointRecord = new PointRecord();
                // 是否第一次扫码
                $isFirst = PointRecord::where(['pr_uid' => Auth::id(), 'pr_prize_type' => '闪电新人礼'])->first();
                if (!$isFirst) {
                    // 闪电进阶礼
                    $attributes[] = $pointRecord->setAttributes('闪电新人礼', $qPoint.'积分奖励', 0, $qPoint, 0);
                }
                $attributes[] = $pointRecord->setAttributes('闪电会员礼', $qPoint.'积分奖励', 1, $qPoint, $user->u_current_point + $qPoint);
                // 判断是否为会员日
                if ($isDayOfWeek) {
                    $attributes[] = $pointRecord->setAttributes('闪电会员礼', '双倍'.$qPoint.'积分奖励', 1, $qPoint, $user->u_current_point + $qPoint * 2);
                }
                // 增加积分记录
                PointRecord::insert($attributes);

                // 券码设置为已使用
                $codeInfo->c_used = 1;
                $codeInfo->save();

                DB::commit();
                return $this->responseSuccess([
                    'double'    => $isDayOfWeek ? 1 : 0,
                    'point'     => $point,
                    'first'     => $isFirst ? 0 : 1,
                    'diff'      => !empty($token) ? 1 : 0,
                    'token'     => !empty($token) ? $token : ''
                ]);
            } catch (\Exception $e) {
                return $this->responseFail($e->getMessage());
            }
        } else {
            return $this->responseFail('Invalid Code!');
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


        $pointRecord = PointRecord::where(['pr_prize_type' => '闪电新人礼', 'pr_received' => 0])->first();

		return $this->responseSuccess([
			'user' 	=> [
				'nick' 			=> $user->u_nick,
				'phone' 		=> $user->u_phone,
				'currentPoint' 	=> $user->u_current_point,
				'currentCity'  	=> $user->u_city,
                'newPrize'      => $pointRecord ? 1 : 0,
                'advancePrize'  => 0,
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

		return $this->responseSuccess(['currentPoint' => Auth::user()->u_current_point, 'list' => $list]);
	}

    /**
     * 领取闪电礼
     * @param Request $request [pointType | 礼品类型(闪电新人礼/闪电进阶礼)]
     * @return \Illuminate\Http\JsonResponse
     */
    public function pointReceive(Request $request)
    {
        $pointType = $request->input('receiveType');

        $pointRecord = PointRecord::where(['pr_prize_type' => $pointType, 'pr_uid' => Auth::id(), 'pr_received' => 0])->first();
        if ($pointRecord) {
            $user = Auth::user();

            DB::beginTransaction();
            try {
                $currentPoint = $user->u_current_point + $pointRecord->pr_point;
                User::where('u_id', $user->u_id)->update([
                    'u_current_point'   => $currentPoint,
                    'u_total_point'     => $user->u_total_point + $pointRecord->pr_point
                ]);

                PointRecord::where('pr_id', $pointRecord->pr_id)->update([
                    'pr_current_point'  => $currentPoint,
                    'pr_received'       => 1
                ]);

                // 增加奖品领取量
                Prize::where(['p_account_id' => $user->u_account_id, 'p_type' => $pointType])->increment('p_receive_number');
                return $this->responseSuccess($pointRecord->pr_point);
            } catch (\Exception $e) {
                return $this->responseFail($e->getMessage());
            }
        } else {
            return $this->responseFail('No Type Point Receive!');
        }
	}

    /**
     * 获取当前城市的抽奖奖品列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function lotteryPrizes()
    {
        $user = Auth::user();
        $prizeList = Prize::where(['p_type' => '闪电传奇礼', 'p_account_id' => $user->u_account_id])->limit(8)->get();

        $data = [];
        if (count($prizeList) > 0) {
            foreach ($prizeList as $key => $prize) {
                $data[$key + 1] = [
                    'aid'       => $prize->p_id,
                    'name'      => $prize->p_name,
                    'thumb'     => $prize->p_thumb,
                    'img'       => $prize->p_img,
                    'isShoe'    => strpos($prize->p_name, '跑鞋') ? 1 : 0,
                    'isCoupon'  => strpos($prize->p_name, '优惠券') ? 1 : 0
                 ];
            }
            $data[-1] = [
                'aid'       => 0,
                'name'      => '立即抽奖',
                'thumb'     => '',
                'img'       => '',
                'isShoe'    => 0,
                'isCoupon'  => 0
            ];
        }

        return $this->responseSuccess($data);
	}

    /**
     * 抽奖
     * @return \Illuminate\Http\JsonResponse
     */
    public function lottery()
    {
        $user = Auth::user();
        DB::beginTransaction();
        try {
            $user->u_current_point = $user->u_current_point - 5;
            $user->save();

            $prizeList = Prize::where(['p_type' => '闪电传奇礼', 'p_account_id' => $user->u_account_id])->get();
            $randArr = [];
            foreach ($prizeList as $item) {
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

            // 扣去用户积分
            $pointRecord = new PointRecord();
            $pointRecordAttribute = $pointRecord->setAttributes('闪电传奇礼', '50积分抽奖', 1, -5, $user->u_current_point);
            PointRecord::insert($pointRecordAttribute);

            if (isset($result)) {
                if (strpos($prizeList[$result]->p_name, '30优惠券')) {
                    $couponCode = DB::table('coupon_codes')->where(['cc_type' => 1, 'cc_used' => 0])->first();
                } elseif (strpos($prizeList[$result]->p_name, '50优惠券')) {
                    $couponCode = DB::table('coupon_codes')->where(['cc_type' => 2, 'cc_used' => 0])->first();
                }

                // 插入用户中奖数据
                $userPrize = UserPrize::create([
                    'up_uid'        => $user->u_id,
                    'up_type'       => '抽奖',
                    'up_prize_id'   => $prizeList[$result]->p_id,
                    'up_coupon_code'=> isset($couponCode) ? $couponCode->cc_code : null
                ]);
                // 修改优惠券码为已使用
                if (isset($couponCode)) {
                    DB::table('coupon_codes')->where('cc_id', $couponCode->cc_id)->update(['cc_used' => 1, 'cc_up_id' => $userPrize->up_id]);
                }

                Prize::where(['p_account_id' => $user->u_account_id, 'p_type' => '闪电传奇礼'])->increment('p_receive_number');
            }

            DB::commit();
            return $this->responseSuccess([
                'prizeId'   => isset($result) ? $prizeList[$result]->p_id : 0,
                'prizeName' => isset($result) ? $prizeList[$result]->p_name : '未中奖',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseFail('No Enough Point To Lottery!');
        }
	}

    /**
     * 用户奖品列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLotteryPrizeList(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $userPrize = UserPrize::leftJoin('prizes', 'p_id', 'up_prize_id')
            ->where(['up_uid' => Auth::id(), 'up_type' => '抽奖'])
            ->select(DB::raw('p_name as name, p_thumb as thumb, p_img as img, up_received as received, up_coupon_code as couponCode, up_id as id'))
            ->orderBy('up_id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $data = [];
        if (count($userPrize) > 0) {
            foreach ($userPrize as $key => $prize) {
                $data[$key] = $prize;
                $data[$key]['isShoe'] = strpos($prize->name, '跑鞋') ? 1 : 0;
                $data[$key]['isCoupon'] = strpos($prize->name, '优惠券') ? 1 : 0;
            }
        }
        return $this->responseSuccess($userPrize);
	}

    /**
     * 保存中奖信息地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeUserPrizeAddress(Request $request)
    {
        $input = $request->input();

        $userPrize = UserPrize::where(['up_id' => $input['id'], 'up_uid' => Auth::id()])->first();
        if ($userPrize) {
            $userPrize->up_name = $input['name'];
            $userPrize->up_phone = $input['phone'];
            $userPrize->up_address = $input['address'];
            $userPrize->up_size = $input['size'];
            $userPrize->up_idcard = $input['idcard'];
            $userPrize->save();

            return $this->responseSuccess(1);
        } else {
            return $this->responseFail('[ID] Prize Not Found!');
        }
	}

    /**
     * 获取当前城市的兑奖奖品列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function exchangePrizes()
    {
        $user = Auth::user();
        $prizeList = Prize::where(['p_type' => '闪电兑换礼', 'p_account_id' => $user->u_account_id])->limit(8)->get();

        $data = [];
        if (count($prizeList) > 0) {
            foreach ($prizeList as $key => $prize) {
                $data[] = [
                    'id'        => $prize->p_id,
                    'name'      => $prize->p_name,
                    'thumb'     => $prize->p_thumb,
                    'img'       => $prize->p_img,
                    'point'     => $prize->p_point
                ];
            }
        }

        return $this->responseSuccess($data);
    }

    /**
     * 兑换奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exchange(Request $request)
    {
        $user = Auth::user();
        $id = $request->input('id');
        $prize = Prize::where(['p_id' => $id, 'p_type' => '闪电兑换礼'])->first();
        if (!$prize) {
            return $this->responseFail('[ID] Prize Not Found!');
        }
        if ($user->u_current_point < $prize->p_point) {
            return $this->responseFail('No Enough Point To Exchange!');
        }

        DB::beginTransaction();
        try {
            $user->u_current_point = $user->u_current_point - $prize->p_point;
            $user->save();

            // 扣去用户积分
            $pointRecord = new PointRecord();
            $pointRecordAttribute = $pointRecord->setAttributes('闪电兑换礼', $prize->p_point.'积分兑换', 1, $prize->p_point, $user->u_current_point);
            PointRecord::insert($pointRecordAttribute);

            // 插入用户中奖数据
            UserPrize::create([
                'up_uid'        => $user->u_id,
                'up_type'       => '兑换',
                'up_prize_id'   => $prize->p_id,
            ]);

            Prize::where(['p_account_id' => $user->u_account_id, 'p_type' => '闪电兑换礼'])->increment('p_receive_number');

            DB::commit();
            return $this->responseSuccess(1);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->responseFail($e->getMessage());
        }
    }

    /**
     * 用户兑奖奖品列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userExchangePrizeList(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $userPrize = UserPrize::leftJoin('prizes', 'p_id', 'up_prize_id')
            ->where(['up_uid' => Auth::id(), 'up_type' => '兑换'])
            ->select(DB::raw('p_name as name, p_thumb as thumb, p_img as img, up_received as received, up_id as id'))
            ->orderBy('up_id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $data = [];
        if (count($userPrize) > 0) {
            foreach ($userPrize as $key => $prize) {
                $data[$key] = $prize;
            }
        }
        return $this->responseSuccess($userPrize);
    }
}