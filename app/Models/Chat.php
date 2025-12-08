<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $table = 'content_chat';
    public $timestamps = false;

    protected $fillable = [
        'id_admin', 'title', 'content', 'created_at',
    ];
}