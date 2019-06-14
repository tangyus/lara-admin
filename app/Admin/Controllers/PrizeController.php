<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\PrizeExporter;
use App\model\Account;
use App\Model\Prize;
use App\Http\Controllers\Controller;
use App\model\Shop;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
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
        }
        $grid->p_type('礼品类型')->using((new Prize())->prizeType);
        $grid->p_name('礼品详情')->modal('详情', function () {
            return new Table(['领取规则', '领取截止时间', '活动热线'], [0 => [$this->p_rule, $this->p_deadline, $this->p_phone_number]]);
        });
        $grid->p_apply_city('适用门店')->modal('适用门店', function () {
            $shops = Shop::find($this->p_apply_shop)->map(function ($item) {
                return $item->only('s_name', 's_city', 's_address', 's_phone');
            });

            return new Table(['门店名称', '门店城市', '门店地址', '门店联系电话'], $shops->toArray());
        });
        $grid->p_point('兑换所需积分')->display(function () {
			return (is_null($this->p_point) || $this->p_point == 0) ? '-' : $this->p_point;
		});
        $grid->p_rate('中奖概率')->display(function () {
			return is_null($this->p_rate) ? '-' : $this->p_rate . '%';
		});
        $grid->p_number('礼品数量');
        $grid->p_current_number('剩余数量(总量-已核销)')->display(function () {
        	return ($this->p_number - $this->p_used_number);
		});
        if (Admin::user()->cannot('prizes.create')) {
            $grid->p_state('是否停用')->using(['否', '是']);
        } else {
            $grid->p_state('是否停用')->switch($this->states);
        }
        $grid->p_img('礼品图')->image('', 100, 100);
        $grid->p_created('创建时间');
        $grid->p_updated('修改时间');

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                if (!Admin::user()->isRole('市场人员')) {
                    $filter->equal('p_account_id', '区域')->select('/admin/accounts_list');
                }
                $filter->equal('p_name', '礼品名称');
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->equal('p_type', '礼品类型')->select((new Prize())->prizeType);
            });
        });

        $grid->disableCreateButton();
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
        $show->p_img('礼品图')->image();
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

        $prize = Prize::find($id);
        if (Admin::user()->isRole('市场人员')) {
            $account = Account::where('a_account', Admin::user()->username)->first();
            $form->text('a_district', '区域')->default($account->a_district)->disable();
            $form->text('a_district', '适用城市')->default($account->a_city)->disable();
            $form->hidden('p_account_id');
            $form->hidden('p_apply_city');

            $form->saving(function (Form $form) use ($account) {
                $form->input('p_account_id', $account->a_id);
                $form->input('p_apply_city', $account->a_city);
                if (in_array($form->input('p_type'), [Prize::NEW_PRIZE, Prize::ADVANCE_PRIZE])) {
                    $form->input('p_deadline', '2019-09-15 23:59:59');
                }
            });
        }
        $form->text('p_name', '礼品名称')->disable();
        $form->text('p_type', '礼品类型')->disable();
        if ($prize && $prize->p_type == Prize::LEGEND_PRIZE) {
            $form->text('p_number', '礼品数量')->rules('required', ['required' => '请输入礼品数量']);
            $form->rate('p_rate', '中奖概率')->setWidth(2, 2)->default(null)->disable();
        } elseif ($prize && $prize->p_type == Prize::EXCHANGE_PRIZE) {
            $form->text('p_number', '礼品数量')->rules('required', ['required' => '请输入礼品数量']);
            $form->text('p_point', '兑换所需积分')->default(null)->disable();
        } else {
            $form->text('p_number', '礼品数量')->disable();
        }
        $form->datetime('p_deadline', '领取截止时间')->placeholder('领取截止时间')->disable();

        $form->listbox('p_apply_shop', '适用门店')->options(Shop::where(function ($query) {
                if (Admin::user()->isRole('市场人员')) {
                    $account = Account::where('a_account', Admin::user()->username)->first();
                    $query->where('s_account_id', $account->a_id);
                    $query->where('s_state', 0);
                }
            })
            ->get()
            ->pluck('s_name', 's_id'))
            ->help('优惠券/跑鞋/新人礼/进阶礼/会员礼 不需设定门店');
        $form->text('p_phone_number', '活动热线')->help('优惠券/新人礼/进阶礼/会员礼 不需要填写');;
        $form->text('p_rule', '领取规则')->help('优惠券/新人礼/进阶礼/会员礼 不需要填写');;
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
                            $('#p_deadline').val('2019-09-30 23:59:59');
                        } else if (val == '闪电积分礼') {
                            $('#p_deadline').val('2019-09-30 23:59:59');
                        } else {
                            if (val == '闪电新人礼' || val == '闪电进阶礼') {
                                $('#p_deadline').val('2019-09-15 23:59:59');
                            } else {
                                $('#p_deadline').val('');
                            }
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
