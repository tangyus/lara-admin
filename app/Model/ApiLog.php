<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = ['user_id', 'path', 'method', 'ip', 'input'];

    protected $table = 'api_operation_log';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
