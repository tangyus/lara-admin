<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\model\Prize;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class PrizeExporter extends ExcelExporter implements WithStrictNullComparison
{
    use Exportable;

    protected $fileName = '区域礼品.csv';

    protected $columns = [
        'p_type'            => '礼品类型',
        'p_name'            => '礼品名称',
        'p_point'           => '兑换所需积分',
        'p_rate'            => '中奖概率(%)',
        'p_number'          => '礼品数量',
        'p_state'           => '是否停用(0/否、1/是)',
        'p_created'         => '创建时间',
        'p_updated'         => '修改时间',
    ];

    public function __construct(Grid $grid = null)
    {
        parent::__construct($grid);
        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $this->columns = array_merge([
                'a_district'        => '区域',
                'a_city'            => '城市',
                'a_manager'         => '区域负责人姓名',
                'a_manager_phone'   => '区域负责人电话',
            ], $this->columns);
        }
    }

    public function query()
    {
        return Prize::query()
			->leftJoin('accounts', 'p_account_id', 'a_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('p_account_id', $account->a_id);
				}
			})
			->select(array_keys($this->columns));
    }
}