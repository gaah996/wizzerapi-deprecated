<?php

use App\CronLog;
use App\Mail\PersonalPlanExpired;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Console\Commands\AlexandresComunication;



//API version 1 unauthenticated routes
Route::prefix('/v1')->group(function (){

    //UsersController routes
    Route::post('/user', 'UsersController@register');
    Route::post('/login', 'UsersController@login');
    Route::post('/reset-password', 'UsersController@sendPasswordResetCode');
    Route::put('/reset-password', 'UsersController@resetPassword');

    //GeocodeAPIController routes
    Route::get('/geocode', 'GeocodeAPIController@getGeocode');

    //SearchController routes
    Route::post('/search/list', 'SearchController@list');
    Route::post('/search/markers', 'SearchController@markers');

    //HomeController routes
//    Route::get('/spotlight', 'HomeController@spotlight');

    //AdvertsController
    Route::post('/prop/view/{advert}', 'AdvertsController@addView');
    Route::post('/prop/call/{advert}', 'AdvertsController@addCall');
    Route::post('/prop/message/{advert}', 'AdvertsController@sendMessage');
    Route::get('/props/{advert}', 'AdvertsController@show');
    Route::get('/share/{advert}', 'AdvertsController@share');

    Route::get('props/{advert}/subprops', 'PropertiesController@getDevelopmentProperties'); //Is it needed?
    Route::get('seller/{seller}', 'SellersController@getSeller')->where('seller', '[0-9]+');

    //PropertiesController routes
    Route::get('/properties', 'PropertiesController@index');
    Route::get('/properties/{property}', 'PropertiesController@show')->where('property', '[0-9]+');;
});


//Route::middleware('web')->prefix('/v1')->group(function(){
//    Route::get('/redirect', 'MediaSocialController@redirect');
//    Route::get('/callback', 'MediaSocialController@callback');
//});

//API version 1 authenticated routes
Route::middleware('auth:api')->prefix('/v1')->group(function (){
    //UsersController routes
    Route::post('/user/profile', 'UsersController@setProfileType');
    Route::get('/user', 'UsersController@show');
    Route::put('/user', 'UsersController@update');
    Route::put('/user/email', 'UsersController@updateEmail');
    Route::put('/user/password', 'UsersController@updatePassword');
    Route::delete('/user', 'UsersController@delete');
    Route::post('/user/picture', 'UsersController@saveProfilePicture');
    Route::delete('/user/picture' , 'UsersController@deleteProfilePicture');
    Route::get('/user/token', 'UsersController@checkToken');

    //AdvertsController routes
    Route::post('/props', 'AdvertsController@selectStore');
//    Route::get('/props', 'AdvertsController@index');
    Route::get('/user/props', 'AdvertsController@getUserAdverts');
    Route::post('/props/{advert}', 'AdvertsController@update');
    Route::delete('/props/{advert}', 'AdvertsController@delete');
    Route::post('props/{advert}/deactivate', 'AdvertsController@deactivate')->where('advert', '[0-9]+');
    Route::post('props/{advert}/activate', 'AdvertsController@activate')->where('advert', '[0-9]+');

    Route::post('props/{advert}/subprop', 'PropertiesController@newProperty')->where('advert', '[0-9]+');
    Route::post('props/{subprop}/update', 'PropertiesController@updateProperty')->where('advert', '[0-9]+');
    Route::delete('props/{subprop}/delete', 'PropertiesController@deleteProperty')->where('advert', '[0-9]+');

    Route::post('props/{advert}/seller', 'SellersController@add')->where('advert', '[0-9]+');
    Route::post('props/{advert}/seller/{seller}', 'SellersController@update')->where(['advert' => '[0-9]+', 'seller' => '[0-9]+']);
    Route::delete('props/{advert}/seller/{seller}', 'SellersController@delete')->where(['advert' => '[0-9]+', 'seller' => '[0-9]+']);

    //PlansController routes
    Route::get('/plans', 'PlansController@index');
    Route::get('/plans/list', 'PlansController@recover');
//    Route::get('/plans/{plan}', 'PlansController@show');
//    Route::put('/plans/{plan}', 'PlansController@update');
//    Route::delete('/plans/{plan}', 'PlansController@delete');

    //DashboardController Routes
    Route::get('/dashboard', 'DashboardController@index');
    Route::post('/support', 'DashboardController@sendEmailSupport');
    Route::post('/custom-plan', 'DashboardController@sendEmailCustomPlan');
    

    //PropertiesController routes
    Route::post('/properties', 'PropertiesController@store');
    Route::post('/properties/{property}', 'PropertiesController@update');
    Route::delete('/properties/{property}', 'PropertiesController@delete');

    //PaymentAPI
    Route::get('/payment/session', 'PagSeguroController@getSessionId');
    Route::post('/payment/pay', 'PagSeguroController@makePayment');
    Route::post('/payment/cancel', 'PagSeguroController@cancelPlan');
    Route::post('/payment/discount', 'PagSeguroController@checkDiscountCode');
    Route::post('/payment/boleto', 'PagSeguroController@generateNewBoleto');
});
