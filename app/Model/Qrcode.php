<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Qrcode extends Model
{
    const CREATED_AT = 'q_created';
    const UPDATED_AT = 'q_updated';

    protected $primaryKey = 'q_id';

    public function district()
    {
        return $this->belongsTo(Account::class, 'q_account_id');
    }
}
