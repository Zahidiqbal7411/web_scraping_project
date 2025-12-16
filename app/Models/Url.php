<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    protected $fillable = ['url', 'filter_id'];

    public function savedSearch()
    {
        return $this->belongsTo(SavedSearch::class, 'filter_id');
    }
    
   
}

