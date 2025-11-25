<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sticker extends Model
{
    protected $table = 'stickers';
    public $timestamps = true;  // Có created_at/updated_at
    protected $fillable = ['id_admin', 'id_topic', 'sticker', 'type'];
}