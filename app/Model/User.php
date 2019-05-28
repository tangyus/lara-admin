<?php

namespace App\Model;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $primaryKey = 'u_id';
    const CREATED_AT = 'u_created';
    const UPDATED_AT = 'u_updated';

    protected $guarded = [];

    public function district()
    {
        return $this->belongsTo(Account::class, 'u_account_id');
    }
}
