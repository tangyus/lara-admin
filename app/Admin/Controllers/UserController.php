<?php

namespace App\Admin\Controllers;

use App\Model\PointRecord;
use App\model\Prize;
use App\Model\User;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;

class UserController extends Controller
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
            ->header('用户管理')
            ->description('用户列表')
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
            ->header('用户管理')
            ->description('详情')
            ->body($this->detail($id));
    }

    public function pointRecord(Content $content)
    {
        return $content
            ->header('用户管理')
            ->description('积分记录')
            ->body($this->pointRecordGrid());
    }

    public function pointRecordShow($id, Content $content)
    {
        return $content
            ->header('积分记录')
            ->description('详情')
            ->body($this->pointRecordDetail($id));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User);

        $grid->u_id('ID');
        $grid->district()->a_district('区域')->expand(function ($model) {
            $info = $model->district()->get()->map(function ($item) {
                return $item->only(['a_manager', 'a_manager_phone']);
            });
            return new Table(['区域负责人姓名', '区域负责人电话'], $info->toArray());
        });
        $grid->u_openid('openId');
        $grid->u_nick('用户昵称');
        $grid->u_phone('用户手机号');
        $grid->u_current_point('当前积分');
        $grid->u_total_point('总积分');
        $grid->u_ip('登录IP');
        $grid->u_created('登录时间')->sortable();

        $grid->disableCreateButton();
        $grid->disableRowSelector();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                $filter->equal('u_account_id', '区域')->select('district/accounts_list');
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->equal('u_phone', '手机号');
            });
        });

        // 禁用 编辑和删除
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
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
        $show = new Show(User::findOrFail($id));

        $show->district('区域', function ($district) {
            $district->panel()->tools(function ($tools) {
                $tools->disableEdit();
                $tools->disableDelete();
                $tools->disableList();
            });
            $district->a_district('区域');
            $district->a_manager('区域负责人姓名');
            $district->a_manager_phone('区域负责人电话');
        });
        $show->u_openid('openId');
        $show->u_nick('用户昵称');
        $show->u_phone('用户手机号');
        $show->u_current_point('当前积分');
        $show->u_total_point('总积分');
        $show->u_ip('登录IP');
        $show->u_created('登录时间');

        $show->panel()->tools(function ($tools) {
                $tools->disableEdit();
                $tools->disableDelete();
            });

        return $show;
    }

    protected function pointRecordGrid()
    {
        $grid = new Grid(new PointRecord());

        $grid->pr_id('ID');
//        $grid->user()->district('区域', function ($district) {
//            $district->a_district('区域');
//        });
//        $grid->user()->district()->a_district('区域');
        $grid->user()->u_openid('openID');
        $grid->user()->u_nick('用户昵称');
        $grid->user()->u_phone('用户手机号');
        $grid->prize()->p_type('礼品类型')->using((new Prize())->prizeType);
        $grid->prize()->p_name('礼品名称');
        $grid->pr_created('时间');
        $grid->pr_point('获得/消耗积分');
        $grid->pr_current_point('当前用户积分');
        $grid->shop()->s_name('使用门店')->expand(function ($model) {
            $info = $model->shop()->get()->map(function ($item) {
                return $item->only(['s_number', 's_name', 's_manager', 's_manager_phone']);
            });
            return new Table(['门店序号', '门店名称', '门店负责人姓名', '门店负责人电话'], $info->toArray());
        });

        $grid->disableCreateButton();
        $grid->disableRowSelector();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                // 关联关系查询
                $filter->where(function ($query) {
                    $query->whereHas('user', function ($query) {
                        $query->where('u_phone', $this->input);
                    });
                }, '用户手机号');
                $filter->where(function ($query) {
                    $query->whereHas('user', function ($query) {
                        $query->where('u_account_id', $this->input);
                    });
                }, '用户区域')->select('/admin/district/accounts_list');

                $filter->between('pr_created', '时间')->datetime();
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->equal('pr_prize_id', '礼品类型')->select((new Prize())->prizeType);
                $filter->equal('pr_shop_id', '使用门店')->select('/admin/district/shops_list');
            });
        });

        // 禁用 编辑和删除
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function pointRecordDetail($id)
    {
        $show = new Show(PointRecord::findOrFail($id));

        $show->user('用户', function ($user) {
            $user->district()->a_district('区域')->as(function ($content) {
                $content = json_decode($content, true);
                return $content['a_district'];
            });
            $user->u_openid('openId');
            $user->u_nick('用户昵称');
            $user->u_phone('用户手机号');
        });
        $show->prize('礼品', function ($prize) {
            $prize->p_type('礼品类型');
            $prize->p_name('礼品名称');
        });
        $show->shop('门店', function ($shop) {
            $shop->s_name('使用门店');
            $shop->s_number('门店序号');
            $shop->s_manager('门店负责人姓名');
            $shop->s_manager_phone('门店负责人电话');
        });
        $show->pr_created('时间');
        $show->pr_point('获得/消耗积分');
        $show->pr_current_point('当前用户积分');
        $show->panel()->tools(function ($tools) {
            $tools->disableList(false);
        });

        return $show;
    }
}
