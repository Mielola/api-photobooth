<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class frame extends Model
{
    //
    use HasUlid;

    protected $table = 'table_frame';

    protected $hidden = ['id'];

    protected $fillable = [
        'uid',
        'nama_frame',
        'jumlah_foto',
        'acara_id',
        'created_at',
        'updated_at',
    ];
}
