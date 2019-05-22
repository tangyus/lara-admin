<?php

namespace App\Admin\Controllers;

use App\model\Account;
use App\Model\Qrcode;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class QrcodeController extends Controller
{
    use HasResourceActions;

    protected $memberDate = [
        1 => '每周一', 2 => '每周二', 3 => '每周三', 4 => '每周四',
        5 => '每周五', 6 => '每周六', 7 => '每周日'
    ];

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('门店信息管理')
            ->description('二维码')
            ->body($this->grid());
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('二维码')
            ->description('生成')
            ->body($this->form());
    }

    public function download()
    {
        dd(1);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Qrcode);

        $grid->q_id('ID');
        $grid->district()->a_district('区域');
        $grid->district()->a_city('城市');
        $grid->district()->a_manager('区域负责人姓名');
        $grid->district()->a_manager_phone('区域负责人电话');
        $grid->q_city('生成城市');
        $grid->q_number('生成数量');
        $grid->q_point('扫码积分');
        $grid->q_member_date('会员日')->using($this->memberDate);
        $grid->q_expired('二维码有效期');
        $grid->q_created('生成时间');

        $grid->disableRowSelector();
        $grid->disableFilter();

        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
            $actions->append('<a href="/admin/qrcodes/download">生成二维码</a>');
        });

        return $grid;
    }
    
    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Qrcode);

//        $account = Account::where('a_account', Admin::user()->username)->first();
        $account = Account::where('a_account', 'SC-CD')->first();
        $accounts = Account::where('a_account', 'SC-CD')->get();
        $cities = [];
        foreach ($accounts as $account) {
            $cities[$account->a_city] = $account->a_city;
        }

        $form->text('q_district', '区域')->default($account->a_district)->attribute(['disabled' => true]);
        $form->hidden('q_account_id')->value($account->a_id);
        $form->select('q_city', '城市')->options($cities);
        $form->text('q_number', '生成数量');
        $form->datetime('q_expired', '二维码有效期');
        $form->radio('q_member_date', '会员日')->options($this->memberDate);
        $form->number('q_point', '扫码积分')->min(5)->default(5);

        return $form;
    }
}
