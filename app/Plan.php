<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['user_id', 'plan_rule_id', 'plan_id', 'signature_date', 'payment_id', 'payment_status', 'pagseguro_plan_id', 'discount_code', 'payment_link'];
    protected $primaryKey = 'plan_id';

    /*protected $rules =[
        'validity' => 'required | integer',
        'price'=> 'required|numeric',
        'adverts_number' => 'required|integer',
        'signature_date' =>'required|date|after: 2019-03-01',
        'payment_info' =>'nullable',
       ];*/

   public function user(){
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
    public function planRule(){
        return $this->belongsTo(PlanRule::class, 'plan_rule_id', 'plan_rule_id');
    }
    public function adverts(){
       return $this->hasMany(Advert::class, 'plan_id', 'plan_id');
    }

    public function paymentOrders() {
       return $this->hasMany(\App\PaymentOrder::class, 'plan_id', 'plan_id');
    }
}
