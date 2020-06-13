<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    protected $fillable = ['cron_signature', 'log', 'user_id', 'plan_id'];
}
