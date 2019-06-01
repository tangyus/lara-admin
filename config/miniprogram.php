<?php
return [
    // 医路向前 wx4ac67a654ba9a4be dda090908b78b71aac95c3acad49f1fc
    // 佳得乐 wx82de84528e164c9b 74f2e0be5ec582ba0b858c8c6bb7fd47
	// TY	wx702c77029a2fe36a	433f4d2279f4203ad953ad729c296d5e
    'app_id' => 'wx702c77029a2fe36a',
	'secret' => '433f4d2279f4203ad953ad729c296d5e',

	// 下面为可选项
	// 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
	'response_type' => 'array',

	'log' => [
		'level' => 'debug',
		'file' => public_path().'/wechat.log',
	],
];