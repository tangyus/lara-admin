<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    const CREATED_AT = 'a_created';
    const UPDATED_AT = 'a_updated';

    protected $primaryKey = 'a_id';

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }
}
