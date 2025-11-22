<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'discount';
    public $timestamps = false;

    protected $fillable = [
        'code', 'value', 'quantity', 'count_quantity',
        'startDate', 'endDate', 'id_admin'
    ];

    protected $casts = [
        'startDate' => 'date:Y-m-d',
        'endDate'   => 'date:Y-m-d',
    ];
}