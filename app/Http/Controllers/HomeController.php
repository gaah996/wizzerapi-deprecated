<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Advert;

class HomeController extends Controller
{
    public function spotlight(){
        $advertsRecent = Advert::where('status', '1')->
        orderBy('created_at', 'desc')->
        limit(8)->
        get();

        $advertsRent = Advert::where('status', '1')->
        where('transaction', 'alugar')->
        orderBy('price')->
        limit(8)->
        get();

        $advertsSell = Advert::where('status', '1')->
        where('transaction', 'vender')->
        orderBy('price')->
        limit(8)->
        get();

        foreach($advertsRecent as $advert){
            $advert->property;
            $advert->property->property_type = json_decode($advert->property->property_type);
            $advert->property->picture = json_decode($advert->property->picture);
            $advert->phone = json_decode($advert->phone);
            $advert->days_on_wizzer = (now()->diffInDays($advert->created_at)) + 1;
        }
        foreach($advertsRent as $advert){
            $advert->property;
            $advert->property->property_type = json_decode($advert->property->property_type);
            $advert->property->picture = json_decode($advert->property->picture);
            $advert->phone = json_decode($advert->phone);
            $advert->days_on_wizzer = (now()->diffInDays($advert->created_at)) + 1;
        }
        foreach($advertsSell as $advert){
            $advert->property;
            $advert->property->property_type = json_decode($advert->property->property_type);
            $advert->property->picture = json_decode($advert->property->picture);
            $advert->phone = json_decode($advert->phone);
            $advert->days_on_wizzer = (now()->diffInDays($advert->created_at)) + 1;
        }

        return response()->json([
            'recent' => $advertsRecent,
            'rent' => $advertsRent,
            'sell' => $advertsSell
        ], 200);
    }
}