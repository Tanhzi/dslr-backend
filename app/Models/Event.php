<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'event'; 
    public $timestamps = false; 

    protected $fillable = [
        'id', 'name', 'date', 'background', 'ev_back',
        'logo', 'ev_logo', 'note1', 'note2', 'note3', 'ev_note', 'id_admin','apply'
    ];

    protected $casts = [
        'ev_back' => 'integer',
        'ev_logo' => 'integer',
        'ev_note' => 'integer',
        'apply'   => 'array', // Laravel sẽ tự động serialize/deserialize
    ];
}