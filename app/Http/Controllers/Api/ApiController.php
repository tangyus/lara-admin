<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\model\Account;
use App\model\Code;
use App\Model\PointRecord;
use App\model\Prize;
use App\Model\Rule;
use App\model\Shop;
use App\Model\Stats;
use App\Model\User;
use App\Model\UserPrize;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    /**
     * PV
     */
    public function pv()
    {
        $user = User::where('u_token', request()->header('s3rd', ''))->first();
        Stats::insert([
            's_time'        => strtotime('today'),
            's_type'        => 'pv',
            's_account_id'  => $user ? $user->u_account_id : null,
            's_uid'         => $user ? $user->u_id : null
        ]);

        return $this->responseSuccess(1);
    }

    /**
     * 扫描二维码，处理券码积分
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function codeCheck(Request $request)
    {
        $code = $request->input('code');
        $nick = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $request->input('nick')
        );
        $head = $request->input('head');
        $phone = $request->input('phone');

        try {
            $user = Auth::user();
            $codeInfo = Code::leftJoin('qrcodes', 'q_id', 'c_qrcode_id')
                ->leftJoin('accounts', 'a_id', 'q_account_id')
                ->where(['c_code' => $code])
                ->first();
            if (!$codeInfo || $codeInfo->c_used != 0) {
                if ($codeInfo && empty($user->u_account_id)) {
                    User::where('u_id', Auth::id())->update([
                        'u_city'            => $codeInfo->q_city,
                        'u_account_id'      => $codeInfo->q_account_id,
                        'u_nick'            => $nick,
                        'u_headimg'         => $head,
                        'u_phone'           => $phone
                    ]);
                }
                return $this->responseFail('Invalid Code!');
            }

            // 判断扫码次数限制
            if (!empty($codeInfo->a_scan_times)) {
                $userOfCodeCity = User::where(['u_account_id' => $codeInfo->q_account_id, 'u_openid' => $user->u_openid])->first();
                if ($userOfCodeCity) {
                    $count = PointRecord::where(['pr_uid' => $userOfCodeCity->u_id, 'pr_prize_name' => '产品购买'])
                        ->whereDay('pr_created', date('d'))
                        ->count();
                    if ($codeInfo->a_scan_times - $count <= 0) {
                        return $this->responseDefine(1002, 'Scan times limit!');
                    }
                }
            }
            if (time() > strtotime($codeInfo->q_expired)) {
                if (empty($user->u_account_id)) {
                    User::where('u_id', Auth::id())->update([
                        'u_city'            => $codeInfo->q_city,
                        'u_account_id'      => $codeInfo->q_account_id,
                        'u_nick'            => $nick,
                        'u_headimg'         => $head,
                        'u_phone'           => $phone
                    ]);
                }
                return $this->responseDefine(1001, 'Code Is Expired!');
            }

            DB::beginTransaction();
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
                    Stats::insert([
                        's_time'        => strtotime('today'),
                        's_type'        => 'uv',
                        's_account_id'  => $codeInfo->q_account_id,
                        's_uid'         => Auth::id()
                    ]);
                }
                $user = Auth::user();
            }

            User::where('u_id', Auth::id())->update([
                'u_current_point'   => $user->u_current_point + $point,
                'u_total_point'     => $user->u_total_point + $point,
                'u_city'            => $codeCity,
                'u_account_id'      => $codeInfo->q_account_id,
                'u_nick'            => $nick,
                'u_headimg'         => $head,
                'u_phone'           => $phone
            ]);

            $pointRecord = new PointRecord();
            // 是否第一次扫码
            $isFirst = PointRecord::where(['pr_uid' => Auth::id(), 'pr_prize_type' => Prize::NEW_PRIZE])->first();
            if (!$isFirst) {
                // 闪电新人礼
                $attributes[] = $pointRecord->setAttributes(Prize::NEW_PRIZE, Prize::NEW_PRIZE, 0, $qPoint, 0);
            }
            $attributes[] = $pointRecord->setAttributes(Prize::MEMBER_PRIZE, '产品购买', 1, $qPoint, $user->u_current_point + $qPoint);
            // 判断是否为会员日
            if ($isDayOfWeek) {
                Prize::where(['p_account_id' => $codeInfo->q_account_id, 'p_type' => Prize::MEMBER_PRIZE])->increment('p_receive_number');
                $attributes[] = $pointRecord->setAttributes(Prize::MEMBER_PRIZE, Prize::MEMBER_PRIZE, 1, $qPoint, $user->u_current_point + $qPoint * 2);
            }
            // 增加积分记录
            PointRecord::insert($attributes);

            // 券码设置为已使用
            $codeInfo->c_used = 1;
            $codeInfo->c_uid = $user->u_id;
            $codeInfo->save();

            DB::commit();
            return $this->responseSuccess([
                'double'    => $isDayOfWeek ? 1 : 0,
                'point'     => $isDayOfWeek ? $point / 2 : $point,
                'first'     => $isFirst ? 0 : 1,
                'diff'      => !empty($token) ? 1 : 0,
                'token'     => !empty($token) ? $token : ''
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->responseFail($e->getMessage());
        }
    }
    
    /**
     * 用户基本信息
     * @return \Illuminate\Http\JsonResponse
     */
	public function info()
	{
	    try {
            $user = Auth::user();
            $userAll = User::where('u_openid', $user->u_openid)->get();
            $cities = [];
            if (count($userAll) > 1) {
                foreach ($userAll as $item) {
                    if (!empty($item->u_city)) {
                        array_push($cities, $item->u_city);
                    }
                }
            }
            Stats::where('s_uid', $user->u_id)->whereNull('s_account_id')->update([
                's_account_id'  => $user->u_account_id,
            ]);

            // 活动规则
            $rule = Rule::first();

            // 新人奖
            $newPrize = PointRecord::where(['pr_prize_type' => Prize::NEW_PRIZE, 'pr_uid' => $user->u_id])->first();
            if (!$newPrize) {
                $newPrize = 0;
            } else {
                $newPrize = ($newPrize->pr_received == 0) ? 1 : 2;
            }

            // 进阶奖
            $advancePrizeData = PointRecord::where(['pr_prize_type' => Prize::ADVANCE_PRIZE, 'pr_uid' => $user->u_id])->first();
            $dateArr = [];
            if (!$advancePrizeData) {
                $advancePrize = 0;
                // 判断是否有进阶礼资格
                $firstDay = strtotime('today') + 86400;
                $lastDay = $firstDay - 86400 * 14;
                $lastDate = date('Y-m-d H:i:s', $lastDay);
                $firstDate = date('Y-m-d H:i:s', $firstDay);
                $dateArr = [];
                PointRecord::where(['pr_prize_type' => Prize::MEMBER_PRIZE, 'pr_uid' => $user->u_id])
                    ->whereRaw("pr_created > '$lastDate' and pr_created < '$firstDate'")
                    ->orderBy('pr_created', 'asc')
                    ->get()
                    ->map(function ($item) use (&$dateArr) {
                        $dateKey = date('Y-m-d', strtotime($item->pr_created));
                        if (!in_array($dateKey, $dateArr)) {
                            $dateArr[] = $dateKey;
                        }
                    });
                if (count($dateArr) >= 7) {
                    // 任意2周时间段内扫码7天，获得进阶礼
                    PointRecord::insert((new PointRecord())->setAttributes(Prize::ADVANCE_PRIZE, Prize::ADVANCE_PRIZE, 0, 35, $user->u_current_point + 35));
                    $advancePrize = 1;
                }
            } else {
                $advancePrize = ($advancePrizeData->pr_received == 0) ? 1 : 2;
            }
            $account = Account::find($user->u_account_id);
            $count = DB::table('lottery_logs')->where(['ll_uid' => $user->u_id, 'll_day' => strtotime('today')])->count();

            return $this->responseSuccess([
                'user' 	=> [
                    'nick' 			=> $user->u_nick,
                    'headimg' 		=> $user->u_headimg,
                    'phone' 		=> $user->u_phone,
                    'currentPoint' 	=> $user->u_current_point,
                    'currentCity'  	=> $user->u_city,
                    'newPrize'      => $newPrize,
                    'advancePrize'  => $advancePrize,
                    'dateArr'       => $dateArr,
                    'mainRuleImg'   => $rule ? $rule->r_act_img : '',
                    'pointRuleImg'  => $rule ? $rule->r_rule_img : '',
                    'legendRuleImg' => $rule ? $rule->r_point_img : '',
                    'hasScanCode'   => !empty($user->u_city) ? 1 : 0,
                    'todayCanLotteryCount' => ($account && $account->a_lottery_times - $count >= 0)? ($account->a_lottery_times - $count) : 0,
                    'hotLine'       => $account ? $account->a_hot_line : '',
                    'workTime'      => $account ? $account->a_work_time : '',
                    'sponsor'       => $account ? $account->a_sponsor : ''
                ],
                'cities' => $cities
            ]);
        } catch (\Exception $e) {
            return $this->responseFail($e->getMessage());
        }

	}

    /**
     * 获取当月签到记录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signDate(Request $request)
    {
        $user = Auth::user();

        $dateArr = [];
        $month = $request->input('month', date('n'));
        PointRecord::where(['pr_prize_type' => Prize::MEMBER_PRIZE, 'pr_uid' => $user->u_id])
            ->whereMonth('pr_created', $month)
            ->orderBy('pr_created', 'asc')
            ->get()
            ->map(function ($item) use (&$dateArr) {
                $dateKey = date('Y-m-d', strtotime($item->pr_created));
                if (!in_array($dateKey, $dateArr)) {
                    $dateArr[] = $dateKey;
                }
            });
        return $this->responseSuccess($dateArr);
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

		$data = [];
		PointRecord::where(function ($query) use ($type) {
				$query->where('pr_uid', Auth::id());
				$query->where('pr_received', 1);
				$query->where('pr_point', ($type == 1) ? '>' : '<', 0);
			})
			->select(DB::raw('pr_id, pr_point, pr_current_point, pr_updated, pr_prize_type, pr_prize_name'))
            ->orderBy('pr_updated', 'desc')
            ->orderBy('pr_id', 'desc')
			->offset(($page - 1) * $pageSize)
			->limit($pageSize)
			->get()
			->map(function ($item) use (&$data) {
				$data[] = [
					'id'            => $item['pr_id'],
					'point' 		=> $item['pr_point'],
					'currentPoint' 	=> $item['pr_current_point'],
					'date' 			=> date('Y-m-d', strtotime($item['pr_updated'])),
					'type' 			=> $item['pr_prize_type'],
					'name'			=> $item['pr_prize_name']
				];
			});

		return $this->responseSuccess(['currentPoint' => Auth::user()->u_current_point, 'list' => $data]);
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
        if (!$pointRecord) {
            return $this->responseFail('No Type Point Receive!');
        }

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
            DB::commit();
            return $this->responseSuccess($pointRecord->pr_point);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->responseFail($e->getMessage());
        }
	}

    /**
     * 获取当前城市的抽奖奖品列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function lotteryPrizes()
    {
        $user = Auth::user();

        $shops = Shop::where('s_account_id', $user->u_account_id)
            ->select(DB::raw('s_id, s_city, s_number, s_name, s_phone, s_address'))
            ->get();
		$data = [];
        Prize::where(['p_type' => Prize::LEGEND_PRIZE, 'p_account_id' => $user->u_account_id])
			->limit(8)
			->get()
			->map(function ($prize, $key) use (&$data, $shops) {
				$data[$key + 1] = [
					'aid'           => $prize->p_id,
					'name'          => $prize->p_name,
					'img'           => $prize->p_img,
					'needSaveInfo'  => ($prize->p_type_id == 7) ? 1 : 0,
					'isCoupon'      => (in_array($prize->p_type_id, [5, 6])) ? 1 : 0,
					'applyShop'	    => $prize->p_apply_shop,
					'applyCity'	    => $prize->p_apply_city,
					'rule'		    => $prize->p_rule,
					'deadline'	    => date('Y年m月d日', strtotime($prize->p_deadline)),
					'phoneNumber'	=> $prize->p_phone_number,
                    'shops'         => []
				];
                foreach ($shops as $shop) {
                    if (in_array($shop->s_id, $prize->p_apply_shop)) {
                        $data[$key + 1]['shops'][] = $shop;
                    }
                }
			});

		$data[-1] = [
			'aid'           => 0,
			'name'          => '立即抽奖',
			'thumb'         => '',
			'img'           => '',
			'needSaveInfo'  => 0,
			'isCoupon'      => 0,
			'applyCity'	    => '',
			'applyShop'	    => '',
			'rule'		    => '',
			'deadline'	    => '',
			'phoneNumber'	=> '',
            'shops'         => []
		];

        return $this->responseSuccess($data);
	}

    /**
     * 抽奖
     * @return \Illuminate\Http\JsonResponse
     */
    public function lottery()
    {
        $user = Auth::user();

        $data = [
            'name'          => '',
            'img'           => '',
            'received'      => 0,
            'code'          => '',
            'id'            => '',
            'needSaveInfo'  => '',
            'isCoupon'      => '',
        ];

        $account = Account::find($user->u_account_id);
        if ($account && !empty($account->a_lottery_times)) {
            $count = DB::table('lottery_logs')->where(['ll_uid' => $user->u_id, 'll_day' => strtotime('today')])->count();
            if ($account->a_lottery_times - $count <= 0) {
                return $this->responseDefine(1001, 'Today has lottery!');
            }
        }

        if ($user->u_current_point < 50) {
			return $this->responseSuccess(['pointIsNotEnough' => 1, 'data' => $data]);
		}
		DB::beginTransaction();
		try {
			$user->u_current_point = $user->u_current_point - 50;
			$user->save();

            DB::table('lottery_logs')->insert(['ll_uid' => $user->u_id, 'll_day' => strtotime('today'), 'll_created' => date('Y-m-d H:i:s')]);
			$prizeList = Prize::where(['p_type' => Prize::LEGEND_PRIZE, 'p_account_id' => $user->u_account_id])
                ->orderBy('p_type_id', 'asc')
                ->get();
			$randArr = [];
			foreach ($prizeList as $item) {
				$randArr[] = (int) ($item->p_rate * 100);
			}

            $result = 0; // 默认中饮品
			// 概率数组的总概率精度
			$proSum = array_sum($randArr);
			// 随机中奖
			foreach ($randArr as $key => $value) {
				$rand = mt_rand(1, $proSum);
				if ($rand <= $value) {
					$result = $key;
					break;
				} else {
					$proSum -= $value;
				}
			}

            // 中奖奖品
            $prize = $prizeList[$result];
			if ($prize->p_type_id == 7) {
                $userPrizeOfShoe = UserPrize::where(['up_openid' => $user->u_openid, 'up_prize_type_id' => 7])->first();
                if ($userPrizeOfShoe) {
                    // 已经中过跑鞋，则默认中饮品
                    $prize = $prizeList[0];
                }
            }
            // 若奖品已抽完，则默认中饮品
            if ($prize->p_received_number >= $prize->p_number) {
                $prize = $prizeList[0];
            }

            // 优惠券发放券码
            if (in_array($prize->p_type_id, [5, 6])) {
			    $cType = ($prize->p_type_id == 5) ? 1 : 2;
                $couponCode = DB::table('coupon_codes')->where(['cc_type' => $cType, 'cc_used' => 0])->first();
            }

            // 插入用户中奖数据
            $userPrize = UserPrize::create([
                'up_uid'            => $user->u_id,
                'up_openid'         => $user->u_openid,
                'up_prize_type_id'  => $prize->p_type_id,
                'up_type'           => '抽奖',
                'up_prize_id'       => $prize->p_id,
                'up_code'           => isset($couponCode) ? $couponCode->cc_code : date('His').mt_rand(1000, 9999)
            ]);
            // 修改优惠券码为已使用
            if (isset($couponCode)) {
                DB::table('coupon_codes')->where('cc_id', $couponCode->cc_id)->update(['cc_used' => 1, 'cc_up_id' => $userPrize->up_id]);
            }

            Prize::where(['p_id' => $prize->p_id])->increment('p_receive_number');
            $data = [
                'name'          => $prize->p_name,
                'img'           => $prize->p_img,
                'received'      => 0,
                'code'          => $userPrize->up_code,
                'aid'           => $prize->p_id,
                'needSaveInfo'  => ($prize->p_type_id == 7) ? 1 : 0,
                'isCoupon' 	    => in_array($prize->p_type_id, [5, 6]) ? 1 : 0,
                'id'		    => $userPrize->up_id,
                'applyCity'	    => $prize->p_apply_city,
                'applyShop'	    => $prize->p_apply_shop,
                'rule'		    => $prize->p_rule,
                'deadline'	    => date('Y年m月d日', strtotime($prize->p_deadline)),
                'phoneNumber'	=> $prize->p_phone_number,
                'number'        => ''
            ];
            Shop::whereIn('s_id', $prize->p_apply_shop)
                ->select(DB::raw('s_id, s_city, s_number, s_name, s_phone, s_address'))
                ->get()
                ->map(function ($item) use (&$data) {
                    $data['shops'][] = $item;
                });
            $mark = $prize->p_name;
            // 扣去用户积分
            $pointRecord = new PointRecord();
            $pointRecordAttribute = $pointRecord->setAttributes(Prize::LEGEND_PRIZE, $mark, 1, -50, $user->u_current_point);
            PointRecord::insert($pointRecordAttribute);

			DB::commit();
			return $this->responseSuccess(['pointIsNotEnough' => 0, 'data' => $data]);
		} catch (\Exception $e) {
			DB::rollBack();
			return $this->responseFail($e->getMessage());
		}
	}

    /**
     * 用户奖品列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLotteryPrizeList()
    {
        $page = request()->input('page', 1);
        $pageSize = request()->input('pageSize', 10);

        $shops = Shop::where('s_account_id', Auth::user()->u_account_id)
            ->select(DB::raw('s_id, s_city, s_number, s_name, s_phone, s_address'))
            ->get();
        $data = [];
        UserPrize::leftJoin('prizes', 'p_id', 'up_prize_id')
            ->where(['up_uid' => Auth::id(), 'up_type' => '抽奖'])
            ->select(DB::raw('p_name as name, p_img as img, p_type_id as typeId, up_received as received, up_code as code, p_id as aid, up_id as id, p_apply_city as applyCity, p_apply_shop, p_rule as rule, p_deadline, p_phone_number as phoneNumber, up_number as number'))
            ->orderBy('up_id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(function ($item, $key) use (&$data, $shops) {
                $data[$key] = $item;
                $data[$key]['needSaveInfo'] = ($item->typeId == 7) ? 1 : 0;
                $data[$key]['isCoupon'] = in_array($item->typeId, [5, 6]) ? 1 : 0;

                $data[$key]['deadline'] = date('Y年m月d日', strtotime($item->p_deadline));
                $applyShopIds = explode('-', $item->p_apply_shop);
                $data[$key]['applyShop'] = $applyShopIds;
                unset($data[$key]['typeId']);

                $tempShops = [];
                foreach ($shops as $shop) {
                    if (in_array($shop->s_id, $applyShopIds)) {
                        $tempShops[] = $shop;
                    }
                }
                $data[$key]['shops'] = $tempShops;
            });

        return $this->responseSuccess($data);
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
            $userPrize->up_received = 1;
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

        $shops = Shop::where('s_account_id', $user->u_account_id)
            ->select(DB::raw('s_id, s_city, s_number, s_name, s_phone, s_address'))
            ->get();
        $data = [];
        Prize::where(['p_type' => Prize::EXCHANGE_PRIZE, 'p_account_id' => $user->u_account_id])
			->limit(8)
			->get()
			->map(function ($prize, $index) use (&$data, $shops) {
				$data[$index] = [
					'id'            => $prize->p_id,
					'name'          => $prize->p_name,
					'img'           => $prize->p_img,
					'point'         => $prize->p_point,
					'applyCity'     => $prize->p_apply_city,
					'applyShop'     => $prize->p_apply_shop,
					'rule'          => $prize->p_rule,
					'deadline'      => date('Y年m月d日', strtotime($prize->p_deadline)),
					'phoneNumber'   => $prize->p_phone_number,
                    'needSaveInfo'  => ($prize->p_type_id == 7) ? 1 : 0,
                    'isCoupon'      => in_array($prize->p_type_id, [5, 6]) ? 1 : 0,
                    'shops'         => []
				];
                foreach ($shops as $shop) {
                    if (in_array($shop->s_id, $prize->p_apply_shop)) {
                        $data[$index]['shops'][] = $shop;
                    }
                }
			});

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
        $prize = Prize::where(['p_id' => $id, 'p_type' => Prize::EXCHANGE_PRIZE])->first();
        if (!$prize) {
            return $this->responseFail('[ID] Prize Not Found!');
        }
        if ($user->u_current_point < $prize->p_point) {
            return $this->responseSuccess(['pointIsNotEnough' => 1, 'data' => []]);
        }
        if (($prize->p_number - $prize->p_used_number) <= 0) {
            return $this->responseFail('奖品已被兑换完');
        }

        DB::beginTransaction();
        try {
            $user->u_current_point = $user->u_current_point - $prize->p_point;
            $user->save();

            // 扣去用户积分
            $pointRecord = new PointRecord();
            $pointRecordAttribute = $pointRecord->setAttributes(Prize::EXCHANGE_PRIZE, $prize->p_point.'积分兑换', 1, -$prize->p_point, $user->u_current_point);
            PointRecord::insert($pointRecordAttribute);

            $codeStr = date('His').mt_rand(1000, 9999);
            // 插入用户中奖数据
            $userPrize = UserPrize::create([
                'up_uid'        => $user->u_id,
                'up_type'       => '兑换',
                'up_prize_id'   => $prize->p_id,
                'up_code'       => $codeStr
            ]);

            Prize::where(['p_id' => $id])->increment('p_receive_number');

            DB::commit();
            $data = [
                'id'            => $userPrize->up_id,
                'name'          => $prize->p_name,
                'img'           => $prize->p_img,
                'point'         => $prize->p_point,
                'applyCity'     => $prize->p_apply_city,
                'applyShop'     => $prize->p_apply_shop,
                'rule'          => $prize->p_rule,
                'deadline'      => date('Y年m月d日', strtotime($prize->p_deadline)),
                'phoneNumber'   => $prize->p_phone_number,
                'code'          => $codeStr,
                'received'      => 0,
                'needSaveInfo'  => ($prize->p_type_id == 7) ? 1 : 0,
                'isCoupon'      => in_array($prize->p_type_id, [5, 6]) ? 1 : 0,
                'number'        => ''
            ];
            Shop::whereIn('s_id', $prize->p_apply_shop)
                ->select(DB::raw('s_id, s_city, s_number, s_name, s_phone, s_address'))
                ->get()
                ->map(function ($item) use (&$data) {
                    $data['shops'][] = $item;
                });
			return $this->responseSuccess(['pointIsNotEnough' => 0, 'data' => $data]);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->responseFail($e->getMessage());
        }
    }

    /**
     * 取消兑换
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelExchange()
    {
        $id = \request()->input('id');
        $userPrize = UserPrize::where(['up_id' => $id, 'up_uid' => Auth::id()])->first();
        if ($userPrize->up_received != 0) {
            return $this->responseFail('该礼品已核销，无法取消');
        }

        DB::beginTransaction();
        $user = Auth::user();
        try {
            $prize = Prize::where('p_id', $userPrize->up_prize_id)->first();
            $prize->p_receive_number = $prize->p_receive_number - 1;
            $prize->save();

            // 增加积分
            $user->u_current_point = $user->u_current_point + $prize->p_point;
            $user->save();

            $userPrize->delete();

            $pointRecord = new PointRecord();
            $attribute = $pointRecord->setAttributes(Prize::EXCHANGE_PRIZE, '闪电积分礼-取消兑换', 1, $prize->p_point, $user->u_current_point);
            $pointRecord->insert($attribute);

            DB::commit();
            return $this->responseSuccess($user->u_current_point);
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

        $shops = Shop::where('s_account_id', Auth::user()->u_account_id)
            ->select(DB::raw('s_id, s_city, s_number, s_name, s_phone, s_address'))
            ->get();
        $data = [];
        UserPrize::leftJoin('prizes', 'p_id', 'up_prize_id')
            ->where(['up_uid' => Auth::id(), 'up_type' => '兑换'])
            ->select(DB::raw('p_name as name, p_img as img, p_type_id as typeId, p_point as point, p_apply_city as applyCity, p_apply_shop, p_rule as rule, p_deadline, p_phone_number as phoneNumber, up_received as received, up_id as id, up_code as code, up_number as number'))
            ->orderBy('up_id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(function ($item, $key) use (&$data, $shops) {
                $data[$key] = $item;
                $data[$key]['needSaveInfo'] = ($item->typeId == 7) ? 1 : 0;
                $data[$key]['isCoupon'] = in_array($item->typeId, [5, 6]) ? 1 : 0;
                $data[$key]['deadline'] = date('Y年m月d日', strtotime($item->p_deadline));

                $applyShopIds = explode('-', $item->p_apply_shop);
                $data[$key]['applyShop'] = $applyShopIds;
                unset($data[$key]['typeId']);

                $tempShops = [];
                foreach ($shops as $shop) {
                    if (in_array($shop->s_id, $applyShopIds)) {
                        $tempShops[] = $shop;
                    }
                }
                $data[$key]['shops'] = $tempShops;
            });

        return $this->responseSuccess($data);
    }
}