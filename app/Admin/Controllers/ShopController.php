<?php

namespace App\Admin\Controllers;

use App\Model\Shop;
use App\Http\Controllers\Controller;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ShopController extends Controller
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
//    	dd(Admin::user());

        return $content
            ->header('门店信息管理')
            ->description('区域门店')
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
		Permission::check('shops.list');

        return $content
            ->header('区域门店')
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
            ->header('区域门店')
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
            ->header('区域门店')
            ->description('新建')
            ->body($this->form());
    }


    public function shopsList()
    {
        $shops = Shop::get(['s_id as id', 's_name as text']);

        return $shops;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Shop);

        $grid->s_id('ID');
        $grid->s_number('门店序号');
        $grid->district()->a_district('区域');
        $grid->district()->a_city('城市');
        $grid->district()->a_manager('区域负责人姓名');
        $grid->district()->a_manager_phone('区域联系电话');
        $grid->s_name('门店名称');
        $grid->s_manager('门店负责人姓名');
        $grid->s_manager_phone('门店负责人电话');
        $grid->s_password('密码');
        $grid->s_state('是否停用')->switch($this->states);
        $grid->s_created('创建时间');
        $grid->s_updated('修改时间');

        $grid->disableRowSelector();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1/2, function ($filter) {
                $filter->equal('s_account_id', '区域')->select('/admin/district/accounts_list');

				$filter->where(function ($query) {
					$query->whereHas('district', function ($query) {
						$query->where('a_city', $this->input);
					});

				}, '城市');

				$filter->where(function ($query) {
					$query->whereHas('district', function ($query) {
						$query->where('a_manager_phone', $this->input);
					});

				}, '区域联系电话');
            });
            $filter->column(1/2, function ($filter) {
                $filter->equal('s_number', '门店序号');
                $filter->like('s_name', '门店名称');
                $filter->equal('s_manager_phone', '门店联系电话');
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
        $show = new Show(Shop::findOrFail($id));

        $show->s_number('门店序号');
        $show->district('区域', function ($district) {
			$district->a_district('区域');
			$district->a_city('城市');
			$district->a_manager('区域负责人姓名');
			$district->a_manager_phone('区域联系电话');
		});

        $show->s_name('门店名称');
        $show->s_manager('门店负责人姓名');
        $show->s_manager_phone('门店负责人电话');
        $show->s_password('门店核销密码');
        $show->s_state('是否停用')->using([0 => '否', 1 => '是']);
        $show->s_created('创建时间');
        $show->s_updated('修改时间');

		$show->panel()->tools(function ($tools) {
			$tools->disableDelete(false);
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
        $form = new Form(new Shop);

        $form->select('s_account_id', '区域')->options('/admin/district/accounts_list')->loads(['a_city', 'a_manager', 'a_manager_phone'], [
            '/admin/district/accounts_info/a_city',
            '/admin/district/accounts_info/a_manager',
            '/admin/district/accounts_info/a_manager_phone'
        ])->rules('required', ['required' => '请选择区域']);

        $form->select('a_city', '城市');
        $form->select('a_manager', '区域负责人姓名');
        $form->select('a_manager_phone', '区域负责人联系电话');
        $form->text('s_number', '门店序号')->rules('required', ['required' => '请输入门店序号']);
        $form->text('s_name', '门店名称')->rules('required', ['required' => '请输入门店名称']);
        $form->text('s_manager', '门店负责人姓名')->rules('required', ['required' => '请输入门店联系人姓名']);
        $form->text('s_manager_phone', '门店负责人电话')->rules('required|regex:/^[1][3,4,5,6,7,8,9][0-9]{9}$/', [
            'required' => '请输入门店负责人电话',
            'regex' => '电话号码非法'
        ]);
        $form->password('s_password', '门店核销密码')->rules('required', ['required' => '请输入门店核销密码']);
        $form->switch('s_state', '是否停用')->states($this->states);

        $form->ignore(['a_city', 'a_manager', 'a_manager_phone']);

        return $form;
    }
}
