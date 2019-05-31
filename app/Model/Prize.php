<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    const CREATED_AT = 'p_created';
    const UPDATED_AT = 'p_updated';

    const NEW_PRIZE = '闪电新人礼';
    const ADVANCE_PRIZE = '闪电进阶礼';
    const LEGEND_PRIZE = '闪电传奇礼';
    const EXCHANGE_PRIZE = '闪电兑换礼';
    const MEMBER_PRIZE = '闪电会员礼';

    protected $primaryKey = 'p_id';

    public $prizeType = [
        self::NEW_PRIZE => self::NEW_PRIZE,
        self::ADVANCE_PRIZE => self::ADVANCE_PRIZE,
        self::LEGEND_PRIZE => self::LEGEND_PRIZE,
        self::EXCHANGE_PRIZE => self::EXCHANGE_PRIZE,
        self::MEMBER_PRIZE => self::MEMBER_PRIZE,
    ];

    public function district()
    {
        return $this->belongsTo(Account::class, 'p_account_id');
    }
}
