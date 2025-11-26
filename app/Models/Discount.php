<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'discount';
    public $timestamps = false;

    protected $fillable = [
        'code', 'value', 'quantity', 'count_quantity',
        'startdate', 'enddate', 'id_admin'
    ];

    protected $casts = [
        'startdate' => 'date:Y-m-d',
        'enddate'   => 'date:Y-m-d',
    ];
}