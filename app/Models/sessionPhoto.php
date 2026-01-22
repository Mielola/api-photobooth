<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class sessionPhoto extends Model
{
    //
    use HasUlid;

    protected $table = 'table_session_photo';

    protected $hidden = ['id'];

    protected $fillable = [
        'uid',
        'type',
        'photo_path',
        'session_id',
        'created_at',
        'updated_at',
    ];
}
