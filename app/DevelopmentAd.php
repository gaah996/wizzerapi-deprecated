<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DevelopmentAd extends Model
{
    protected $fillable = ['logo', 'title', 'description', 'number', 'street', 'neighborhood', 'city',
        'state', 'cep', 'lat', 'lng', 'datasheet', 'work_stage', 'due_date', 'picture', 'video', 'background', 'type'];

    public function properties() {
        return $this->hasMany(Property::class, 'development_id', 'id');
    }

    public function advert() {
        return $this->belongsTo(Advert::class, 'id', 'property_id')->where('advert_type', 'development');
    }

    public function sellers() {
        return $this->hasMany(Seller::class);
    }
}
