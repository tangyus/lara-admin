<?php
return [
    // 医路向前 wx4ac67a654ba9a4be dda090908b78b71aac95c3acad49f1fc
	'app_id' => 'wx4ac67a654ba9a4be',
	'secret' => 'dda090908b78b71aac95c3acad49f1fc',

	// 下面为可选项
	// 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
	'response_type' => 'array',

	'log' => [
		'level' => 'debug',
		'file' => public_path().'/wechat.log',
	],
];