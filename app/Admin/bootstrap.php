<?php

/**
 * Laravel-admin - admin builder based on Laravel.
 * @author z-song <https://github.com/z-song>
 *
 * Bootstraper for Admin.
 *
 * Here you can remove builtin form field:
 * Encore\Admin\Form::forget(['map', 'editor']);
 *
 * Or extend custom form field:
 * Encore\Admin\Form::extend('php', PHPEditor::class);
 *
 * Or require js and css assets:
 * Admin::css('/packages/prettydocs/css/styles.css');
 * Admin::js('/packages/prettydocs/js/main.js');
 *
 */

Encore\Admin\Form::forget(['map', 'editor']);
Encore\Admin\Form::init(function (Encore\Admin\Form $form) {
    $form->footer(function ($footer) {
        $footer->disableViewCheck();
        $footer->disableEditingCheck();
        $footer->disableCreatingCheck();
    });
});

Encore\Admin\Show::init(function (Encore\Admin\Show $show) {
    $show->panel()->tools(function ($tools) {
        $tools->disableEdit();
        $tools->disableDelete();
        $tools->disableList();
    });
});

Admin::js('vendor/laravel-admin-ext/chartjs/Chart.bundle.min.js');
