<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\Model\User;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;

class UserExporter extends ExcelExporter
{
    use Exportable;

    protected $fileName = '用户列表.csv';

    protected $columns = [
        'a_district'        => '区域',
        'u_openid'          => 'openID',
        'u_nick'            => '昵称',
        'u_phone'           => '用户手机号',
        'u_current_point'   => '当前积分',
        'u_total_point'     => '总积分',
        'u_ip'              => '登录IP',
        'u_created'         => '登录时间',
    ];

    public function query()
    {
        return User::query()
			->leftJoin('accounts', 'u_account_id', 'a_id')
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