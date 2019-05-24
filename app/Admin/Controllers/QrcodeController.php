<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\QrcodeExporter;
use App\model\Account;
use App\model\Code;
use App\Model\Qrcode;
use App\Http\Controllers\Controller;
use Encore\Admin\Auth\Permission;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

class QrcodeController extends Controller
{
    use HasResourceActions;

    protected $memberDate = [
        1 => '每周一', 2 => '每周二', 3 => '每周三', 4 => '每周四',
        5 => '每周五', 6 => '每周六', 7 => '每周日'
    ];

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        Permission::check('qrcodes.index');

        return $content
            ->header('二维码记录')
            ->description('记录列表')
            ->breadcrumb(
                ['text' => '二维码记录', 'url' => '/qrcodes'],
                ['text' => '记录列表']
            )
            ->body($this->grid());
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        Permission::check('qrcodes.create');

        return $content
            ->header('二维码记录')
            ->description('生成')
            ->breadcrumb(
                ['text' => '二维码记录', 'url' => '/qrcodes'],
                ['text' => '生成']
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
        $grid = new Grid(new Qrcode);
        if (Admin::user()->isRole('市场人员')) {
            // 修改数据来源
            $account = Account::where('a_account', Admin::user()->username)->first();
            $grid->model()->where('q_account_id', $account->a_id);
        }
        $grid->exporter(new QrcodeExporter());

        $grid->q_id('ID');
        if (Admin::user()->inRoles(['administrator', '后台管理员'])) {
            $grid->district()->a_district('区域');
            $grid->district()->a_city('城市');
            $grid->district()->a_manager('区域负责人姓名');
            $grid->district()->a_manager_phone('区域负责人电话');
        }
        $grid->q_city('生成城市');
        $grid->q_number('生成数量');
        $grid->q_point('扫码积分');
        $grid->q_member_date('会员日')->using($this->memberDate);
        $grid->q_expired('二维码有效期');
        $grid->q_created('生成时间');

        $grid->disableRowSelector();
        $grid->disableFilter();
        if (Admin::user()->cannot('qrcodes.create')) {
            $grid->disableCreateButton();
        }

        $grid->actions(function ($actions) {
            $actions->disableEdit();
            $actions->disableDelete();
            $actions->disableView();

            $row = $actions->row;
            $actions->append('<a href="'.$row->q_zip_path.'" target="_blank"><i class="fa fa-cloud-download">下载二维码</i></a>');
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
        $form = new Form(new Qrcode);

        if (Admin::user()->isRole('市场人员')) {
            $form->hidden('q_account_id');
            $account = Account::where('a_account', Admin::user()->username)->first();
            $form->saving(function ($form) use ($account) {
                $form->input('q_account_id', $account->a_id);
            });

            $form->text('q_district', '区域')->default($account->a_district)->attribute(['disabled' => true]);
        } else {
            $form->select('q_account_id', '区域')->options('/admin/accounts_list')->rules('required', ['required' => '请选择区域']);
        }

        $form->text('q_city', '城市')->rules('required', ['required' => '请输入城市']);
        $form->text('q_number', '生成数量')->rules('required', ['required' => '请输入生成数量']);
        $form->datetime('q_expired', '二维码有效期')->rules('required', ['required' => '请选择二维码有效期']);
        $form->radio('q_member_date', '会员日')->options($this->memberDate)->rules('required', ['required' => '请选择会员日']);
        $form->number('q_point', '扫码积分')->min(5)->default(5);

        $form->ignore('q_district');

        // 下载导出二维码
        $form->saved(function (Form $form) {
            $this->zipCodes($form->model());
        });

        return $form;
    }

    protected function zipCodes($model)
    {
        $codes = Code::whereNull('c_qrcode_id')->limit($model->q_number)->get();
        if (count($codes) > 0) {
            $publicPath = public_path();
            $path = $publicPath . '/download/codes';
            $zipPath = $path . "/{$model->q_id}.zip";
            $ids = [];

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
                foreach ($codes as $codeFile) {
                    if (file_exists($publicPath . $codeFile->c_path)) {
                        // 将文件加入zip对象
                        $ids[] = $codeFile['c_id'];
                        $zip->addFile($publicPath . '/' . $codeFile->c_path, $codeFile->c_filename);
                    }
                }
                $zip->close(); // 关闭处理的zip文件

                Code::whereIn('c_id', $ids)->limit($model->q_number)->update(['c_qrcode_id' => $model->q_id]);

                $model->q_zip_path = "/download/codes/{$model->q_id}.zip";
                $model->save();
            }
        }
    }
}
