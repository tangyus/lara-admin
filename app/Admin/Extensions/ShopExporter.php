<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\model\Shop;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ShopExporter extends ExcelExporter implements WithStrictNullComparison
{
    use Exportable;

    protected $fileName = '区域门店.csv';

    protected $columns = [
        's_number'          => '门店序号',
        's_name'            => '门店名称',
        's_phone'           => '门店联系电话',
        's_manager'         => '门店负责人姓名',
        's_manager_phone'   => '门店负责人电话',
        's_state'           => '是否停用(0/否、1/是)',
        's_password'        => '门店核销密码',
        's_created'         => '创建时间',
        's_updated'         => '修改时间',
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
        return Shop::query()->leftJoin('accounts', 's_account_id', 'a_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('s_account_id', $account->a_id);
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