<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    //
    protected $fillable = ['property_type', 'title', 'description', 'complement',
                          'cep','street', 'neighborhood','number',
                          'city', 'state','lat','lng', 'rooms',
                          'bathrooms', 'parking_spaces',
                          'area','picture','user_id', 'development_id',
                          'quantity', 'price', 'video', 'blueprint', 'tour', 'external_register_id'];

   // protected $hidden = ['user_id'];
    protected $primaryKey = 'property_id';

    public function adverts(){
        return $this->hasMany(Advert::class, 'property_id', 'property_id')->where('advert_type', '!=', 'development');
    }
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
    public function development() {
        return $this->belongsTo(DevelopmentAd::class, 'development_id', 'id');
    }
}







