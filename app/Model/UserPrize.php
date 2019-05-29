<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserPrize extends Model
{
    const CREATED_AT = 'up_created';
    const UPDATED_AT = 'up_updated';

    protected $primaryKey = 'up_id';

    protected $guarded = [];

    public function prize()
    {
        return $this->belongsTo(Prize::class, 'up_prize_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'up_uid');
    }
}