<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTopic extends Model
{
    protected $table = 'ai_topics';
    public $timestamps = true;
    protected $fillable = ['id_admin', 'name', 'type', 'illustration', 'is_prompt', 'status'];
}