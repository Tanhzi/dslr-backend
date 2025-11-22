<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pay extends Model
{
    protected $table = 'pays';
    public $timestamps = false;

    protected $fillable = [
        'id_admin', 'id_client', 'price', 'cuts', 'date',
        'discount', 'discount_price', 'discount_code', 'id_frame', 'id_qr','email'
    ];
}