<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class acara extends Model
{
    use HasUlid;

    protected $table = 'table_acara';

    protected $hidden = ['id'];

    protected $fillable = [
        'uid',
        'nama_acara',
        'nama_pengantin_pria',
        'nama_pengantin_wanita',
        'tanggal',
        'status',
        'background',
        'created_at',
        'updated_at',
    ];
}
