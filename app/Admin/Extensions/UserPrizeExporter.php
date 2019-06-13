<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\Model\UserPrize;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class UserPrizeExporter extends ExcelExporter implements WithStrictNullComparison
{
    use Exportable;

    protected $fileName = '用户礼品.csv';

    protected $columns = [
        'u_city'        => '城市',
        'u_nick'        => '昵称',
        'u_phone'       => '手机号',
        'p_name'        => '奖品名称',
        'p_type'   		=> '奖品类型',
        'up_name'   	=> '姓名',
        'up_phone'   	=> '电话',
        'up_address'   	=> '地址',
        'up_size'   	=> '尺码',
        'up_idcard'   	=> '身份证号',
        'up_number'   	=> '快递单号',
        'up_received' 	=> '是否核销(0/否、1/是)',
        's_number'      => '核销门店编号',
        's_name'        => '核销门店名称',
        's_address'     => '核销门店地址',
        'up_created'   	=> '中奖时间',
        'up_updated'    => '核销时间',
    ];

    public function query()
    {
        return UserPrize::query()->leftJoin('users', 'up_uid', 'u_id')
			->leftJoin('prizes', 'up_prize_id', 'p_id')
			->leftJoin('shops', 'up_shop_id', 's_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('u_account_id', $account->a_id);
				}
				foreach (request()->input() as $key => $value) {
                    if (!empty($value) && !in_array($key, ['_pjax', '_export_', 'per_page'])) {
                        $query->where($key, $value);
                    }
                }
			})
			->select(array_keys($this->columns));
    }
}