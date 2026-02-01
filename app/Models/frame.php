<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class frame extends Model
{
    //
    use HasUlid;

    protected $table = 'table_frame';

    protected $hidden = ['id'];

    protected $appends = ['photo_url'];

    protected $fillable = [
        'uid',
        'nama_frame',
        'photo',
        'jumlah_foto',
        'acara_id',
        'created_at',
        'updated_at',
    ];
    public function getPhotoUrlAttribute()
    {
        return $this->photo
            ? Storage::disk('public')->url($this->photo)
            : null;
    }
    public function acara()
    {
        return $this->belongsTo(Acara::class, 'acara_id');
    }
}
