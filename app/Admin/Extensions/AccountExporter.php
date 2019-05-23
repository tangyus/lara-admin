<?php

namespace App\Admin\Extensions;

use App\model\Account;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class AccountExporter extends ExcelExporter implements WithStrictNullComparison
{
    use Exportable;

    protected $fileName = '区域账号.csv';

    protected $columns = [
        'a_district'        => '区域',
        'a_city'            => '城市',
        'a_manager'         => '区域负责人姓名',
        'a_manager_phone'   => '区域负责人手机号',
        'a_account'         => '区域账号',
        'a_password'        => '密码',
        'a_state'           => '是否停用(0/否、1/是)',
        'a_created'         => '创建时间',
        'a_updated'         => '修改时间',
    ];

    public function query()
    {
        return Account::query()->select(array_keys($this->columns));
    }
}