<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    const CREATED_AT = 'p_created';
    const UPDATED_AT = 'p_updated';

    protected $primaryKey = 'p_id';

    public $prizeType = [
        1 => '闪电进阶礼',
        2 => '闪电传奇礼',
        3 => '闪电积分礼',
        4 => '闪电会员礼'
    ];

    public function district()
    {
        return $this->belongsTo(Account::class, 'p_account_id');
    }
}
