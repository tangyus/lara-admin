<?php

namespace App\Admin\Extensions;

use App\Model\User;
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
        return User::query()->leftJoin('accounts', 'u_account_id', 'a_id')->select(array_keys($this->columns));
    }
}