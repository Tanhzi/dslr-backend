<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    protected $table = 'camera';
    public $timestamps = false;

    protected $fillable = [
        'id_admin', 'time1', 'time2', 'video', 'mirror', 'time_run'
    ];

    // Tự động ép kiểu khi truy cập
    protected $casts = [
        'time1' => 'integer',
        'time2' => 'integer',
        'video' => 'integer',
        'mirror' => 'integer',
        'time_run' => 'string',
    ];
}