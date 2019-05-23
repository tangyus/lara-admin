<?php

namespace App\Admin\Extensions;

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
        'q_city'            => '生成城市',
        'q_number'          => '生成数量',
        'q_created'         => '生成时间',
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
        } else {
            $this->columns = array_merge($this->columns, [
                'q_point'        => '扫码积分',
                'q_member_date'  => '会员日',
                'q_expired'      => '二维码有效期',
            ]);
        }
    }

    public function query()
    {
        return Qrcode::query()->leftJoin('accounts', 'q_account_id', 'a_id')->select(array_keys($this->columns));
    }
}