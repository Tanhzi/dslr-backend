<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';
    public $timestamps = false;

    protected $fillable = [
        'file_path', 'file_type', 'id_admin', 'id_qr', 'created_at', 'link'
    ];

    
}