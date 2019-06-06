<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\StatsExporter;
use App\Http\Controllers\Controller;
use App\model\Account;
use App\Model\Stats;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Box;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function data(Content $content)
    {
        return $content
            ->header('活动数据')
            ->description('数据列表')
            ->breadcrumb(
                ['text' => '活动数据', 'url' => '/data/act'],
                ['text' => '数据列表']
            )
            ->body($this->grid());
    }

    protected function grid()
    {
        $grid = new Grid(new Stats());
        $grid->exporter(new StatsExporter());
        $grid->model()->select(DB::raw('count(s_id) as count, s_time'))->where('s_id', 0);
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableCreateButton();
        $grid->disableColumnSelector();
        $grid->disablePagination();
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                if (!Admin::user()->isRole('市场人员')) {
                    $filter->equal('s_account_id', '区域')->select('/admin/accounts_list');;
                }
                $filter->between('s_time', '时间')->date();
            });
        });

        $grid->header(function () {
            return $this->box($this->box());
        });

        return $grid;
    }

    /**
     * 统计图
     * @return Box
     */
    protected function box()
    {
        $labels = [];
        $uvData = [];
        $pvData = [];
        Stats::select(DB::raw('count(s_id) as count, s_time, s_type'))
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
            ->groupBy('s_time')
            ->groupBy('s_type')
            ->get()
            ->map(function ($item) use (&$labels, &$pvData, &$uvData) {
                $day = date('m-d', $item['s_time']);
                if (!in_array($day, $labels)) {
                    $labels[] = date('m-d', $item['s_time']);
                }
                if ($item['s_type'] == 'pv') {
                    $pvData[] = $item['count'];
                } else {
                    $uvData[] = $item['count'];
                }
            });

        $doughnut = view('admin.chart.gender', compact('labels', 'pvData', 'uvData', 'label'));

        return new Box('访问数据', $doughnut);
    }
}