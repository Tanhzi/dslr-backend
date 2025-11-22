<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $table = 'ratings';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'quality',
        'smoothness',
        'photo',
        'service',
        'comment',
        'id_admin',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'quality' => 'integer',
        'smoothness' => 'integer',
        'photo' => 'integer',
        'service' => 'integer',
        'id_admin' => 'integer',
    ];

    public function admin()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_admin');
    }
}