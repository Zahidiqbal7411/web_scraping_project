<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertySold extends Model
{
    use HasFactory;

    protected $table = 'properties_sold';

    protected $fillable = [
        'property_id',
        'location',
        'source_sold_link',
        'house_number',
        'road_name',
        'image_url',
        'images', // Add to fillable
        'property_type',
        'bedrooms',
        'bathrooms',
        'tenure',
        'detail_url',
        'map_url'
    ];
    
    protected $casts = [
        'images' => 'array', // Cast to array
    ];

    public function prices()
    {
        return $this->hasMany(PropertySoldPrice::class, 'sold_property_id');
    }
}
