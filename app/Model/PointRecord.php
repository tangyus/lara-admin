<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PointRecord extends Model
{
    const CREATED_AT = 'pr_created';
    const UPDATED_AT = 'pr_updated';

    protected $primaryKey = 'pr_id';

    public function user()
    {
        return $this->belongsTo(User::class, 'pr_uid');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'pr_shop_id');
    }
}