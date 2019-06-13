<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\model\Prize;
use App\model\Shop;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ActExporter extends ExcelExporter implements WithStrictNullComparison
{
    use Exportable;

    protected $fileName = '活动数据.csv';

    protected $columns = [
        'a_district'        => '区域',
        'a_city'            => '城市',
        'a_manager'         => '区域负责人姓名',
        'a_manager_phone'   => '区域负责人电话',
        'p_type'            => '礼品类型',
        'p_name'            => '礼品名称',
        'p_number'          => '礼品设置总量',
        'p_receive_number'  => '礼品发放量',
        'p_used_number'     => '礼品领取/使用量',
        'p_number - p_used_number'     => '礼品剩余量',
    ];

    public function query()
    {
        return Prize::query()->leftJoin('accounts', 'p_account_id', 'a_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('p_account_id', $account->a_id);
				}
                foreach (request()->input() as $key => $value) {
                    if (!empty($value) && !in_array($key, ['_pjax', '_export_', 'per_page'])) {
                        $query->where($key, $value);
                    }
                }
			})
			->select(DB::raw(implode(',', array_keys($this->columns))));
    }
}