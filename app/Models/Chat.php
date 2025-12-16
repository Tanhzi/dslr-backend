<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $table = 'content_chat';
    public $timestamps = true;

    protected $fillable = [
        'id_admin', 'title', 'content'
    ];
}