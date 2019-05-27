<?php

namespace App\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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

    public function setAttributes(...$args)
    {
        return [
            'pr_uid'            => Auth::id(),
            'pr_prize_type'     => $args[0],
            'pr_prize_name'     => $args[1],
            'pr_received'       => $args[2],
            'pr_point'          => $args[3],
            'pr_current_point'  => $args[4],
            'pr_created'        => Carbon::now(),
            'pr_updated'        => Carbon::now()
        ];
    }
}