<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertySoldPrice extends Model
{
    use HasFactory;

    protected $table = 'properties_sold_prices';

    protected $fillable = [
        'sold_property_id',
        'sold_price',
        'sold_date'
    ];

    public function propertySold()
    {
        return $this->belongsTo(PropertySold::class, 'sold_property_id');
    }
}
