<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User;
use App\Property;
use App\Advert;
use App\CronLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class AlexandresComunication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'communication:alexandreazevedo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comunica com a WebAPI WordPress do Alexandre Azevedo, assim importando seus imóveis para a base Wizzer';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    protected $googleKey;

    public function __construct()
    {
        parent::__construct();
        $this->googleKey = null;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $_user_id = 19;

        // Set request time limit to one hour
        set_time_limit(3600);

        $user = User::find($_user_id);
        $plans = null;
        $code = 200;
        $page = 1;
        $property_ids = array();
        $properties = array();
        if ($user) {
            $plans = $user->plans;
            $http = null;
            if ($plans) {

                // Request Alexandres API
                while ($code == 200) {
                    $http = new \GuzzleHttp\Client();
                    try {
                        $response = $http->get("https://www.alexandreazevedoimoveis.com.br/wp-json/wp/v2/property?per_page=100&page=$page");
                        foreach (json_decode($response->getBody()->getContents()) as $item) {
                            array_push($properties, $item);
                            // Array for not in
                            array_push($property_ids, 'AA' . $item->id);
                        }
                        $code = $response->getStatusCode();
                        $page++;
                    } catch (\Throwable $th) {
                        $code = 500;
                    }
                }

                // Delete all properties that not found in request
                $properties_to_delete = Property::whereNotIn('external_register_id', $property_ids)->where('user_id', $user->user_id)->get();
                if ($properties_to_delete) {
                    foreach ($properties_to_delete as $property) {
                        Advert::where('property_id', $property->property_id)->delete();
                        // Drop property folder
                        Storage::deleteDirectory("/public/properties/$property->property_id");
                        $property->delete();
                    }
                }

                // Count actived adverts
                $currentAdverts = $user->adverts->where('status', '1')->count();
                $advertsNumber = 0;
                foreach ($plans as $plan) {
                    // Paied
                    if ($plan->payment_status == '2') {
                        $advertsNumber += $plan->planRule->adverts_number;
                    }
                }

                foreach ($properties as $property) {
                    $property = json_decode(json_encode($property), true);

                    // Decode address
                    $property['real_estate_property_zip'] = preg_replace('/\D/', '', $property['real_estate_property_zip']);
                    $address = $this->address($property);
                    if (!$address)
                        continue;
                    // End decode address

                    $exist_property = Property::where('external_register_id', 'AA' . $property['id'])->first();

                    if ($exist_property) {
                        
                        $this->updateProperty($exist_property, $property, $user, $address, $plans);
                    } else {

                        if (($currentAdverts < $advertsNumber) && $property['type'] == 'property') {

                            $this->insertProperty($property, $user, $address, $plans);
                            $currentAdverts++;
                        }
                    }
                }
            } else {
                CronLog::create([
                    'cron_signature' => $this->signature,
                    'log' => 'plans not found',
                    'user_id' => $plan->user_id,
                    'plan_id' => $plan->plan_id
                ]);
            }
        }
    }

    private function getType($type)
    {
        switch ($type) {
            case 'CASA':
                return 'house';
            case 'GALPÃO COMERCIAL':
                return 'warehouse';
            case 'APARTAMENTO':
                return 'apartment';
            case 'KITNET':
                return 'apartment';
            case 'LOJA COMERCIAL':
                return 'store';
            case 'SITIO':
                return 'farm';
            case 'TERRENO E LOTEAMENTOS':
                return 'land';
            default:
                return 'house';
        };
    }

    private function getAddressByCEP($CEP)
    {
        //https://viacep.com.br/ws/{cep}/{format}/

        // ADDRESS ARRAY
        // [0] streetNumber
        // [1] street
        // [2] neighborhood
        // [3] city
        // [4] state
        // [5] country
        $address = array();
        $http = new \GuzzleHttp\Client();
        $response = $http->get("https://viacep.com.br/ws/$CEP/json/");
        if ($response->getStatusCode() == 200) {
            $response = json_decode($response->getBody()->getContents());
            if (!property_exists($response, 'error')) {
                $address = [
                    "streetNumber" => property_exists($response, 'unidade') ? ($response->unidade == "" ? 0 : $response->unidade) : 0,
                    "street" => property_exists($response, 'unidade') ? $response->logradouro : "",
                    "neighborhood" =>  property_exists($response, 'bairro') ? $response->bairro : "",
                    "city" =>  property_exists($response, 'localidade') ? $response->localidade : "",
                    "state" =>  property_exists($response, 'uf') ? $response->uf : "",
                    "country" => property_exists($response, 'uf') ? (
                        ($response->uf == "AC" ||
                            $response->uf == "AL" ||
                            $response->uf == "AP" ||
                            $response->uf == "AM" ||
                            $response->uf == "BA" ||
                            $response->uf == "CE" ||
                            $response->uf == "DF" ||
                            $response->uf == "ES" ||
                            $response->uf == "GO" ||
                            $response->uf == "MA" ||
                            $response->uf == "MT" ||
                            $response->uf == "MS" ||
                            $response->uf == "MG" ||
                            $response->uf == "PA" ||
                            $response->uf == "PB" ||
                            $response->uf == "PR" ||
                            $response->uf == "PE" ||
                            $response->uf == "PI" ||
                            $response->uf == "RJ" ||
                            $response->uf == "RN" ||
                            $response->uf == "RS" ||
                            $response->uf == "RO" ||
                            $response->uf == "RR" ||
                            $response->uf == "SC" ||
                            $response->uf == "SP" ||
                            $response->uf == "SE" ||
                            $response->uf == "TO") ? "BR" : "") : "",
                    "location" => ["lat" => 0, "lng" => 0]
                ];
            }
        }
        return $address;
    }

    private function getAddressByLatLng($latlng)
    {
        $http = new \GuzzleHttp\Client();
        $response = $http->get("https://maps.googleapis.com/maps/api/geocode/json?latlng=$latlng&key=$this->googleKey");
        if ($response->getStatusCode() == 200) {
            $response = json_decode($response->getBody()->getContents());
            if (!$response->results)
                return [];
            // ADDRESS ARRAY
            // [0] streetNumber
            // [1] street
            // [2] neighborhood
            // [3] city
            // [4] state
            // [5] country
            // [6] location
            $address = array();
            $item = $response->results[0]->address_components;
            for ($y = 0; $y < count($item); $y++) {
                for ($i = 0; $i < count($item[$y]->types); $i++) {
                    if ($item[$y]->types[$i] == "street_number")
                        $address['streetNumber'] = $item[$y]->short_name; #number
                    if ($item[$y]->types[$i] == "route")
                        $address['street'] = $item[$y]->short_name; #street
                    if ($item[$y]->types[$i] == 'locality' || $item[$y]->types[$i] == 'bus_station' || $item[$y]->types[$i] == 'sublocality_level_1' || $item[$y]->types[$i] == 'sublocality')
                        $address['neighborhood'] = $item[$y]->short_name; #neighborhood
                    if ($item[$y]->types[$i] == 'administrative_area_level_2')
                        $address['city'] = $item[$y]->short_name; #city
                    if ($item[$y]->types[$i] == 'administrative_area_level_1')
                        $address['state'] = $item[$y]->short_name; #state
                    if ($item[$y]->types[$i] == 'country')
                        $address['country'] = $item[$y]->short_name; #state
                }
            }
            $address['location'] = json_decode(json_encode($response->results[0]->geometry->location), true);
            return $address;
        } else return [];
    }

    private function getAddressbyAddress($query)
    {
        $http = new \GuzzleHttp\Client();

        $response = $http->get("https://maps.googleapis.com/maps/api/geocode/json?address=" . $query . "&region=br&language=pt-BR&key=$this->googleKey");

        if ($response->getStatusCode() == 200) {
            $response = json_decode($response->getBody()->getContents());

            if (!$response->results)
                return [];
            // ADDRESS ARRAY
            // [0] streetNumber
            // [1] street
            // [2] neighborhood
            // [3] city
            // [4] state
            // [5] country
            // [6] location
            $address = array();
            $item = $response->results[0]->address_components;
            for ($y = 0; $y < count($item); $y++) {
                for ($i = 0; $i < count($item[$y]->types); $i++) {
                    if ($item[$y]->types[$i] == "street_number")
                        $address['streetNumber'] = $item[$y]->short_name; #number
                    if ($item[$y]->types[$i] == "route")
                        $address['street'] = $item[$y]->short_name; #street
                    if ($item[$y]->types[$i] == 'locality' || $item[$y]->types[$i] == 'bus_station' || $item[$y]->types[$i] == 'sublocality_level_1' || $item[$y]->types[$i] == 'sublocality')
                        $address['neighborhood'] = $item[$y]->short_name; #neighborhood
                    if ($item[$y]->types[$i] == 'administrative_area_level_2')
                        $address['city'] = $item[$y]->short_name; #city
                    if ($item[$y]->types[$i] == 'administrative_area_level_1')
                        $address['state'] = $item[$y]->short_name; #state
                    if ($item[$y]->types[$i] == 'country')
                        $address['country'] = $item[$y]->short_name; #state
                }
            }
            $address['location'] = json_decode(json_encode($response->results[0]->geometry->location), true);
            return $address;
        } else return [];
    }

    private function address($property)
    {
        try {
            $address = array();
            if ($property['real_estate_property_zip'] != "")
                $address = $this->getAddressByCEP($property['real_estate_property_zip']);
            else if ($property['real_estate_property_address'] != "")
                $address = $this->getAddressbyAddress($property['real_estate_property_address']);
            else
                $address = $this->getAddressByLatLng($property['real_estate_property_location']['location']);

            if ($address == []) {
                CronLog::create([
                    'cron_signature' => $this->signature,
                    'log' => $property['id'] . ' address not found to property'
                ]);
                return [];
            }

            // Only brazilian states
            if ($address['country'] != 'BR' && $address['country'] != '') {
                CronLog::create([
                    'cron_signature' => $this->signature,
                    'log' => $property['id'] . ' is not a brazilian address'
                ]);
                return [];
            }

            return $address;
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function saveImage($urlImages, $propertyid, $limit)
    {
        //Saves the picture in the server and the path in the database
        if (isset($urlImages)) {
            $savedFileArray = [];
            // Gets limit image per plans
            $urlImages = array_slice($urlImages, 0, $limit);
            foreach ($urlImages as $urlPicture) {
                try {
                    if ($urlPicture == false || $urlPicture == null) {
                        continue;
                    }
                    $path = "/public/properties/$propertyid/" . bin2hex(random_bytes(16)) . ".png";
                    $content = file_get_contents($urlPicture);
                    //file_put_contents($path, $content);
                    Storage::put($path, $content);
                    $savedFileArray[] = (explode('public/', $path))[1];
                } catch (\Throwable $th) {
                    CronLog::create([
                        'cron_signature' => $this->signature,
                        'log' => "[AA$propertyid] upload image error - ".$th->getMessage(),
                        'user_id' => null,
                        'plan_id' => null
                    ]);
                }
            }
            //Saves the path to the database
            Property::find($propertyid)->update(['picture' => json_encode($savedFileArray)]);
        }
    }

    private function insertProperty($property, $user, $address, $plans)
    {
        try {
            DB::beginTransaction();

            // Insert property into database
            $createdProperty = Property::create([
                'user_id'               => $user->user_id,
                'title'                 => $property['title']['rendered'],
                'description'           => preg_replace('/<[^>]*>/', '', $property['content']['rendered']),
                'complement'            => $property['tipo'],
                'cep'                   => $property['real_estate_property_zip'],
                'number'                => isset($address['streetNumber']) ? $address['streetNumber'] : '',
                'street'                => isset($address['street']) ? $address['street'] : '',
                'neighborhood'          => isset($address['neighborhood']) ? $address['neighborhood'] : '',
                'city'                  => isset($address['city']) ? $address['city'] : '',
                'state'                 => isset($address['state']) ? strlen($address['state']) >= 3 ? 'MG' : $address['state'] : '',
                'lat'                   => $address['location']['lat'] != 0 ? $address['location']['lat'] : explode(",", $property['real_estate_property_location']['location'])[0],
                'lng'                   => $address['location']['lng'] != 0 ? $address['location']['lng'] : explode(",", $property['real_estate_property_location']['location'])[1],
                'rooms'                 => $property['real_estate_property_bedrooms'] == '' ? 0 : $property['real_estate_property_bedrooms'],
                'bathrooms'             => $property['real_estate_property_bathrooms'] == '' ? 0 : $property['real_estate_property_bathrooms'],
                'parking_spaces'        => $property['real_estate_property_garage'] == '' ? 0 : $property['real_estate_property_garage'],
                'area'                  => $property['real_estate_property_size'] == '' ? 0 : str_replace(',', '.', $property['real_estate_property_size']),
                'quantity'              => 1,
                'price'                 => $property['real_estate_property_price_short'] == '' ? 0 : $property['real_estate_property_price_short'],
                'tour'                  => null,
                'video'                 => null,
                'picture'               => json_encode([]),
                'blueprint'             => null,
                'property_type'         => json_encode([$this->getType($property['tipo'])]),
                'external_register_id'  => "AA" . $property['id']
            ]);

            // Inserts the main image in the first position of the array
            array_unshift($property['imagens-imovel'], $property['imagem-principal']);
            // Insert all images referencied by the property into database
            $this->saveImage($property['imagens-imovel'], $createdProperty->property_id, $plans[0]->planRule->images_per_advert);

            // Create add
            $createdAdvert = Advert::create([
                'plan_id'       => $plans[0]->plan_id,
                'property_id'   => $createdProperty->property_id,
                'user_id'       => $user->user_id,
                'price'         => $createdProperty->price,
                'price_max'     => $createdProperty->price,
                'condo'         => null,
                'transaction'   => $property['status'] == 'Aluguel' ? 'alugar' : 'vender',
                'status'        => 1,
                'user_picture'  => false,
                'phone'         => json_encode([[$user->phone, false]]),
                'email'         => json_encode([$user->email]),
                'advert_type'   => 'default',
                'site'          => $property['link'],
                'facebook'      => '',
                'instagram'     => '',
                'view_count'    => 0,
                'message_count' => 0,
                'call_count'    => 0
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
        }
    }

    private function updateProperty($exist_property, $property, $user, $address, $plans)
    {
        // Drop property folder
        Storage::deleteDirectory("/public/properties/$exist_property->property_id");

        // Update property
        $exist_property->description = preg_replace('/<[^>]*>/', '', $property['content']['rendered']);
        $exist_property->title = $property['title']['rendered'];
        $exist_property->complement = $property['tipo'];
        $exist_property->cep = $property['real_estate_property_zip'];
        $exist_property->number = isset($address['streetNumber']) ? $address['streetNumber'] : '';
        $exist_property->street = isset($address['street']) ? $address['street'] : '';
        $exist_property->neighborhood = isset($address['neighborhood']) ? $address['neighborhood'] : '';
        $exist_property->city = isset($address['city']) ? $address['city'] : '';
        $exist_property->state = isset($address['state']) ? strlen($address['state']) >= 3 ? 'MG' : $address['state'] : '';
        $exist_property->lat = $address['location']['lat'] != 0 ? $address['location']['lat'] : explode(",", $property['real_estate_property_location']['location'])[0];
        $exist_property->lng = $address['location']['lng'] != 0 ? $address['location']['lng'] : explode(",", $property['real_estate_property_location']['location'])[1];
        $exist_property->rooms = $property['real_estate_property_bedrooms'] == '' ? 0 : $property['real_estate_property_bedrooms'];
        $exist_property->bathrooms = $property['real_estate_property_bathrooms'] == '' ? 0 : $property['real_estate_property_bathrooms'];
        $exist_property->parking_spaces = $property['real_estate_property_garage'] == '' ? 0 : $property['real_estate_property_garage'];
        $exist_property->area = $property['real_estate_property_size'] == '' ? 0 : str_replace(',', '.', $property['real_estate_property_size']);
        $exist_property->quantity = 1;
        $exist_property->price = $property['real_estate_property_price_short'] == '' ? 0 : $property['real_estate_property_price_short'];
        $exist_property->tour = null;
        $exist_property->video = null;
        $exist_property->picture = json_encode([]);
        $exist_property->blueprint = null;
        $exist_property->property_type = json_encode([$this->getType($property['tipo'])]);
        $exist_property->save();
        // Inserts the main image in the first position of the array
        array_unshift($property['imagens-imovel'], $property['imagem-principal']);

        // Insert all images referencied by the property into database
        $this->saveImage($property['imagens-imovel'], $exist_property->property_id, $plans[0]->planRule->images_per_advert);

        // Update advert
        $exist_advert = Advert::where('property_id', $exist_property->id)->first();
        if ($exist_advert) {
            $exist_advert->price = $exist_property->price;
            $exist_advert->price_max = $exist_property->price;
            $exist_advert->condo = null;
            $exist_advert->transaction = $property['status'] == 'Aluguel' ? 'Alugar' : 'Vender';
            $exist_advert->status = 1;
            $exist_advert->user_picture = false;
            $exist_advert->phpne = json_encode([[$user->phone, false]]);
            $exist_advert->email = json_encode([$user->email]);
            $exist_advert->advert_type = 'default';
            $exist_advert->site = $property['link'];
            $exist_advert->save();
        }
    }
}
