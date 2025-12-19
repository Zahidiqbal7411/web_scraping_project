<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $primaryKey = 'property_id';
    public $incrementing = false;
    protected $keyType = 'string'; // Usually BigInt is string in PHP if > int max, but string is safe for direct IDs. Though 'unsignedBigInteger' fits in PHP int on 64bit. Safe to set keyType = int?
    // User asked for unsignedBigInt 20. standard PHP int is 64 bit.
    // Rightmove IDs are like 165671282 (9 digits). PHP int handles it fine.

    protected $fillable = [
        'property_id',
        'location',
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
        return $this->hasMany(PropertyImage::class, 'property_id', 'property_id');
    }

    public function savedSearch()
    {
        return $this->belongsTo(SavedSearch::class, 'filter_id');
    }

    public function soldProperties()
    {
        // Match sold records by the parent property_id
        return $this->hasMany(PropertySold::class, 'property_id', 'property_id');
    }
}
