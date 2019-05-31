<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\PrizeExporter;
use App\model\Account;
use App\Model\Prize;
use App\Http\Controllers\Controller;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\Auth;

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
        Permission::check('prizes.list');

        return $content
            ->header('区域礼品')
            ->description('礼品列表')
            ->breadcrumb(
                ['text' => '区域礼品', 'url' => '/prizes'],
                ['text' => '礼品列表']
            )
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
        Permission::check('prizes.list');

        return $content
            ->header('区域礼品')
            ->description('详情')
            ->breadcrumb(
                ['text' => '区域礼品', 'url' => '/prizes'],
                ['text' => '详情']
            )
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
        Permission::check('prizes.create');

        return $content
            ->header('区域礼品')
            ->description('编辑')
            ->breadcrumb(
                ['text' => '区域礼品', 'url' => '/prizes'],
                ['text' => $id],
                ['text' => '编辑']
            )
            ->body($this->form($id)->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        Permission::check('prizes.create');

        return $content
            ->header('区域礼品')
            ->description('创建')
            ->breadcrumb(
                ['text' => '区域礼品', 'url' => '/prizes'],
                ['text' => '创建']
            )
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
        if (Admin::user()->isRole('市场人员')) {
            // 修改数据来源
            $account = Account::where('a_account', Admin::user()->username)->first();
            $grid->model()->where('p_account_id', $account->a_id);
        }
        $grid->exporter(new PrizeExporter());

        $grid->p_id('ID');
        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $grid->district()->a_district('区域')->expand(function ($model) {
                $info = $model->district()->get()->map(function ($item) {
                    return $item->only(['a_district', 'a_city', 'a_manager', 'a_manager_phone']);
                });
                return new Table(['区域', '城市', '区域负责人姓名', '区域负责人电话'], $info->toArray());
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
        } else {
            $grid->disableFilter();
        }
        $grid->p_type('礼品类型')->using((new Prize())->prizeType);
        $grid->p_name('礼品名称');
        $grid->p_point('兑换所需积分')->display(function () {
			return (is_null($this->p_point) || $this->p_point == 0) ? '-' : $this->p_point;
		});
        $grid->p_rate('中奖概率')->display(function () {
			return is_null($this->p_rate) ? '-' : $this->p_rate . '%';
		});
        $grid->p_number('礼品数量');
        if (Admin::user()->cannot('prizes.create')) {
            $grid->p_state('是否停用')->using(['否', '是']);
            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                $actions->disableDelete();
            });
        } else {
            $grid->p_state('是否停用')->switch($this->states);
        }
//        $grid->p_img('礼品图')->image('', 100, 100);
//        $grid->p_thumb('礼品缩略图')->image('', 100, 100);
        $grid->p_created('创建时间');
        $grid->p_updated('修改时间');

        $grid->disableRowSelector();
        if (Admin::user()->cannot('prizes.create')) {
            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                $actions->disableEdit(false);
            });
        }
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            if (!Admin::user()->isRole('市场人员')) {
                $actions->disableEdit();
            }
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
        $show->p_rate('中奖概率')->as(function ($rate) {
            return is_null($rate) ? '-' : $rate . '%';
        });
//        $show->p_img('礼品图')->image();
        $show->p_thumb('礼品缩略图')->image();
        $show->p_created('创建时间');
        $show->p_updated('修改时间');

		$show->panel()->tools(function ($tools) {
			$tools->disableList(false);
			if (Admin::user()->isRole('市场人员')) {
                $tools->disableEdit(false);
            }
		});

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = 0)
    {
        $form = new Form(new Prize);

        if ($id) {
            $form->text('district.a_district', '区域')->disable();
        } else {
            if (Admin::user()->isRole('市场人员')) {
                $account = Account::where('a_account', Admin::user()->username)->first();
                $form->text('a_district', '区域')->default($account->a_district)->disable();
                $form->hidden('p_account_id');

                $form->saving(function (Form $form) use ($account) {
                    $form->input('p_account_id', $account->a_id);
                });
            } else {
                $form->select('p_account_id', '区域')->options('/admin/accounts_list');
            }
        }

        $form->text('p_name', '礼品名称')->rules('required', ['required' => '请输入礼品名称']);
        $form->radio('p_type', '礼品类型')->options((new Prize())->prizeType)->rules('required', ['required' => '请选择礼品类型']);
        $form->text('p_number', '礼品数量')->rules('required', ['required' => '请输入礼品数量']);
        $form->text('p_point', '兑换所需积分')->default(0)->disable();
        $form->rate('p_rate', '中奖概率')->setWidth(2, 2)->disable();
        $form->text('p_apply_city', '适用城市');
        $form->text('p_apply_shop', '适用门店');
        $form->text('p_rule', '领取规则');
        $form->datetime('p_deadline', '领取截止时间');
        $form->text('p_phone_number', '活动热线');

//        $form->image('p_img', '礼品图')->move('prizes')->rules('required', ['required' => '请上传礼品图']);
        $form->image('p_thumb', '礼品缩略图')->move('prizes')->rules('required', ['required' => '请上传礼品缩略图']);
        $form->switch('p_state', '是否停用')->states($this->states);

        $form->tools(function ($tools) {
            $tools->disableDelete();
        });

        $form->html("
            <script type='text/javascript'>
                    $(function() {
                        $('.radio-inline').click(function() {
                            var a= $('.iradio_minimal-blue.hover')[0];
                            var val = $($(a).children()[0]).val();
                            if (val == '闪电传奇礼') {
                                $('#p_rate').attr('disabled', false);
                                $('#p_point').attr('disabled', true);
                            } else if (val == '闪电兑换礼') {
                                $('#p_point').attr('disabled', false);
                                $('#p_rate').attr('disabled', true);
                            } else {
                                 $('#p_point').attr('disabled', true);
                                $('#p_rate').attr('disabled', true);
                            }
                        })
                    })
            </script>
        ");

        return $form;
    }
}
