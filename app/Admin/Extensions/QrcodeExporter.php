<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\model\Qrcode;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;

class QrcodeExporter extends ExcelExporter
{
    use Exportable;

    protected $fileName = '二维码生成记录.csv';

    protected $columns = [
        'a_district'        => '区域',
        'a_city'            => '城市',
        'a_manager'         => '区域负责人姓名',
        'a_manager_phone'   => '区域负责人电话',
        'q_city'            => '生成城市',
        'q_number'          => '生成数量',
        'q_point'           => '扫码积分',
        'q_member_date'     => '会员日',
        'q_expired'         => '二维码扫描截止日期',
    ];

    public function query()
    {
        return Qrcode::query()->leftJoin('accounts', 'q_account_id', 'a_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('q_account_id', $account->a_id);
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