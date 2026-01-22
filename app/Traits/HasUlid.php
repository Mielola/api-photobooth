<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUlid
{
    protected static function bootHasUlid()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uid';
    }
}