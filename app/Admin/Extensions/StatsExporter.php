<?php

namespace App\Admin\Extensions;

use App\model\Account;
use App\Model\Stats;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;

class StatsExporter extends ExcelExporter
{
    use Exportable;

    protected $fileName = '统计.csv';

    protected $columns = [
        '类型', '日期', '值'
    ];

    public function query()
    {
        return Stats::select(DB::raw("s_type, from_unixtime(s_time, '%Y-%m-%d'), count(s_id) as count"))
            ->where(function ($query) {
                if (Admin::user()->isRole('市场人员')) {
                    // 修改数据来源
                    $account = Account::where('a_account', Admin::user()->username)->first();
                    $query->where('s_account_id', $account->a_id);
                } elseif (request()->get('s_account_id')) {
                    $query->where('s_account_id', request()->get('s_account_id'));
                }
                if (request()->get('s_time')) {
                    $times = request()->get('s_time');
                    $query->whereBetween('s_time', [
                        $times['start'] ? strtotime($times['start']) : strtotime('-1 day'),
                        $times['end'] ? strtotime($times['end']) : strtotime('+1 day')
                    ]);
                }
            })
            ->orderBy('s_type', 'asc')
            ->orderBy('s_time', 'asc')
            ->groupBy('s_time')
            ->groupBy('s_type');
    }
}