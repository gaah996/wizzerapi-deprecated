<?php

namespace App\Http\Controllers;

use App\DevelopmentAd;
use App\Seller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Advert;
use App\Property;
use App\Http\Controllers\PropertiesController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection ;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\User;

use App\Mail\InterestMessage;
use App\Mail\ShareAdvert;

class AdvertsController extends Controller
{
    public function selectStore(Request $request) {
        $userType = Auth::user()->profile_type;

        if($userType == 0 || $userType == 1) {
            return $this->store($request);
        } else {
            return $this->storeProfile2($request);
        }
    }

    private function storeProfile2(Request $request) {
        //Data validation
        $regex = '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/';

        $this->validate($request, [
            'title' => 'required',
            'description' => 'required',
            'number' => 'nullable|numeric',
            'street' => 'nullable',
            'neighborhood' => 'nullable',
            'city' => 'required',
            'state' => 'required|size:2',
            'cep' => 'nullable|size:8',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'work_stage' => 'numeric',
            'due_date' => 'date',
            'price_min' => 'numeric',
            'price_max' => 'numeric',
            'transaction' => 'array|size:1',
            'transaction.*' => 'in:vender,alugar',
            'picture' => 'array',
            'picture.*' => 'image',
            'phone' => 'array|required',
            'phone.*.0' => 'celular_com_ddd',
            'email' => 'required|array',
            'email.*' => 'email',
            'video' => 'file',
            'facebook' => 'nullable',
            'instagram' => 'nullable',
            'youtube' => 'nullable|regex:' . $regex
        ]);

        $user = Auth::user();

        //Get user current adverts and the plan max adverts
        $currentAdverts = $user->adverts->where('status', '1')->count();

        $advertsNumber = 0;
        $advertsFutureNumber = 0;
        foreach($user->plans as $plan){
            if($plan->payment_status == '2'){
                $advertsNumber += $plan->planRule->adverts_number;
            }
        }
        foreach ($user->plans as $plan){
            if($plan->payment_status == '1' ){
                $advertsFutureNumber += $plan->planRule->adverts_number;
            }
        }

        //Creates the advert
        if ($currentAdverts < $advertsNumber || $currentAdverts < $advertsFutureNumber) {
            //Creates the development_ad
            $development_ad = DevelopmentAd::create([
                'title' => $request['title'],
                'description' => $request['description'],
                'number' => $request['number'],
                'street' => $request['street'],
                'neighborhood' => $request['neighborhood'],
                'city' => $request['city'],
                'state' => $request['state'],
                'cep' => $request['cep'],
                'lat' => $request['lat'],
                'lng' => $request['lng'],
                'work_stage' => $request['work_stage'],
                'datasheet' => $request['datasheet'],
                'due_date' => (new Carbon($request['due_date'])),
                'background' => $request['background'],
                'type' => $request['type']
            ]);

            if($development_ad) {
                //Saves the pictures
                $this->saveDevAdPictures($request, $development_ad->id);

                //Saves the video
                $this->saveVideo($request, $development_ad->id);

                //Creates the advert
                $plan_id = null;
                foreach($user->plans as $plan){
                    if($plan->payment_status == '2'){
                        $max = $plan->planRule->adverts_number;
                        $current = $plan->adverts->where('status', '1')->count();
                        if($current < $max) {
                            $plan_id = $plan->plan_id;
                            $status = 1;
                        }
                    }
                }

                foreach($user->plans as $plan){
                    if($plan->payment_status == '1'){
                        $max = $plan->planRule->adverts_number;
                        $current = $plan->adverts->count();
                        if($current < $max) {
                            $plan_id = $plan->plan_id;
                            $status = 0;
                        }
                    }
                }

                $userPicture = null;
                if($request->user_picture == 'true'){
                    $userPicture = true;
                } else {
                    $userPicture = false;
                }

                $advert = Advert::create([
                    'plan_id' => $plan_id,
                    'property_id' => $development_ad->id,
                    'user_id' => $user->user_id,
                    'price' => $request['price_min'],
                    'price_max' => $request['price_max'],
                    'condo' => null,
                    'transaction' => $request['transaction'][0],
                    'status' => $status,
                    'user_picture' => $userPicture,
                    'phone' => json_encode($request['phone']),
                    'email' => json_encode($request['email']),
                    'advert_type' => 'development',
                    'site' => $request['site'],
                    'facebook' => $request['facebook'],
                    'instagram' => $request['instagram'],
                    'youtube' => $request['youtube'],
                    'view_count' => 0,
                    'message_count' => 0,
                    'call_count' => 0
                ]);

                if($advert) {
                    $advert->development;
                    $advert->development->picture = json_decode($advert->development->picture);
                    $advert->phone = json_decode($advert->phone);
                    $advert->email = json_decode($advert->email);
                    $response[] = $advert;
                } else {
                    $development_ad->delete();
                    return response()->json(['error' => 'Couldn\'t create advert'], 400);
                }
                //Return adverts
                return response()->json($response, 200);
            } else {
                return response()->json(['error' => 'Couldn\'t create development advert'], 400);
            }
        } else {
            return response()->json(['error' => 'User not allowed to create advert'], 400);
        }
    }

    private function saveVideo(Request $request, $id) {
        if($request->hasFile('video')){
            $video = $request->file('video');
            $extension = $video->getClientOriginalExtension();
            $videoExtensions = ['mpeg', 'ogg', 'mp4', 'webm', '3gp', 'mov', 'flv', 'avi', 'wmv', 'ts'];

            if(in_array($extension, $videoExtensions)) {
                $development = DevelopmentAd::find($id);
                if($development->video != null) {
                    Storage::delete('public/' . $development->video);
                }

                $savedVideo = Storage::put("public/developments/$id", $video);
                $savedVideo = (explode('public/', $savedVideo))[1];

                $development->update(['video' => $savedVideo]);
            }
        }
    }

    private function saveDevAdPictures(Request $request, $id) {
        if($request->hasFile('picture')) {
            $savedFileArray = [];
            foreach ($request->file('picture') as $picture) {
                //Checks if file is an image
                if (exif_imagetype($picture)) {
                    //Saves the file in the disk
                    $savedFile = Storage::put("public/developments/$id", $picture);
                    $savedFileArray[] = (explode('public/', $savedFile))[1];
                }
            }
            //Saves the path to the database
            DevelopmentAd::find($id)->update(['picture' => json_encode($savedFileArray)]);
        }

        if($request->hasFile('logo')) {
            $logo = $request->file('logo');
            if(exif_imagetype($logo)) {
                $savedFile = Storage::put("public/developments/$id", $logo);
                $savedFile = (explode('public/', $savedFile))[1];

                DevelopmentAd::find($id)->update(['logo' => $savedFile]);
            }
        }
    }

    private function store(Request $request){//price,transaction,phone,email,site
        $user = Auth::user();
        $plans = $user->plans;

        //Data validation
        $this->validateDataAdverts($request);

        //Checks if the plan allows the user to add the advert
        $currentAdverts = $user->adverts->where('status', '1')->count();
        $advertsNumber = 0;
        $advertsFutureNumber = 0;
        foreach($plans as $plan){
            if($plan->payment_status == '2'){
                $advertsNumber += $plan->planRule->adverts_number;
            }
        }
        foreach ($plans as $plan){
            if($plan->payment_status == '1'){
                $advertsFutureNumber += $plan->planRule->adverts_number;
            }
        }

        if ($currentAdverts < $advertsNumber || $currentAdverts < $advertsFutureNumber) {
            //Adds the new property
            $property = Property::create([
                'user_id' => $user->user_id,
                'property_type' => json_encode($request->property_type),
                'description' => $request->description,
                'complement' => $request->complement,
                'cep' => $request->cep,
                'number' => $request->number,
                'street' => $request->street,
                'neighborhood' => $request->neighborhood,
                'city' => $request->city,
                'state' => $request->state,
                'lat' => $request->lat,
                'lng' => $request->lng,
                'rooms' => $request->rooms,
                'bathrooms' => $request->bathrooms,
                'parking_spaces' => $request->parking_spaces,
                'area' => $request->area,
                'tour' => $request->tour
            ]);

            if ($property) {
                //Save the images in the server and get the URLs
                $this->savePropertyPictures($request, $property->property_id);
                $this->savePropertyVideo($request, $property->property_id);

                //Creates the advert
                foreach($request->transaction as $transaction) {
                    if($transaction == 'vender') {
                        $price = $request->price_sell;
                    } else {
                        $price = $request->price_rent;
                    }

                    //Check with how much properties that plan can be
                    //associated with, if that limit is reached, check
                    //the next plan until finding one that can be used
                    $plan_id = null;
                    foreach($plans as $plan){
                        if($plan->payment_status == '2'){
                            $max = $plan->planRule->adverts_number;
                            $current = $plan->adverts->where('status', '1')->count();
                            if($current < $max) {
                                $plan_id = $plan->plan_id;
                                $status = 1;
                            }
                        }
                    }

                    foreach($plans as $plan){
                        if($plan->payment_status == '1'){
                            $max = $plan->planRule->adverts_number;
                            $current = $plan->adverts->count();
                            if($current < $max) {
                                $plan_id = $plan->plan_id;
                                $status = 0;
                            }
                        }
                    }

                    $userPicture = null;
                    if($request->user_picture == 'true'){
                        $userPicture = true;
                    } else {
                        $userPicture = false;
                    }

                    $advert = Advert::create([
                        'plan_id' => $plan_id,
                        'property_id' => $property->property_id,
                        'user_id' => $user->user_id,
                        'price' => $price,
                        'condo' => $request->condo,
                        'transaction' => $transaction,
                        'status' => $status,
                        'user_picture' => $userPicture,
                        'phone' => json_encode($request->phone),
                        'email' => json_encode($request->email),
                        'site' => $request->site,
                        'facebook' => $request->facebook,
                        'instagram' => $request->instagram,
                        'youtube' => $request->youtube,
                        'advert_type' => 'default',
                        'view_count' => 0,
                        'message_count' => 0,
                        'call_count' => 0
                    ]);

                    if($advert) {
                        $advert->property;
                        $advert->property->property_type = json_decode($advert->property->property_type);
                        $advert->property->picture = json_decode($advert->property->picture);
                        $advert->phone = json_decode($advert->phone);
                        $advert->email = json_decode($advert->email);
                        $response[] = $advert;
                    } else {
                        $property->delete();
                        return response()->json(['error' => 'Couldn\'t create advert'], 400);
                    }
                }
                //Return adverts
                return response()->json($response, 200);
            } else {
                return response()->json(['error' => 'Couldn\'t create property'], 400);
            }
        } else {
            return response()->json(['error' => 'User not allowed to create advert'], 400);
        }
    }

    private function savePropertyVideo(Request $request, $id) {
        if($request->hasFile('video')){
            $video = $request->file('video');
            $extension = $video->getClientOriginalExtension();
            $videoExtensions = ['mpeg', 'ogg', 'mp4', 'webm', '3gp', 'mov', 'flv', 'avi', 'wmv', 'ts'];

            if(in_array($extension, $videoExtensions)) {
                $property = Property::find($id);
                if($property->video != null) {
                    Storage::delete('public/' . $property->video);
                }

                $savedVideo = Storage::put("public/properties/$id", $video);
                $savedVideo = (explode('public/', $savedVideo))[1];

                $property->update(['video' => $savedVideo]);
            }
        }
    }

    private function savePropertyPictures(Request $request, $propertyID){
        //Saves the picture in the server and the path in the database
        if($request->hasfile('picture')) {
            $savedFileArray = [];
            foreach ($request->file('picture') as $picture) {
                //Checks if file is an image
                if (exif_imagetype($picture)) {
                    //Saves the file in the disk
                    $savedFile = Storage::put("public/properties/$propertyID", $picture);
                    $savedFileArray[] = (explode('public/', $savedFile))[1];
                }
            }
            //Saves the path to the database
            Property::find($propertyID)->update(['picture' => json_encode($savedFileArray)]);
        }
    }

    public function index(){
        //Return all adverts
        $advertsCollection =  Advert::all();

        foreach($advertsCollection as $advert){
            $advert->property;
            if($advert->user_picture == "1") {
                $advert->user_picture = $advert->user->avatar;
            }
        }

        return  $this->integrationJSON('No advert found.',$advertsCollection,
          is_null($advertsCollection)? 0: 1, 200, 404);

    }

    public function getUserAdverts(){

        //Return the current user adverts
        $user = Auth::user();

        $adverts = Advert::where('user_id', $user->user_id)->orderBy('advert_id', 'desc')->get();

        foreach($adverts as $advert){
            if($advert->advert_type == 'development') {
                $advert->development;
                $advert->phone = json_decode($advert->phone);
                $advert->email = json_decode($advert->email);
                $advert->development->picture = json_decode($advert->development->picture);

                $advert->development->properties;

                foreach($advert->development->properties as $property){
                    $property->property_type = json_decode($property->property_type);
                    $property->picture = json_decode($property->picture);
                    $property->blueprint = json_decode($property->blueprint);
                }

                if($advert->user_picture == "1") {
                    $advert->user_picture = $user->avatar;
                }

                foreach ($advert->development->sellers as $seller) {
                    $seller->phones = json_decode($seller->phones);
                    $seller->emails = json_decode($seller->emails);
                }
            } else {
                $advert->property;
                $advert->property->picture = json_decode($advert->property->picture);
                $advert->property->property_type = json_decode($advert->property->property_type);
                $advert->phone = json_decode($advert->phone);
                $advert->email = json_decode($advert->email);
                if($advert->user_picture == "1") {
                    $advert->user_picture = $user->avatar;
                }
            }
        }

        return $this->integrationJSON('Not exist advert for this user.', $adverts,
            is_null($adverts)? 0: 1, 200, 204);

    }

    public function show(Advert $advert)
    {
        if($advert->advert_type == 'development') {
            $advert->development;

            $advert->phone = json_decode($advert->phone);
            $advert->email = json_decode($advert->email);
            $advert->development->picture = json_decode($advert->development->picture);

            $advert->development->properties;

            foreach($advert->development->properties as $property){
                $property->property_type = json_decode($property->property_type);
                $property->picture = json_decode($property->picture);
                $property->blueprint = json_decode($property->blueprint);
            }

            if($advert->user_picture == "1") {
                $advert->user_picture = $advert->user->avatar;
            }

            //Sellers
            $sellers = Seller::where('development_ad_id', $advert->development->id)->get();

            $partialAnswer = [];

            foreach($sellers as $seller) {
                array_push($partialAnswer, [
                    'id' => $seller->id,
                    'avatar' => $seller->avatar,
                    'name' => $seller->name,
                    'emails' => json_decode($seller->emails),
                    'phones' => json_decode($seller->phones)
                ]);
            }

            $advert->development->sellers = $partialAnswer;

            return response()->JSON(['advert' => $advert], 200);
        } else {
            //Return the informed advert
            $advert->property;

            $advert->property->property_type = json_decode($advert->property->property_type);
            $advert->property->picture = json_decode($advert->property->picture);
            $advert->phone = json_decode($advert->phone);
            $advert->email = json_decode($advert->email);
            if($advert->user_picture == "1") {
                $advert->user_picture = $advert->user->avatar;
            }

            return $this->integrationJSON(['advert' => $advert], 200);
        }
    }

    public function addView(Advert $advert){
        $advert->increment('view_count');

        return $advert->view_count;
    }

    public function addCall(Advert $advert){
        $advert->increment('call_count');

        return $advert->call_count;
    }

    public function sendMessage(Advert $advert, Request $request){
        $toEmails = json_decode($advert->email);
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'celular_com_ddd',
            'message' => 'required'
        ]);

        $userName = User::where('user_id', $advert->user_id)->value('name');

        foreach ($toEmails as $to) {
            Mail::to($to)->send(new InterestMessage($userName ,$request->name, $request->email, $request->phone, $request->message, $advert));
        }
        Mail::to($request->email)->send(new InterestMessageUser($request->name));

        $advert->increment('message_count');

        return response()->JSON(['success' => 'Message sent'], 200);
    }

    public function update(Request $request, Advert $advert){
        //Updates the informed advert if it belongs to the current user
        $user = Auth::user();
        $userId = $user->user_id;


        if($advert->user_id == $userId){//price,transaction,phone,email,site
            //Data validation
            //$this->validateDataAdverts($request);

            if($request->picture) {
                $advert->property->picture = $this->updatePropertyPictures($request->picture, $advert->property_id, $advert->advert_type);
            }

            if($request->complement == '' || $request->complement == 'null' || $request->complement == null){
                $request->complement = null;
            }

            if($advert->advert_type == 'development') {
                // Updates the development
                $this->updateLogo($request, $advert);
                $this->saveVideo($request, $advert->development->id);

                $property = $advert->property()->update([
                    'title' => (isset($request->title)) ? $request->title : $advert->property->title,
                    'description' => (isset($request->description)) ? $request->description : $advert->property->description,
                    'number' => (isset($request->number)) ? $request->number : $advert->property->number,
                    'street' => (isset($request->street)) ? $request->street : $advert->property->street,
                    'neighborhood' => (isset($request->neighborhood)) ? $request->neighborhood : $advert->property->neighborhood,
                    'city' => (isset($request->city)) ? $request->city : $advert->property->city,
                    'state' => (isset($request->state)) ? $request->state : $advert->property->state,
                    'cep' => (isset($request->cep)) ? $request->cep : $advert->property->cep,
                    'lat' => (isset($request->lat)) ? $request->lat : $advert->property->lat,
                    'lng' => (isset($request->lng)) ? $request->lng : $advert->property->lng,
                    'datasheet' => (isset($request->datasheet)) ? $request->datasheet : $advert->property->datasheet,
                    'work_stage' => (isset($request->work_stage)) ? $request->work_stage : $advert->property->work_stage,
                    'due_date' => (isset($request->due_date)) ? $request->due_date : null,
                    'background' => (isset($request->background)) ? $request->background : $advert->property->background,
                    'type' => (isset($request->type)) ? $request->type : $advert->property->type
                ]);

                //Updates the address of the properties for that development
                foreach ($advert->property->properties as $subprop) {
                    $subprop->update([
                        'number' => $advert->property->number,
                        'street' => $advert->property->street,
                        'neighborhood' => $advert->property->neighborhood,
                        'city' => $advert->property->city,
                        'state' => $advert->property->state,
                        'cep' => $advert->property->cep,
                        'lat' => $advert->property->lat,
                        'lng' => $advert->property->lng
                    ]);
                }

                //Updates the advert
                if ($request->site == '' || $request->site == 'null' || $request->site == null) {
                    $request->site = null;
                }

                $userPicture = false;
                if ($request->user_picture == 'true') {
                    $userPicture = true;
                } else {
                    $userPicture = false;
                }

                $advert->update([
                    'price' => ($request->price_min) ? $request->price_min : $advert->price,
                    'price_max' => ($request->price_max) ? $request->price_max : $advert->price_max,
                    'transaction' => ($request->transaction) ? $request->transaction : $advert->transaction,
                    'user_picture' => $userPicture,
                    'phone' => ($request->phone) ? json_encode($request->phone) : $advert->phone,
                    'email' => ($request->email) ? json_encode($request->email) : $advert->email,
                    'site' => ($request->site) ? $request->site : $advert->site,
                    'facebook' => ($request->facebook) ? $request->facebook : $advert->facebook,
                    'instagram' => ($request->instagram) ? $request->instagram : $advert->instagram,
                ]);

            } else {

                //Updates the property
                $this->savePropertyVideo($request, $advert->property->property_id);

                $property = $advert->property()->update([
                    'property_type' => ($request->property_type) ? json_encode($request->property_type) : $advert->property->property_type,
                    'description' => ($request->description) ? $request->description : $advert->property->description,
                    'complement' => ($request->complement) ? $request->complement : $advert->property->complement,
                    'cep' => ($request->cep) ? $request->cep : $advert->property->cep,
                    'number' => ($request->number) ? $request->number : $advert->property->number,
                    'street' => ($request->street) ? $request->street : $advert->property->street,
                    'neighborhood' => ($request->neighborhood) ? $request->neighborhood : $advert->property->neighborhood,
                    'city' => ($request->city) ? $request->city : $advert->property->city,
                    'state' => ($request->state) ? $request->state : $advert->property->state,
                    'lat' => ($request->lat) ? $request->lat : $advert->property->lat,
                    'lng' => ($request->lng) ? $request->lng : $advert->property->lng,
                    'rooms' => ($request->rooms) ? $request->rooms : $advert->property->rooms,
                    'bathrooms' => ($request->bathrooms) ? $request->bathrooms : $advert->property->bathrooms,
                    'parking_spaces' => ($request->parking_spaces) ? $request->parking_spaces : $advert->property->parking_spaces,
                    'area' => ($request->area) ? $request->area : $advert->property->area,
                    'tour' => ($request->tour) ? $request->tour : $advert->property->tour
                ]);

                if ($request->site == '' || $request->site == 'null' || $request->site == null) {
                    $request->site = null;
                }

                $userPicture = false;
                if ($request->user_picture == 'true') {
                    $userPicture = true;
                } else {
                    $userPicture = false;
                }

                //Updates the advert
                $advert->update([
                    'price' => ($request->price) ? $request->price : $advert->price,
                    'condo' => (isset($request->condo)) ? $request->condo : $advert->condo,
                    'transaction' => ($request->transaction) ? $request->transaction : $advert->transaction,
                    'user_picture' => $userPicture,
                    'phone' => ($request->phone) ? json_encode($request->phone) : $advert->phone,
                    'email' => ($request->email) ? json_encode($request->email) : $advert->email,
                    'site' => ($request->site) ? $request->site : $advert->site,
                    'facebook' => ($request->facebook) ? $request->facebook : $advert->facebook,
                    'instagram' => ($request->instagram) ? $request->instagram : $advert->instagram
                ]);

            }

            $advert = Advert::find($advert->advert_id);
            $advert->property;
            $advert->property->picture = json_decode($advert->property->picture);
            $advert->phone = json_decode($advert->phone);
            $advert->email = json_decode($advert->email);

            if($advert->advert_type != 'development') {
                $advert->property->property_type = json_decode($advert->property->property_type);
            }

            return response()->json(['advert' => $advert], 200);
        } else {
            return response()->JSON(['error' => 'Advert doesn\'t belong to user'], 403);
        }
    }

    private function updateLogo(Request $request, Advert $advert) {
        if($request->hasFile('logo')) {
            if($advert->development->logo != null) {
                Storage::delete('public/' . $advert->development->logo);
            }

            $logo = $request->file('logo');
            $savedFile = Storage::put("public/developments/" . $advert->development->id, $logo);
            $savedFile = (explode('public/', $savedFile))[1];

            DevelopmentAd::find($advert->development->id)->update(['logo' => $savedFile]);
        }
    }

    private function updatePropertyPictures($pictures, $propertyID, $advertType){
        $urlsVector = [];
        foreach ($pictures as $picture){
            if(gettype($picture) == 'string'){
                //If it is already an image, just keeps the current url in the vector
                $urlsVector[] = $picture;
            } else {
                //If it is a file, saves the file in the server
                if (exif_imagetype($picture)) {
                    //Saves the file in the disk
                    $savedFile = ($advertType == 'development') ? Storage::put("public/developments/$propertyID", $picture) : Storage::put("public/properties/$propertyID", $picture);
                    $urlsVector[] = (explode('public/', $savedFile))[1];
                }
            }
        }

        //Deletes the unwanted pictures
        if($advertType == 'development') {
            $currentPictures = json_decode(DevelopmentAd::find($propertyID)->picture);
            if($currentPictures) {
                foreach ($currentPictures as $currentPicture) {
                    if(gettype(array_search($currentPicture, $pictures)) != 'integer'){
                        //Deletes the image from the server
                        Storage::delete('public/' . $currentPicture);
                    }
                }
            }

            //Saves the new pictures urls in the database
            DevelopmentAd::find($propertyID)->update(['picture' => json_encode($urlsVector)]);

            return $urlsVector;
        } else {
            $currentPictures = json_decode(Property::find($propertyID)->picture);
            foreach ($currentPictures as $currentPicture) {
                if(gettype(array_search($currentPicture, $pictures)) != 'integer'){
                    //Deletes the image from the server
                    Storage::delete('public/' . $currentPicture);
                }
            }

            //Saves the new pictures urls in the database
            Property::find($propertyID)->update(['picture' => json_encode($urlsVector)]);

            return $urlsVector;
        }
    }

    public function delete(Advert $advert){
        //Deletes the informed advert if it belongs to the current user
        if($advert->user_id == Auth::user()->user_id){
            //Deletes the images
            if($advert->advert_type == 'development') {
                Storage::deleteDirectory('public/developments/' . $advert->property_id);

                foreach ($advert->development->properties as $property) {
                    $property->delete();
                }

                if($advert->development()->delete()){
                    //Deletes the advert
                    if($advert->delete()) {
                        return $this->integrationJSON('response', 'Advert ' . $advert->advert_id . ' deleted', 200);
                    }
                }
            } else {
                Storage::deleteDirectory('public/properties/' . $advert->property_id);

                if($advert->property()->delete()){
                    //Deletes the advert
                    if($advert->delete()) {
                        return $this->integrationJSON('response', 'Advert ' . $advert->advert_id . ' deleted', 200);
                    }
                }
            }
        } else{
            return response()->json(['error' => 'The advert do not belongs to the user'], 400);
        }
    }

    public function share(Request $request, Advert $advert){
        $this->validate($request, [
            'link' => 'required',
            'to' => 'required|email',
            'name' => 'required'
        ]);

        Mail::to($request->to)->send(new ShareAdvert($request->name, $request->link, $request->message, $advert));

        return response()->JSON(['success' => 'Email sent'], 200);
    }

    public function activate(Advert $advert){
        //Reactivates a deactivated advert
        if($advert->plan->payment_status == '2'){
            //Checks if it yet has free adverts
            $advertsCount = $advert->plan->adverts->where('status', '1')->count();
            $maxAdverts = $advert->plan->planRule->adverts_number;
            if($advertsCount < $maxAdverts){
                $advert->update(['status' => '1']);
                return response()->JSON(['success' => 'Advert reactivated with success'], 200);
            } else {
                //Search the others user's plan
                return $this->searchPlan($advert);
            }
        } else {
            return $this->searchPlan($advert); 
        }
    }

    private function searchPlan(Advert $advert){
        $availablePlan = null;
        foreach($advert->user->plans as $plan) {
            if ($plan->payment_status == '2'){
                $advertsCount = $plan->adverts->count();
                $maxAdverts = $plan->planRule->adverts_number;
                if($advertsCount<$maxAdverts){
                    $availablePlan = $plan->plan_id;
                    break;
                }
            }
        }

        if ($availablePlan != null) {
            $advert->update([
                'plan_id' => $availablePlan,
                'status' => 1
            ]);

            return response()->JSON(['success' => 'Advert reactivated with success'], 200);
        } else {
            return response()->JSON(['error' => 'User doesn\'t have any available plan'], 400);
        }
    }

    public function deactivate(Advert $advert){
        $user = Auth::user();

        if($advert->user_id == $user->user_id) {
            //Deactivates the advert
            $advert->update(['status' => 0]);

            return response()->JSON(['success' => 'Advert deactivated with success'], 200);
        } else {
            return response()->JSON(['error' => 'Advert doesn\'t belong to user'], 400);
        }
    }

   private function validateDataAdverts(Request $request){
       $this->validate($request, [
           'property_type' => [
               'required',
               'array',
               Rule::in(['apartment', 'house', 'condo', 'farmhouse', 'flat', 'studio', 'land', 'roof', 'warehouse', 'commercial_set', 'farm', 'store', 'commercial_room', 'commercial_building'])
           ],
           'description' => 'required',
           'cep' => 'required|size:8',
           'number' => 'required|numeric',
           'street' => 'required',
           'neighborhood' => 'required',
           'city' => 'required',
           'state' => 'required|size:2',
           'lat' => 'required|numeric',
           'lng' => 'required|numeric',
           'rooms' => 'required|numeric',
           'bathrooms' => 'required|numeric',
           'parking_spaces' => 'required|numeric',
           'area' => 'required|numeric',
           'picture' => 'required',
           'transaction' => 'required|array',
           'email' => 'required'
       ]);
   }
}
