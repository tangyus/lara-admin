<?php

namespace App\Admin\Controllers;

use App\Model\ApiLog;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

class ApiLogController extends Controller
{
    /**
     * Index interface.
     *
     * @param Content $content
     *
     * @return Content
     */
    public function index(Content $content)
    {
        if (!Admin::user()->inRoles(['']))

        return $content
            ->header('接口日志')
            ->description('列表')
            ->body($this->grid());
    }

    /**
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ApiLog());

        $grid->model()->leftJoin('users', 'u_id', 'user_id')
            ->leftJoin('shops', 's_id', 'shop_id')
            ->orderBy('id', 'DESC');

        $grid->id('ID')->sortable();
        $grid->u_nick('用户昵称');
        $grid->u_phone('手机号');
        $grid->s_number('门店编号');
        $grid->s_name('门店名');
        $grid->method('方法名')->display(function ($method) {
            return "<span class=\"badge bg-grey\">$method</span>";
        });
        $grid->path('请求路径')->label('info');
        $grid->ip('访问ip')->label('primary');
        $grid->input('参数')->display(function ($input) {
            $input = json_decode($input, true);
            $input = Arr::except($input, ['_pjax', '_token', '_method', '_previous_']);
            if (empty($input)) {
                return '<code>{}</code>';
            }

            return '<pre>'.json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</pre>';
        });

        $grid->created_at('时间');

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableEdit();
            $actions->disableView();
        });

        $grid->disableCreateButton();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableExport();
        $grid->disableColumnSelector();
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->equal('u_phone', '电话号码');
            $filter->like('path', '请求路径');
            $filter->equal('ip', '访问ip');
        });

        return $grid;
    }
}
