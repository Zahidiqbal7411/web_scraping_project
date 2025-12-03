<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'address',
        'property_url',
        'image_urls',
        'main_image',
    ];

    protected $casts = [
        'image_urls' => 'array',
    ];
}
