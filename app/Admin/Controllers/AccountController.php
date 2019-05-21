<?php

namespace App\Admin\Controllers;

use App\model\Account;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;

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
        'on'  => ['value' => 1, 'text' => '是', 'color' => 'primary'],
        'off' => ['value' => 0, 'text' => '否', 'color' => 'default'],
    ];

	/**
	 * @param Content $content
	 * @return Content
	 */
    public function index(Content $content)
    {
        return $content
            ->header('门店信息管理')
            ->description('区域账号')
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
            ->header('区域账号')
            ->description('详情')
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
        return $content
            ->header('区域账号')
            ->description('创建')
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
        return $content
            ->header('区域账号')
            ->description('编辑')
            ->body($this->form()->edit($id));
    }

    public function accountsDetail(Request $request, $type)
    {
        $districtId = $request->get('q');
        if (!empty($districtId)) {
            $account = Account::select('a_id', 'a_city', 'a_manager', 'a_manager_phone')->where('a_id', $districtId)->first();

            return ['text' => $account->{$type}];
        } else {
            return ['text' => ''];
        }
    }

    public function accountsList()
    {
        $accounts = Account::get(['a_id as id', 'a_district as text']);

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
        $show->a_state('是否停用')->using([1 => '是', 0 => '否']);
        $show->a_created('创建时间');
        $show->a_updated('修改时间');

        $show->panel()->tools(function ($tools) {
        	$tools->disableDelete(false);
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

        $grid->a_id('ID');
        $grid->a_district('区域');
        $grid->a_city('城市');
        $grid->a_manager('区域负责人姓名');
        $grid->a_manager_phone('区域联系电话');
        $grid->a_account('账号');
        $grid->a_password('密码');
        $grid->a_state('是否停用')->switch($this->states);
        $grid->a_created('创建时间');
        $grid->a_updated('修改时间');

        $grid->disableRowSelector();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1/2, function ($filter) {
				$filter->equal('a_district', '区域')->select('/admin/district/accounts_list');
			});
			$filter->column(1/2, function ($filter) {
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
    protected function form()
    {
        $form = new Form(new Account());

        $form->select('a_district', '区域')->options($this->districts)->rules('required', ['required' => '请选择区域']);
        $form->text('a_city', '城市')->rules('required', ['required' => '请输入城市']);
        $form->text('a_manager', '区域负责人姓名')->rules('required', ['required' => '请输入区域负责人姓名']);
        $form->text('a_manager_phone', '区域联系电话')->rules('required|regex:/^[1][3,4,5,6,7,8,9][0-9]{9}$/', [
            'required' => '请输入区域联系电话',
            'regex' => '电话号码非法'
        ]);
        $form->text('a_account', '账号')->rules('required', ['required' => '请输入账号']);
        $form->password('a_password', '密码')->rules('required', ['required' => '请输入密码']);
        $form->switch('a_state', '是否停用')->states($this->states);

        return $form;
    }
}