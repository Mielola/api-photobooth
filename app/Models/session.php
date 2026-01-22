<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class session extends Model
{
    //
    use HasUlid;

    protected $hidden = ['id'];

    protected $fillable = [
        'uid',
        'acara_id',
        'expired_time',
        'created_at',
        'updated_at',
    ];
}
