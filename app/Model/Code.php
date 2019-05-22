<?php

namespace App\model;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    const CREATED_AT = 'c_created';

    protected $primaryKey = 'c_id';

    public $timestamps = false;
}
