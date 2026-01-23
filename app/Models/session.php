<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class session extends Model
{
    //
    use HasUlid;

    protected $table = 'table_session';

    protected $hidden = ['id'];

    protected $fillable = [
        'uid',
        'acara_id',
        'email',
        'expired_time',
        'created_at',
        'updated_at',
    ];

    /**
     * Relasi ke tabel acara
     */
    public function acara()
    {
        return $this->belongsTo(acara::class, 'acara_id');
    }

    /**
     * Relasi ke tabel session_photo
     */
    public function photos()
    {
        return $this->hasMany(sessionPhoto::class, 'session_id');
    }
}
