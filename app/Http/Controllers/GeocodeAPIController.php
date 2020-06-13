<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeocodeAPIController extends Controller
{
    public function getGeocode(Request $request){
        //Receives the query send by the user and looks for the geocode
        //in the database, or requests the geocode from google

        //Data validation
        $this->validate($request, [
            'query' => 'required'
        ]);

        $query = urlencode($request['query']);

        //Checks the database
        $geocode = DB::table('transient_geocode')->where('query', $query)->value('geocode');

        if($geocode != ''){
            //Updates the geocode validity
            DB::table('transient_geocode')->where('query', $query)->update(['updated_at' => now()]);

            return response()->json(['geocode' => json_decode($geocode)],200);
        } else{
            //Gets the geocode from google
            $googleKey = null;
            $geocode = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?address=$query&region=br&language=pt-BR&key=$googleKey");
            $geocode = json_decode($geocode);

            if($geocode->status == 'OK'){
                //Saves the geocode in the database
                DB::table('transient_geocode')->insert([
                    'query' => $query,
                    'geocode' => json_encode($geocode),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json(['geocode' => $geocode], 200);
            } else{
                return response()->json(['error' => 'No Geocode API answer', 'answer' => $geocode], 400);
            }
        }

        //Fake geocode
//        $geocode = '{"results":[{"address_components":[{"long_name":"Varginha","short_name":"Varginha","types":["administrative_area_level_2","political"]},{"long_name":"Minas Gerais","short_name":"MG","types":["administrative_area_level_1","political"]},{"long_name":"Brasil","short_name":"BR","types":["country","political"]}],"formatted_address":"Varginha - MG, Brasil","geometry":{"bounds":{"northeast":{"lat":-21.458889,"lng":-45.251313},"southwest":{"lat":-21.6842209,"lng":-45.548685}},"location":{"lat":-21.5566617,"lng":-45.4270897},"location_type":"APPROXIMATE","viewport":{"northeast":{"lat":-21.458889,"lng":-45.251313},"southwest":{"lat":-21.6842209,"lng":-45.548685}}},"place_id":"ChIJCQyKfpKSypQR4CAwqYF4WmA","types":["administrative_area_level_2","political"]}],"status":"OK"}';
//
//        return response()->json(['geocode' => json_decode($geocode)],200);
    }
}
