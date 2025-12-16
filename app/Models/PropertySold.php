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
        'property_type',
        'bedrooms',
        'bathrooms',
        'tenure'
    ];

    public function prices()
    {
        return $this->hasMany(PropertySoldPrice::class, 'sold_property_id');
    }
}
