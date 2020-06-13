<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    //php artisan migrate:refresh

    protected $primaryKey = 'user_id';
    protected $fillable = ['avatar', 'name', 'email', 'password', 'profile_type', 'cpf_cnpj', 'creci', 'phone', 'site'];
    protected $hidden = ['password'];

    public function adverts(){
        return $this->hasMany(Advert::class, 'user_id', 'user_id');
    }

    public function properties(){
        return $this->hasMany(Property::class, 'user_id', 'user_id');
    }

    public function plans(){
        return $this->hasMany(Plan::class, 'user_id', 'user_id');
    }
}
