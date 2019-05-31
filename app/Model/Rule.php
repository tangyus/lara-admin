<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    const CREATED_AT = 'r_created';
    const UPDATED_AT = 'r_updated';

    protected $primaryKey = 'r_id';

    public function district()
    {
        return $this->belongsTo(Account::class, 'r_account_id');
    }
}