<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\PointRecordExporter;
use App\Admin\Extensions\UserExporter;
use App\Admin\Extensions\UserPrizeExporter;
use App\model\Account;
use App\Model\PointRecord;
use App\model\Prize;
use App\Model\User;
use App\Http\Controllers\Controller;
use App\Model\UserPrize;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
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
            ->breadcrumb(
                ['text' => '用户管理', 'url' => '/users'],
                ['text' => '用户列表']
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
        return $content
            ->header('用户管理')
            ->description('详情')
            ->breadcrumb(
                ['text' => '用户管理', 'url' => '/users'],
                ['text' => $id],
                ['text' => '详情']
            )
            ->body($this->detail($id));
    }

    /**
     * 用户积分列表
     * @param Content $content
     * @return Content
     */
    public function pointRecord(Content $content)
    {
        return $content
            ->header('积分记录')
            ->description('积分列表')
            ->breadcrumb(
                ['text' => '积分记录', 'url' => '/users/point_record'],
                ['text' => '积分列表']
            )
            ->body($this->pointRecordGrid());
    }

    /**
     * 用户积分详情
     * @param $id
     * @param Content $content
     * @return Content
     */
    public function pointRecordShow($id, Content $content)
    {
        return $content
            ->header('积分记录')
            ->description('详情')
            ->breadcrumb(
                ['text' => '积分记录', 'url' => '/users/point_record'],
                ['text' => $id],
                ['text' => '详情']
            )
            ->body($this->pointRecordDetail($id));
    }

    /**
     * 用户积分列表
     * @param Content $content
     * @return Content
     */
    public function userPrize(Content $content)
    {
        return $content
            ->header('用户奖品列表')
            ->description('奖品列表')
            ->breadcrumb(
                ['text' => '奖品列表', 'url' => '/users/prize'],
                ['text' => '奖品列表']
            )
            ->body($this->userPrizeGrid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User);
        $grid->model()->where(function ($query) {
			if (Admin::user()->isRole('市场人员')) {
				// 修改数据来源
				$account = Account::where('a_account', Admin::user()->username)->first();
				$query->where('u_account_id', $account->a_id);
			}
		});
        $grid->exporter(new UserExporter());

        $grid->u_id('ID');
        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $grid->district()->a_district('区域')->expand(function ($model) {
                $info = $model->district()->get()->map(function ($item) {
                    return $item->only(['a_district', 'a_city', 'a_manager', 'a_manager_phone']);
                });
                return new Table(['区域', '城市', '区域负责人姓名', '区域负责人电话'], $info->toArray());
            });
        }
        $grid->u_city('城市');
        $grid->u_openid('openId');
        $grid->u_nick('用户昵称');
        $grid->u_headimg('用户头像')->image('', 64, 64);
        $grid->u_phone('用户手机号');
        $grid->u_current_point('当前积分');
        $grid->u_total_point('总积分');
        $grid->u_ip('登录IP');
        $grid->u_created('登录时间');

        $grid->disableCreateButton();
        $grid->disableRowSelector();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            if (!Admin::user()->isRole('市场人员')) {
                $filter->column(1 / 2, function ($filter) {
                    $filter->equal('u_account_id', '区域')->select('/admin/accounts_list');
                });
            }
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

        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $show->district('区域', function ($district) {
                $district->a_district('区域');
                $district->a_manager('区域负责人姓名');
                $district->a_manager_phone('区域负责人电话');
            });
        }

        $show->u_city('城市');
        $show->u_openid('openId');
        $show->u_nick('用户昵称');
        $show->u_headimg('用户头像')->image();
        $show->u_phone('用户手机号');
        $show->u_current_point('当前积分');
        $show->u_total_point('总积分');
        $show->u_ip('登录IP');
        $show->u_created('登录时间');
        $show->u_updated('更新时间');

        $show->panel()->tools(function ($tools) {
                $tools->disableEdit();
                $tools->disableDelete();
            });

        return $show;
    }

    /**
     * 用户积分记录
     * @return Grid
     */
    protected function pointRecordGrid()
    {
        $grid = new Grid(new PointRecord());
        $grid->exporter(new PointRecordExporter());

        $grid->model()->where('pr_received', 1)
            ->leftJoin('users', 'u_id', 'pr_uid')
			->leftJoin('accounts', 'a_id', 'u_account_id')
			->where(function ($query) {
				if (Admin::user()->isRole('市场人员')) {
					// 修改数据来源
					$account = Account::where('a_account', Admin::user()->username)->first();
					$query->where('u_account_id', $account->a_id);
				}
			})
			->orderBy('pr_updated', 'desc')
			->orderBy('pr_id', 'desc');

        $grid->pr_id('ID');
        $grid->u_headimg('用户头像')->image('', 64, 64)->expand(function () {
            return new Table(['区域', '城市', '昵称', '手机号'], [0 => [$this->a_district, $this->u_city, $this->u_nick, $this->u_phone]]);
        });
        $grid->pr_prize_type('礼品类型');
        $grid->pr_prize_name('礼品名称');
        $grid->pr_point('积分')->display(function ($point) {
            return ($point > 0) ? "<span class='label label-info'>获得 $point</span>" : "<span class='label label-danger'>消耗 $point</span>";
        });
        $grid->pr_updated('时间');
        $grid->pr_current_point('当前用户积分');

        $grid->disableCreateButton();
        $grid->disableRowSelector();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->column(1 / 2, function ($filter) {
                // 关联关系查询
                $filter->equal('u_phone', '用户手机号');
//                $filter->where(function ($query) {
//                    $query->whereHas('user', function ($query) {
//                        $query->where('u_phone', $this->input);
//                    });
//                }, '用户手机号');
                if (!Admin::user()->isRole('市场人员')) {
                    $filter->equal('u_account_id', '用户区域')->select('/admin/accounts_list');
//                    $filter->where(function ($query) {
//                        $query->whereHas('user', function ($query) {
//                            $query->where('u_account_id', $this->input);
//                        });
//                    }, '用户区域')->select('/admin/accounts_list');
                }

                $filter->between('pr_created', '时间')->datetime();
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->equal('pr_prize_type', '礼品类型')->select((new Prize())->prizeType);
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

        if (!Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $show->user('用户', function ($user) {
                $user->district()->a_district('区域')->as(function ($content) {
                    $content = json_decode($content, true);
                    return $content['a_district'];
                });
                $user->u_openid('openId');
                $user->u_nick('用户昵称');
                $user->u_phone('用户手机号');
            });
        }
        $show->user()->u_city('城市')->as(function ($user) {
            return $user->u_city;
        });
        $show->pr_prize_type('礼品类型');
        $show->pr_prize_name('礼品名称');
        $show->pr_created('时间');
        $show->pr_point('积分')->as(function ($point) {
            return ($point > 0) ? "获得 $point" : "消耗 $point";
        });
        $show->pr_current_point('当前用户积分');
        $show->panel()->tools(function ($tools) {
            $tools->disableList(false);
        });

        return $show;
    }

    /**
     * 用户积分记录
     * @return Grid
     */
    protected function userPrizeGrid()
    {
        $grid = new Grid(new UserPrize());
        $grid->exporter(new UserPrizeExporter());

        $grid->model()->leftJoin('users', 'u_id', 'up_uid')
            ->leftJoin('prizes', 'p_id', 'up_prize_id')
            ->leftJoin('shops', 's_id', 'up_shop_id')
            ->where(function ($query) {
                if (Admin::user()->isRole('市场人员')) {
                    // 修改数据来源
                    $account = Account::where('a_account', Admin::user()->username)->first();
                    $query->where('u_account_id', $account->a_id);
                }
            })
            ->orderBy('up_created', 'desc');

        $grid->up_id('ID');
        $grid->u_headimg('用户头像')->image('', 64, 64)->expand(function () {
            return new Table(['城市', '昵称', '手机号'], [0 => [$this->u_city, $this->u_nick, $this->u_phone]]);
        });
        $grid->up_type('类型');
        $grid->p_name('奖品名称')->modal('邮寄信息', function ($model) {
            return new Table(['姓名', '电话', '地址', '尺码', '身份证号', '快递单号'], [0 => [
                $model->up_name,
                $model->up_phone,
                $model->up_address,
                $model->up_size,
                $model->up_idcard,
                $model->up_number
            ]]);
        });
        $grid->p_type('奖品类型');
        $grid->up_received('是否核销')->display(function ($received) {
            return ($received == 1) ? "<span class='label label-success'>是</span>" : "<span class='label label-info'>否</span>";
        });
        $grid->up_code('券码');
        $grid->s_name('核销门店')->modal('核销门店', function ($model) {
            return new Table(['所在城市', '门店编号', '门店名称', '负责人', '负责人电话', '门店地址'], [0 => [
                $model->s_city,
                $model->s_number,
                $model->s_name,
                $model->s_manager,
                $model->s_manager_phone,
                $model->s_address
            ]]);
        });
        $grid->up_number('快递单号')->editable();
        $grid->up_updated('时间');

        $grid->disableCreateButton();
        $grid->disableRowSelector();
//        $grid->disableExport();

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->equal('p_type', '礼品类型')->select((new Prize())->prizeType);
            $filter->equal('up_received', '是否核销')->select([0 => '未核销', 1 => '已核销']);
            $filter->equal('up_shop_id', '核销门店')->select('/admin/shops_list');
        });
        $grid->disableActions();

        return $grid;
    }

    /**
     * 更新快递单号
     * @param $id
     * @param Request $request
     */
    public function updateUserPrize($id, Request $request)
    {
        $input = $request->input();
        $userPrize = UserPrize::find($id);
        if ($userPrize) {
            if (empty($userPrize->up_number)) {
                Prize::where(['p_id' => $userPrize->up_prize_id])->increment('p_used_number', 1);
                $update['up_received'] = 1;
            }
            $update[$input['name']] = $input['value'];
            UserPrize::where('up_id', $id)->update($update);
        }
    }
}
