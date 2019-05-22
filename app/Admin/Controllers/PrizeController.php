<?php

namespace App\Admin\Controllers;

use App\model\Account;
use App\Model\Prize;
use App\Http\Controllers\Controller;
use Encore\Admin\Admin;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;

class PrizeController extends Controller
{
    use HasResourceActions;

    protected $states = [
        'on'  => ['value' => 1, 'text' => '是', 'color' => 'primary'],
        'off' => ['value' => 0, 'text' => '否', 'color' => 'default'],
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
            ->description('区域礼品')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('区域礼品')
            ->description('详情')
            ->body($this->detail($id));
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('区域礼品')
            ->description('编辑')
            ->body($this->form()->edit($id));
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
            ->header('区域礼品')
            ->description('创建')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Prize());

        $grid->p_id('ID');
        $grid->district()->a_district('区域')->expand(function ($model) {
            $info = $model->district()->get()->map(function ($item) {
                return $item->only(['a_manager', 'a_manager_phone']);
            });
            return new Table(['区域负责人姓名', '区域负责人电话'], $info->toArray());
        });
        $grid->p_type('礼品类型')->using((new Prize())->prizeType);
        $grid->p_name('礼品名称');
        $grid->p_point('兑换所需积分')->display(function () {
			return (is_null($this->p_point) || $this->p_point == 0) ? '-' : $this->p_point;
		});
        $grid->p_rate('中奖概率')->display(function () {
			return is_null($this->p_rate) ? '-' : $this->p_rate . '%';
		});
        $grid->p_number('礼品数量');
        $grid->p_state('是否停用')->switch($this->states);
        $grid->p_created('创建时间');
        $grid->p_updated('修改时间');

        $grid->disableRowSelector();
        $grid->actions(function ($actions) {
            $actions->disableDelete();
        });

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                $filter->equal('p_account_id', '区域')->select('/admin/accounts_list');
                $filter->like('p_name', '礼品名称');
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->equal('p_type', '礼品类型')->radio((new Prize())->prizeType)->stacked();
            });
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Prize::findOrFail($id));

        $show->district('区域')->as(function ($district) {
			return $district->a_district;
		});
        $show->p_type('礼品类型')->using((new Prize())->prizeType);
        $show->p_name('礼品名称');
        $show->p_point('兑换所需积分')->as(function ($point) {
			return (is_null($point) || $point == 0) ? '-' : $point;
		});
        $show->p_state('是否停用')->using([0 => '否', 1 => '是']);
        $show->p_created('创建时间');
        $show->p_updated('修改时间');
        $show->p_rate('中奖概率')->as(function ($rate) {
        	return is_null($rate) ? '-' : $rate . '%';
		});

		$show->panel()->tools(function ($tools) {
			$tools->disableEdit(false);
			$tools->disableList(false);
		});

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Prize);

        // $account = Account::where('a_account', Admin::user()->username)->first();
        $account = Account::where('a_account', 'SC-CD')->first();
        $form->text('a_district', '区域')->default($account->a_district)->disable();
        $form->hidden('p_account_id')->value($account->a_id);
        $form->text('p_name', '礼品名称')->rules('required', ['required' => '请输入礼品名称']);
        $form->radio('p_type', '礼品类型')
            ->options((new Prize())->prizeType)
            ->default(1)
            ->rules('required', ['required' => '请选择礼品类型']);
        $form->text('p_number', '礼品数量')->rules('required', ['required' => '请输入礼品数量']);
        $form->textarea('p_detail', '礼品详情')->rules('required', ['required' => '请输入礼品详情']);
        $form->number('p_point', '兑换所需积分')->min(0)->default(0);
        $form->rate('p_rate', '中奖概率')->setWidth(1, 2);
        $form->switch('p_state', '是否停用')->states($this->states);

        $form->tools(function ($tools) {
            $tools->disableDelete();
        });

        return $form;
    }
}
