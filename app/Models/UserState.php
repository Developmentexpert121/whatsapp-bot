<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserState extends Model
{
    protected $fillable = [
        'user_id', // Add this line
        'state', // Add any other fields you have
        'current_product_id',
        'pages',
        'product_options'
    ];
}
