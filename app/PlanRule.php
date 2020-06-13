<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlanRule extends Model
{
    protected $fillable = ['description', 'discount_code', 'profile_type', 'adverts_number', 'images_per_advert', 'price', 'validity', 'renewable', 'user_id'];
    protected $primaryKey = 'plan_rule_id';

    public function plans(){
        return $this->hasMany(Plan::class, 'plan_rule_id', 'plan_rule_id');
    }

    public function discountCodes() {
        return $this->belongsToMany(DiscountCode::class, 'discount_code_plan_rule', 'plan_rule_id', 'discount_code_id');
    }
}