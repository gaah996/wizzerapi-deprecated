<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    protected $fillable = ['type','code', 'status', 'amount', 'last_event_date', 'scheduling_date', 'transactions', 'plan_id'];

    public function plan() {
        return $this->belongsTo(\App\Plan::class, 'plan_id', 'plan_id');
    }
}
