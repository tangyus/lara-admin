<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\ShopExporter;
use App\model\Account;
use App\Model\Shop;
use App\Http\Controllers\Controller;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;

class ShopController extends Controller
{
    use HasResourceActions;

    protected $states = [
        'on' => ['value' => 1, 'text' => '是', 'color' => 'primary'],
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
        Permission::check('shops.list');

        return $content
            ->header('区域门店')
            ->description('门店列表')
            ->breadcrumb(
                ['text' => '区域门店', 'url' => '/shops'],
                ['text' => '门店列表']
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
        Permission::check('shops.list');

        return $content
            ->header('区域门店')
            ->description('详情')
            ->breadcrumb(
                ['text' => '区域门店', 'url' => '/shops'],
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
        Permission::check('shops.create');

        return $content
            ->header('区域门店')
            ->description('编辑')
            ->breadcrumb(
                ['text' => '区域门店', 'url' => '/shops'],
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
        Permission::check('shops.create');

        return $content
            ->header('区域门店')
            ->description('新建')
            ->breadcrumb(
                ['text' => '区域门店', 'url' => '/shops'],
                ['text' => '新建']
            )
            ->body($this->form());
    }

    /**
     * 门店列表
     * @return mixed
     */
    public function shopsList()
    {
        return Shop::where(function ($query) {
                if (Admin::user()->isRole('市场人员')) {
                    $account = Account::where('a_account', Admin::user()->username)->first();
                    $query->where('s_account_id', $account->a_id);
                }
                $query->where('s_state', 0);
            })
            ->get(['s_id as id', 's_name as text']);
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Shop);
        if (Admin::user()->isRole('市场人员')) {
            // 修改数据来源
            $account = Account::where('a_account', Admin::user()->username)->first();
            $grid->model()->where('s_account_id', $account->a_id);
        }
        $grid->exporter(new ShopExporter());

        $grid->s_id('ID');
        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $grid->district()->a_district('区域')->expand(function ($model) {
                $info = $model->district()->get()->map(function ($item) {
                    return $item->only(['a_district', 'a_city', 'a_manager', 'a_manager_phone']);
                });
                return new Table(['区域', '城市', '区域负责人姓名', '区域负责人电话'], $info->toArray());
            });
        }
        $grid->s_city('门店所在城市');
        $grid->s_number('门店序号');
        $grid->s_name('门店名称');
        $grid->s_phone('门店联系电话');
        $grid->s_target('销售目标')->modal(function () {
            return new Table(['月份', '销量目标', '实际销量'], [
                0 => [6, $this->s_target_6, $this->s_sales_6],
                1 => [7, $this->s_target_7, $this->s_sales_7],
                2 => [8, $this->s_target_8, $this->s_sales_8],
                3 => [9, $this->s_target_9, $this->s_sales_9],
            ]);
        });
        $grid->s_manager('门店负责人姓名');
        $grid->s_manager_phone('门店负责人电话');
        $grid->s_password('门店核销密码');
        if (Admin::user()->cannot('qrcodes.create')) {
            $grid->s_state('是否停用')->using(['否', '是']);
            $grid->disableCreateButton();
        } else {
            $grid->s_state('是否停用')->switch($this->states);
        }
        $grid->s_created('创建时间');
        $grid->s_updated('修改时间');

        // 数据查询过滤
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
                $filter->column(1 / 2, function ($filter) {
                    $filter->equal('s_account_id', '区域')->select('/admin/accounts_list');

                    $filter->where(function ($query) {
                        $query->whereHas('district', function ($query) {
                            $query->where('a_city', $this->input);
                        });
                    }, '城市');
                    $filter->where(function ($query) {
                        $query->whereHas('district', function ($query) {
                            $query->where('a_manager_phone', $this->input);
                        });
                    }, '区域负责人联系电话');
                });
            }

            $filter->column(1 / 2, function ($filter) {
                $filter->equal('s_number', '门店序号');
                $filter->like('s_name', '门店名称');
                $filter->equal('s_manager_phone', '门店负责人电话');
            });
        });

        $grid->disableRowSelector();
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            if (Admin::user()->cannot('qrcodes.create')) {
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
        $show = new Show(Shop::findOrFail($id));

        $show->s_number('门店序号');
        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $show->district('区域', function ($district) {
                $district->a_district('区域');
                $district->a_manager('区域负责人姓名');
                $district->a_manager_phone('区域联系电话');
            });
        }

        $show->s_name('门店名称');
        $show->s_city('门店所在城市');
        $show->s_number('门店序号');
        $show->s_phone('门店联系电话');
        $show->s_manager('门店负责人姓名');
        $show->s_manager_phone('门店负责人电话');
        $show->s_password('门店核销密码');
        $show->s_state('是否停用')->using([0 => '否', 1 => '是']);
        $show->s_created('创建时间');
        $show->s_updated('修改时间');

        $show->panel()->tools(function ($tools) {
            if (Admin::user()->can('qrcodes.create')) {
                $tools->disableEdit(false);
            }
            $tools->disableList(false);
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
        $form = new Form(new Shop);

        if ($id) {
            $form->text('district.a_district', '区域')->disable();
            $form->text('s_city', '城市')->disable();
            $form->text('s_number', '门店序号')->disable();
        } else {
            if (Admin::user()->isRole('市场人员')) {
                $account = Account::where('a_account', Admin::user()->username)->first();
                $form->text('s_district', '区域')->default($account->a_district)->disable();
                $shopCount = Shop::where('s_account_id', $account->a_id)->count();

                $number = pinyin($account->a_district).'-'.pinyin($account->a_city).str_pad($shopCount + 1, 6, '0', STR_PAD_LEFT);
                $form->text('s_city', '城市')->default($account->a_city)->disable();
                $form->text('s_number', '门店序号')->default($number)->disable();

                $form->hidden('s_account_id');
                $form->hidden('s_city');
                $form->hidden('s_number');

                $form->saving(function (Form $form) use ($account, $number) {
                    $form->input('s_account_id', $account->a_id);
                    $form->input('s_city', $account->a_city);
                    $form->input('s_number', $number);
                });
            } else {
                $form->select('s_account_id', '区域')->options('/admin/accounts_list')->rules('required', ['required' => '请选择区域']);
                $form->text('s_number', '门店序号')->rules('required', ['required' => '请输入门店序号']);
            }
        }

        $targetRules = ['required' => '请输入销售目标', 'integer' => '销售目标必须为整数', 'min' => '销售目标不能为负数'];
        $salesRules = ['integer' => '实际销量必须为整数', 'min' => '实际销量不能为负数'];
        $form->tab('基础信息', function ($form) {
            $form->text('s_name', '门店名称')->rules('required', ['required' => '请输入门店名称']);
            $form->text('s_phone', '门店联系电话')->rules('required', ['required' => '请输入门店所在城市']);
            $form->text('s_manager', '门店负责人')->rules('required', ['required' => '请输入门店联系人姓名']);
            $form->text('s_manager_phone', '负责人电话')->rules('required|regex:/^[1][3,4,5,6,7,8,9][0-9]{9}$/', [
                'required'  => '请输入门店负责人电话',
                'regex'     => '电话号码非法'
            ]);
            $form->text('s_address', '门店地址')->rules('required', ['required' => '请输入门店地址']);
            $form->text('s_password', '门店核销密码')->placeholder('市场人员与门店负责人确认，建议设置为门店负责人手机号')->rules('required', ['required' => '请输入门店核销密码']);
            $form->switch('s_state', '是否停用')->states($this->states);
        })->tab('6月销售目标', function ($form) use ($targetRules, $salesRules) {
            $form->text('s_target_6', '6月销售目标')->default(0);
            $form->text('s_sales_6', '6月实际销量')->default(0)->rules('integer|min:0');
        })->tab('7月销售目标', function ($form) use ($targetRules, $salesRules) {
            $form->text('s_target_7', '7月销售目标')->default(0);
            $form->text('s_sales_7', '7月实际销量')->default(0)->rules('integer|min:0', $salesRules);
        })->tab('8月销售目标', function ($form) use ($targetRules, $salesRules) {
            $form->text('s_target_8', '8月销售目标')->default(0);
            $form->text('s_sales_8', '8月实际销量')->default(0)->rules('integer|min:0', $salesRules);
        })->tab('9月销售目标', function ($form) use ($targetRules, $salesRules) {
            $form->text('s_target_9', '9月销售目标')->default(0);
            $form->text('s_sales_9', '9月实际销量')->default(0)->rules('integer|min:0', $salesRules);
        });

        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
        });

        return $form;
    }
}

/**
 * 获取单个汉字拼音首字母。注意:此处不要纠结。汉字拼音是没有以U和V开头的
 * @param $ch
 * @return null|string
 */
function firstChar($ch) {
    $char = ord($ch{0});
    if ($char >= ord('A') and $char <= ord('z')) return strtoupper($ch{0});

    $asc = ord($ch{0}) * 256 + ord($ch{1}) - 65536;
    if ($asc >= -20319 and $asc <= -20284) return 'A';
    if ($asc >= -20283 and $asc <= -19776) return 'B';
    if ($asc >= -19775 and $asc <= -19219) return 'C';
    if ($asc >= -19218 and $asc <= -18711) return 'D';
    if ($asc >= -18710 and $asc <= -18527) return 'E';
    if ($asc >= -18526 and $asc <= -18240) return 'F';
    if ($asc >= -18239 and $asc <= -17923) return 'G';
    if ($asc >= -17922 and $asc <= -17418) return 'H';
    if ($asc >= -17922 and $asc <= -17418) return 'I';
    if ($asc >= -17417 and $asc <= -16475) return 'J';
    if ($asc >= -16474 and $asc <= -16213) return 'K';
    if ($asc >= -16212 and $asc <= -15641) return 'L';
    if ($asc >= -15640 and $asc <= -15166) return 'M';
    if ($asc >= -15165 and $asc <= -14923) return 'N';
    if ($asc >= -14922 and $asc <= -14915) return 'O';
    if ($asc >= -14914 and $asc <= -14631) return 'P';
    if ($asc >= -14630 and $asc <= -14150) return 'Q';
    if ($asc >= -14149 and $asc <= -14091) return 'R';
    if ($asc >= -14090 and $asc <= -13319) return 'S';
    if ($asc >= -13318 and $asc <= -12839) return 'T';
    if ($asc >= -12838 and $asc <= -12557) return 'W';
    if ($asc >= -12556 and $asc <= -11848) return 'X';
    if ($asc >= -11847 and $asc <= -11056) return 'Y';
    if ($asc >= -11055 and $asc <= -10247) return 'Z';

    return NULL;
}

/**
 * 获取整条字符串所有汉字拼音首字母
 * @param $zh
 * @return string
 */
function pinyin($zh) {
    $ret = '';
    $s1 = iconv('UTF-8', 'gb2312', $zh);
    $s2 = iconv('gb2312', 'UTF-8', $s1);
    if ($s2 == $zh) {
        $zh = $s1;
    }
    for ($i = 0; $i < strlen($zh); $i++) {
        $s1 = substr($zh, $i, 1);
        $p = ord($s1);
        if ($p > 160) {
            $s2 = substr($zh, $i++, 2);
            $ret .= firstChar($s2);
        } else {
            $ret .= $s1;
        }
    }
    return $ret;
}