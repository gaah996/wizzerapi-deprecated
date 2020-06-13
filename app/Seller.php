<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    protected $fillable = ['avatar', 'name', 'phones', 'emails', 'site', 'creci', 'view_count', 'development_ad_id'];

    public function development() {
        return $this->belongsTo(DevelopmentAd::class);
    }
}
