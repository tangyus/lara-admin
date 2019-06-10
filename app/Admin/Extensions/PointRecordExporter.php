<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\Model\PointRecord;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;

class PointRecordExporter extends ExcelExporter
{
    use Exportable;

    protected $fileName = '积分记录.csv';

    protected $columns = [
        'u_openid'          => 'openID',
        'u_nick'            => '昵称',
        'u_phone'           => '用户手机号',
        'pr_prize_type'     => '礼品类型',
        'pr_prize_name'     => '礼品名称',
        'pr_created'        => '时间',
        'pr_point'          => '获得/消耗积分',
        'pr_current_point'  => '当前用户积分',
    ];

    public function query()
    {
        return PointRecord::query()
			->leftJoin('users', 'pr_uid', 'u_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('u_account_id', $account->a_id);
				}
			})
			->select(array_keys($this->columns));
    }
}