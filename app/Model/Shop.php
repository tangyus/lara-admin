<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    const CREATED_AT = 's_created';
    const UPDATED_AT = 's_updated';

    protected $primaryKey = 's_id';

    public function district()
    {
        return $this->belongsTo(Account::class, 's_account_id');
    }
}
