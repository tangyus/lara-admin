<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserPrize extends Model
{
    const CREATED_AT = 'up_created';
    const UPDATED_AT = 'up_updated';

    protected $primaryKey = 'up_id';

    protected $guarded = [];
}