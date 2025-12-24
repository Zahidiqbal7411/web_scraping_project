<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'integer'; // Rightmove IDs are like 165671282 (9 digits). PHP int handles it fine.

    protected $fillable = [
        'id',
        'location',
        'house_number',
        'road_name',
        'price',
        'key_features',
        'description',
        'sold_link',
        'filter_id',
        'bedrooms',
        'bathrooms',
        'property_type',
        'size',
        'tenure',
        'council_tax',
        'parking',
        'garden',
        'accessibility',
        'ground_rent',
        'annual_service_charge',
        'lease_length'
    ];

    protected $casts = [
        'key_features' => 'array',
    ];

    public function images()
    {
        return $this->hasMany(PropertyImage::class, 'property_id', 'id');
    }

    public function savedSearch()
    {
        return $this->belongsTo(SavedSearch::class, 'filter_id');
    }

    public function soldProperties()
    {
        // Match sold records by the parent id
        return $this->hasMany(PropertySold::class, 'property_id', 'id');
    }
}
