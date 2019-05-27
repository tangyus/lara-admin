<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    const CREATED_AT = 'c_created';
    const UPDATED_AT = 'c_updated';

    protected $primaryKey = 'c_id';

    public function city()
    {
        return $this->belongsTo(Qrcode::class, 'c_qrcode_id');
    }
}
