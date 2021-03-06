<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\AccountExporter;
use App\model\Account;
use Carbon\Carbon;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountController
{
    use HasResourceActions;

    protected $districts = [
        '珠三角' => '珠三角',
        '广东' => '广东',
        '福建' => '福建',
        '四川' => '四川',
        '北京' => '北京',
        '江苏' => '江苏'
    ];
    protected $states = [
        'on' => ['value' => 1, 'text' => '是', 'color' => 'primary'],
        'off' => ['value' => 0, 'text' => '否', 'color' => 'default'],
    ];

    /**
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        Permission::check('accounts.all');

        return $content
            ->header('区域账号')
            ->description('账号列表')
            ->breadcrumb(
                ['text' => '区域账号', 'url' => '/accounts'],
                ['text' => '账号列表']
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
        Permission::check('accounts.all');

        return $content
            ->header('区域账号')
            ->description('详情')
            ->breadcrumb(
                ['text' => '区域账号', 'url' => '/accounts'],
                ['text' => '详情']
            )
            ->body($this->detail($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        Permission::check('accounts.all');

        return $content
            ->header('区域账号')
            ->description('创建')
            ->breadcrumb(
                ['text' => '区域账号', 'url' => '/accounts'],
                ['text' => '创建']
            )
            ->body($this->form());
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
        Permission::check('accounts.all');

        return $content
            ->header('区域账号')
            ->description('编辑')
            ->breadcrumb(
                ['text' => '区域账号', 'url' => '/accounts'],
                ['text' => $id],
                ['text' => '编辑']
            )
            ->body($this->form($id)->edit($id));
    }

    /**
     * 获取区域列表
     * @return array
     */
    public function accountsList()
    {
        $accounts = DB::select("select `a_id` as `id`, concat(`a_district`, '-', `a_city`) as `text` from `g_4357_accounts` ORDER BY `id`");

        return $accounts ? $accounts : $this->districts;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Account::findOrFail($id));

        $show->a_district('区域');
        $show->a_city('城市');
        $show->a_manager('区域负责人姓名');
        $show->a_manager_phone('区域联系电话');
        $show->a_account('账号');
        $show->a_password('密码');
        $show->a_hot_line('工作热线');
        $show->a_work_time('工作时间');
        $show->a_sponsor('主办方');
        $show->a_scan_times('每个ID单日扫码最高次数');
        $show->a_lottery_times('每个ID单日抽奖最高次数');
        $show->a_state('是否停用')->using([1 => '是', 0 => '否']);
        $show->a_created('创建时间');
        $show->a_updated('更新时间');

        $show->panel()->tools(function ($tools) {
            $tools->disableEdit(false);
            $tools->disableList(false);
        });

        return $show;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Account());
        $grid->exporter(new AccountExporter());

        $grid->a_id('ID');
        $grid->a_district('区域');
        $grid->a_city('城市');
        $grid->a_manager('区域负责人姓名');
        $grid->a_manager_phone('区域联系电话');
        $grid->a_account('账号');
        $grid->a_password('密码');
        $grid->a_hot_line('工作热线');
        $grid->a_work_time('工作时间');
        $grid->a_sponsor('主办方');
        $grid->a_scan_times('每个ID单日扫码最高次数');
        $grid->a_lottery_times('每个ID单日抽奖最高次数');
        $grid->a_state('是否停用')->switch($this->states);
        $grid->a_created('创建时间');
        $grid->a_updated('更新时间');

        $grid->disableRowSelector();
        $grid->actions(function ($actions) {
            $actions->disableDelete();
        });

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                $filter->equal('a_id', '区域')->select('/admin/accounts_list');
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->equal('a_manager', '区域负责人姓名');
            });
        });

        return $grid;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = 0)
    {
        $form = new Form(new Account());

        if (!$id) {
            $form->text('a_district', '区域')->rules('required', ['required' => '请选择区域']);
            $form->text('a_city', '城市')->rules('required', ['required' => '请输入城市']);
            $form->text('a_account', '账号')->rules('required', ['required' => '请输入账号']);
        }
        $form->text('a_manager', '区域负责人姓名')->rules('required', ['required' => '请输入区域负责人姓名']);
        $form->text('a_manager_phone', '区域联系电话')->rules('required|regex:/^[1][3,4,5,6,7,8,9][0-9]{9}$/', [
            'required'  => '请输入区域联系电话',
            'regex'     => '电话号码非法'
        ]);
        $form->text('a_password', '密码')->rules('required', ['required' => '请输入密码']);
        $form->text('a_hot_line', '工作热线')->rules('required', ['required' => '请输入工作热线']);
        $form->text('a_work_time', '工作时间')->rules('required', ['required' => '请输入工作时间']);
        $form->text('a_sponsor', '主办方')->rules('required', ['required' => '请输入主办方']);
        $form->number('a_scan_times', '每个ID单日扫码最高次数')->default(0)->min(0);
        $form->number('a_lottery_times', '每个ID单日抽奖最高次数')->default(0)->min(0);
        $form->switch('a_state', '是否停用')->states($this->states);

		// 添加后台登录账号和角色
		$form->saving(function (Form $form) {
			if (!empty($form->input('a_account'))) {
				$userId = DB::table('admin_users')->insertGetId([
					'username'      => $form->input('a_account'),
					'password'      => bcrypt($form->input('a_password')),
					'name'          => $form->input('a_district').$form->input('a_city').'市场人员',
					'created_at'    => Carbon::now(),
					'updated_at'    => Carbon::now(),
				]);

				DB::table('admin_role_users')->insert([
					'role_id' => 3,
					'user_id' => $userId
				]);
			} else {
				DB::table('admin_users')->where('username', $form->model()->a_account)->update(['password' => bcrypt($form->input('a_password'))]);
			}
		});

        $form->tools(function ($tools) {
            $tools->disableDelete();
        });

        return $form;
    }
}