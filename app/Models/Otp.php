<?php

namespace App\Models;

use App\Traits\HasUlid;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasUlid;
    protected $hidden = ['id'];


    protected $fillable = [
        'uid',
        'user_id',
        'otp',
        'type',
        'expired_at'
    ];
}
