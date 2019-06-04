<?php

namespace App\Admin\Controllers;

use App\model\Account;
use App\Model\Prize;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ActController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
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

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Prize);
        if (Admin::user()->isRole('市场人员')) {
            // 修改数据来源
            $account = Account::where('a_account', Admin::user()->username)->first();
            $grid->model()->where('p_account_id', $account->a_id);
        }
        $grid->p_id('ID');
        $grid->district()->a_district('区域');
        $grid->p_type('礼品类型');
        $grid->p_name('礼品名称');
        $grid->p_number('礼品设置总量');
        $grid->p_receive_number('礼品发放量');
        $grid->p_used_number('礼品领取/使用量');
        $grid->p_created('礼品剩余量')->display(function () {
            return $this->p_number - $this->p_used_number;
        });

        $grid->disableCreateButton();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableFilter();
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
        });

        return $grid;
    }
}
