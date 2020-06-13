<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Advert extends Model
{
    protected $primaryKey = 'advert_id';
    protected $fillable = ['user_id','plan_id','property_id', 'condo', 'price', 'price_max', 'transaction', 'status', 'user_picture', 'phone', 'email',
        'site', 'view_count', 'message_count', 'call_count', 'advert_type', 'facebook', 'instagram', 'youtube'];

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function property(){
        if($this->advert_type == 'development') {
            return $this->belongsTo(DevelopmentAd::class, 'property_id', 'id');
        } else {
            return $this->belongsTo(Property::class, 'property_id', 'property_id');
        }
    }

    public function development() {
        return $this->belongsTo(DevelopmentAd::class, 'property_id', 'id');
    }

    public function plan(){
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }
}
