<?php

namespace App\Console\Commands;

use App\Advert;
use App\CronLog;
use App\Property;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TelesulsCommunication extends Command
{
    protected $signature = 'communication:telesul';
    protected $description = 'Get the telesul adverts from the fantastiko api';
    protected $user;
    protected $plans;
    protected $googleKey;

    public function __construct()
    {
        parent::__construct();
        $this->user = User::find(17);
        $this->plans = $this->user ? $this->user->plans : null;
        $this->googleKey = null;
    }

    public function handle()
    {
        // Used in 'whereNotIn' sql clause
        $property_ids = array();

        //Calls the fantastiko API
        $client = new Client();
        try {
            $response = $client->get('http://srv01.fantastiko.com.br/api/xml-imoveis');
            $response = simplexml_load_string($response->getBody()->getContents());
            //Deletes the adverts that are not in the request anymore
            foreach ($response->Imoveis->Imovel as $advert) {
                array_push($property_ids, "TS$advert->CodigoImovel");
            }
            $this->deleteProperties($property_ids, $this->user->id);

            // Count actived adverts
            $currentAdverts = $this->user->adverts->where('status', '1')->count();
            $advertsNumber = 0;
            foreach ($this->plans as $plan) {
                // Paied
                if ($plan->payment_status == '2') {
                    $advertsNumber += $plan->planRule->adverts_number;
                }
            }

            foreach ($response->Imoveis->Imovel as $advert) {
                // Decode address
                $address = [];
                try {
                    $address = $this->getAddressByAddress($advert);
                } catch (\Throwable $th) {
                    Cronlog::create([
                        'cron_signature' => $this->signature,
                        'log' => 'ADDRESS ERROR - ' . $advert->CodigoImovel . ' | ' . $th->getMessage()
                    ]);
                }
                //Checks if the advert already exists
                $exist_property = Property::where('external_register_id', "TS$advert->CodigoImovel")->first();
                if ($exist_property) {
                    //If it does, updates it
                    $this->updateProperty($advert, $exist_property, $address, $this->plans);
                } else if ($currentAdverts < $advertsNumber) {
                    //If not, saves it in the database
                    $this->saveProperty($advert, $address);

                    $currentAdverts++;
                }
            }

            return response()->json($response->Imoveis);
        } catch (ClientException $exception) {
            CronLog::create([
                'cron_signature' => $this->signature,
                'log' => 'HANDLE ERROR - ' . $exception->getResponse()->getBody()->getContents(),
                'user_id' => null,
                'plan_id' => null
            ]);
        }
    }

    public function saveProperty($advert, $address)
    {
        try {
            DB::beginTransaction();
            //Creates the Property
            $property = Property::create([
                'user_id' => $this->user->user_id,
                'property_type' => json_encode([$this->getType($advert->TipoImovel)]),
                'description' => $advert->Observacao,
                'complement' => $advert->Complemento,
                'cep' => preg_replace('/\D/', '', $advert->CEP),
                'number' => $advert->Numero,
                'street' => $advert->Endereco,
                'neighborhood' => $advert->Bairro,
                'city' => $advert->Cidade,
                'state' => $advert->Estado,
                'lat' => $address['location']['lat'],
                'lng' => $address['location']['lng'],
                'rooms' => (string)$advert->QtdDormitorios != '' ? $advert->QtdDormitorios : 0,
                'bathrooms' => (string)$advert->QtdBanheiros != '' ? $advert->QtdBanheiros : 0,
                'parking_spaces' => (string)$advert->QtdVagas != '' ? $advert->QtdVagas : 0,
                'area' => (string)$advert->AreaTotal != '' ? $advert->AreaTotal : 0,
                'quantity' => 1,
                'picture' => json_encode([]),
                'video' => $advert->Videos->Video[0] ? $advert->Videos->Video[0]->URLArquivo : null,
                'external_register_id' => "TS$advert->CodigoImovel"
            ]);

            //Creates the advert
            $createdAdvert = Advert::create([
                'plan_id' => $this->user->plans[0]->plan_id,
                'property_id' => $property->property_id,
                'user_id' => $this->user->user_id,
                'price' => $advert->Valor,
                'condo' => null,
                'transaction' => $advert->Finalidade == 'Venda' ? 'vender' : 'alugar',
                'status' => 1,
                'user_picture' => 0,
                'phone' => json_encode([[$this->user->phone, false]]),
                'email' => json_encode([$this->user->email]),
                'site' => 'www.imobiliariatelesul.com.br/',
                'advert_type' => 'default',
                'view_count' => 0,
                'message_count' => 0,
                'call_count' => 0
            ]);

            DB::commit();

            $urls = array();
            foreach ($advert->Fotos->Foto as $foto) {
                array_push($urls, $foto->URLArquivo);
            }

            //Saves the picture
            $this->handlePictures($urls, $property->property_id, $this->plans[0]->planRule->images_per_advert);
        } catch (\Throwable $th) {
            DB::rollBack();
            CronLog::create([
                'cron_signature' => $this->signature,
                'log' => 'Insert error ' . $th->getMessage() . "TS$advert->CodigoImovel",
                'user_id' => null,
                'plan_id' => null
            ]);
        }
    }

    public function updateProperty($property, $exist_property, $address)
    {
        try {
            
            DB::beginTransaction();
            // Update property
            $exist_property->description = 'ATUALIZADO - '.$property->Observacao;
            $exist_property->complement = $property->Complemento;
            $exist_property->cep = preg_replace('/\D/', '', $property->CEP);
            $exist_property->number = $property->Numero;
            $exist_property->street = $property->Endereco;
            $exist_property->neighborhood = $property->Bairro;
            $exist_property->city = $property->Cidade;
            $exist_property->state = $property->Estado;
            $exist_property->lat = $address['location']['lat'];
            $exist_property->lng = $address['location']['lng'];
            $exist_property->rooms = (string)$property->QtdDormitorios != '' ? $property->QtdDormitorios : 0;
            $exist_property->bathrooms = (string)$property->QtdBanheiros != '' ? $property->QtdBanheiros : 0;
            $exist_property->parking_spaces = (string)$property->QtdVagas != '' ? $property->QtdVagas : 0;
            $exist_property->area = (string)$property->AreaUtil != '' ? $property->AreaUtil : 0;
            $exist_property->quantity = 1;
            $exist_property->price = $property->Valor;
            $exist_property->tour = null;
            $exist_property->video = null;
            $exist_property->picture = json_encode([]);
            $exist_property->blueprint = null;
            $exist_property->property_type = json_encode([$this->getType($property->TipoImovel)]);
            $exist_property->save();

            // Update advert
            $exist_advert = Advert::where('property_id', $exist_property->id)->first();
            if ($exist_advert) {
                $exist_advert->price = $exist_property->price;
                $exist_advert->price_max = $exist_property->price;
                $exist_advert->condo = null;
                $exist_advert->transaction = $property->Finalidade == 'Venda' ? 'vender' : 'alugar';
                $exist_advert->status = 1;
                $exist_advert->user_picture = false;
                $exist_advert->phone = json_encode([[$this->user->phone, false]]);
                $exist_advert->email = json_encode([$this->user->email]);
                $exist_advert->advert_type = 'default';
                $exist_advert->site = 'www.imobiliariatelesul.com.br/';
                $exist_advert->save();
            }
            DB::commit();

            // Drop property folder
            Storage::deleteDirectory("/public/properties/$exist_property->property_id");

            $urls = array();
            foreach ($property->Fotos->Foto as $foto) {
                array_push($urls, $foto->URLArquivo);
            }
            //Saves the picture
            $this->handlePictures($urls, $exist_property->property_id, $this->plans[0]->planRule->images_per_advert);

        } catch (\Throwable $th) {
            DB::rollBack();
            CronLog::create([
                'cron_signature' => $this->signature,
                'log' => 'Update error ' . $th->getMessage(),
                'user_id' => null,
                'plan_id' => null
            ]);
        }
    }

    public function handlePictures($urlImages, $propertyid, $limit)
    {
        if (!$urlImages || !$propertyid || !$limit) {
            return;
        }

        $saved = array();

        // Gets limit image per plans
        $urlImages = array_slice($urlImages, 0, $limit);

        foreach ($urlImages as $url) {
            try {
                $path = "/public/properties/$propertyid/" . bin2hex(random_bytes(16)) . ".png";
                if ($url) {
                    $content = file_get_contents($url);
                    if ($content) {
                        Storage::put($path, $content);
                        $saved[] = (explode('public/', $path))[1];
                    }
                }
            } catch (\Throwable $th) {
                CronLog::create([
                    'cron_signature' => $this->signature,
                    'log' => "[TS$propertyid] IMAGE UPLOAD ERROR - " . $th->getMessage() . "URL $url"
                ]);
            }
        }

        //Saves the path to the database
        Property::find($propertyid)->update(['picture' => json_encode($saved)]);
    }

    private function getAddressByAddress($advert)
    {
        $query = "";
        //$advert->Endereco, $advert->Numero - $advert->Bairro, $advert->Cidade - $advert->Estado
        $query = "$advert->Endereco, $advert->Numero - $advert->Bairro, $advert->Cidade - $advert->Estado";
        $http = new \GuzzleHttp\Client();

        $response = $http->get("https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($query) . "&region=br&language=pt-BR&key=$this->googleKey");

        $response = json_decode($response->getBody()->getContents());

        if ($response->status == "ZERO_RESULTS") {
            $query = "$advert->Endereco - $advert->Bairro, $advert->Cidade - $advert->Estado";

            $response = $http->get("https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($query) . "&region=br&language=pt-BR&key=$this->googleKey");

            $response = json_decode($response->getBody()->getContents());
        }
        if (!$response->results)
            return [];

        if (count($response->results) == 0)
            return [];
        // ADDRESS ARRAY    
        // [0] streetNumber
        // [1] street
        // [2] neighborhood
        // [3] city
        // [4] state
        // [5] country
        // [6] zipcode
        // [7] location

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
                    $address['country'] = $item[$y]->short_name; #country
                if ($item[$y]->types[$i] == 'postal_code')
                    $address['zipcode'] = $item[$y]->short_name; #zipcode 
            }
        }
        $address['location'] = json_decode(json_encode($response->results[0]->geometry->location), true);
        return $address;
    }

    private function getAddressByCEP($cep)
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
        $response = $http->get("https://viacep.com.br/ws/$cep/json/");
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
                    "location" => ["lat" => 0, "lng" => 0],
                    "CEP" => $cep
                ];
            }
        }
        return $address;
    }

    private function deleteProperties($ids_notin, $userid)
    {
        $properties_to_delete = Property::whereNotIn('external_register_id', $ids_notin)->where('user_id', $userid)->get();
        if ($properties_to_delete) {
            foreach ($properties_to_delete as $property) {
                Advert::where('property_id', $property->property_id)->delete();
                // Drop property folder
                Storage::deleteDirectory("/public/properties/$property->property_id");
                $property->delete();
            }
        }
    }

    private function getType($type)
    {
        // Types: apartment,house,condo,farmhouse,flat,studio,land,roof,warehouse,commercial_set,farm,store,commercial_room,commercial_building'
        switch ($type) {
                // Alexandre Azevedo
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

                // Telesul
            case 'Apartamento':
                return 'apartment';
            case 'Barracão':
                return 'warehouse';
            case 'Casa':
                return 'house';
            case 'Casa comercial':
                return 'house';
            case 'Chácara':
                return 'farmhouse';
            case 'Loja':
                return 'store';
            case 'Lote':
                return 'land';
            case 'Lote Comercial':
                return 'land';
            case 'Lotes em Condomínio':
                return 'land';
            case 'Ponto Comercial':
                return 'commercial_set';
            case 'Prédio Comercial':
                return 'commercial_building';
            case 'Sala':
                return 'commercial_room';
            case 'Sítio':
                return 'farm';
            case 'Sobrado':
                return 'house';
            case 'Terreno / Área':
                return 'land';
            default:
                return 'house';
        };
    }
}
