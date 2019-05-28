<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    const CREATED_AT = 'p_created';
    const UPDATED_AT = 'p_updated';

    protected $primaryKey = 'p_id';

    public $prizeType = [
        '闪电新人礼' => '闪电新人礼',
        '闪电进阶礼' => '闪电进阶礼',
        '闪电传奇礼' => '闪电传奇礼',
        '闪电兑换礼' => '闪电兑换礼',
        '闪电会员礼' => '闪电会员礼'
    ];

    public function district()
    {
        return $this->belongsTo(Account::class, 'p_account_id');
    }
}
