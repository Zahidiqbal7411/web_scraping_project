<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    protected $fillable = [
        'area',
        'min_price',
        'max_price',
        'min_bed',
        'max_bed',
        'min_bath',
        'max_bath',
        'property_type',
        'tenure_types',
        'must_have',
        'dont_show',
        'updates_url',
        'max_days_since_added',
        'include_sstc',
    ];

    public function properties()
    {
        return $this->belongsToMany(Property::class, 'property_saved_search', 'saved_search_id', 'property_id');
    }
}
