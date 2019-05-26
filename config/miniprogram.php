<?php
return [
	'app_id' => 'wxfe49d510a7e7853b',
	'secret' => '2aadf5a404e444e45408adafee76f2a0',

	// 下面为可选项
	// 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
	'response_type' => 'array',

	'log' => [
		'level' => 'debug',
		'file' => public_path().'/wechat.log',
	],
];