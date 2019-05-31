<?php

namespace App\Admin\Controllers;

use App\model\Account;
use App\Model\Rule;
use App\Http\Controllers\Controller;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class RuleController extends Controller
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
        Permission::check('rules.list');
        return $content
            ->header('规则管理')
            ->description('规则列表')
            ->breadcrumb(
                ['text' => '规则列表']
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
        Permission::check('rules.list');
        return $content
            ->header('规则管理')
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
        Permission::check('rules.create');
        return $content
            ->header('规则管理')
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
        Permission::check('rules.create');
        return $content
            ->header('规则管理')
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
        $grid = new Grid(new Rule());
        if (Admin::user()->isRole('市场人员')) {
            // 修改数据来源
            $account = Account::where('a_account', Admin::user()->username)->first();
            $grid->model()->where('r_account_id', $account->a_id);
        }
        $grid->r_id('ID');
        $grid->r_act_img('活动规则图')->image();
        $grid->r_rule_img('传奇礼规则图')->image();
        $grid->r_created('创建时间');
        $grid->r_updated('更新时间');

        $grid->disableFilter();
        if (!Admin::user()->isRole('市场人员')) {
            $grid->disableCreateButton();
        }
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            if (!Admin::user()->isRole('市场人员')) {
                $actions->disableEdit();
            }
        });
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->disableColumnSelector();

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
        $show = new Show(Rule::findOrFail($id));

        $show->r_id('ID');
        $show->district('区域')->as(function ($district) {
            return $district->a_district;
        });
        $show->r_act_img('活动规则图')->image();
        $show->r_rule_img('传奇礼规则图')->image();
        $show->r_created('创建时间');
        $show->r_updated('更新时间');

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
        $form = new Form(new Rule);

        if ($id) {
            $form->text('district.a_district', '区域')->disable();
        } else {
            if (Admin::user()->isRole('市场人员')) {
                $account = Account::where('a_account', Admin::user()->username)->first();
                $form->text('a_district', '区域')->default($account->a_district)->disable();
                $form->hidden('r_account_id');

                $form->saving(function (Form $form) use ($account) {
                    $form->input('r_account_id', $account->a_id);
                });
            } else {
                $form->select('r_account_id', '区域')->options('/admin/accounts_list');
            }
        }
        $form->tools(function ($tools) {
            $tools->disableDelete();
        });
        $form->image('r_act_img', '活动规则图')->move('rules');
        $form->image('r_rule_img', '传奇礼规则图')->move('rules');

        return $form;
    }
}
