<?php

namespace App\Model;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $primaryKey = 'u_id';

    public function district()
    {
        return $this->belongsTo(Account::class, 'u_account_id');
    }
}
