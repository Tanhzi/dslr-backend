<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $table = 'prices';
    public $timestamps = false;

    protected $fillable = ['id_admin', 'size1', 'size2'];

    protected $casts = [
        'size1' => 'array', 
        'size2' => 'array',
    ];
}