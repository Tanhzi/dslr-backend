<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'template';
    public $timestamps = false;

    protected $fillable = ['id','frame', 'type', 'id_admin', 'id_topic', 'cuts'];
}