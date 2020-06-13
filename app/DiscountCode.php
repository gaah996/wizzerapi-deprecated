<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    protected $fillable=['discount_code', 'discount_percentage', 'first_buy_only', 'number_of_uses'];
    protected $hidden=['discount_code'];

    public function planRules() {
        return $this->belongsToMany(PlanRule::class, 'discount_code_plan_rule', 'discount_code_id', 'plan_rule_id');
    }
}
