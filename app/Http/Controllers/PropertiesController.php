<?php

namespace App\Http\Controllers;

use \Exception;
use App\Advert;
use http\Env\Response;
use Illuminate\Http\Request;
use App\Property;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Tests\ProcessTest;
use Guzzle\Http\Client;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\Psr7\str;
use GuzzleHttp\Exception\GuzzleException;
use phpDocumentor\Reflection\Types\Array_;

use function GuzzleHttp\json_encode;

class PropertiesController extends Controller
{
    public function index()
    {

        //          This is Function is  equivalent this is
        //          returning alls register the table properties

        try {
            $properties = Property::all();
            return response()->json(['properties' => $properties], 200);
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Couldn\'t return the properties'], 403);
        }
    }

    public function store(Request $request)
    {
        // This function inserts data in the database's property table
        $user = Auth::user();
        $this->validateDataProperties($request);

        try {
            $property = Property::create([
                'user_id' => $user->user_id,
                'description' => $request['description'],
                'complement' => $request['complement'],
                'cep' => $request['cep'],
                'number' => $request['number'],
                'street' => $request['street'],
                'neighborhood' => $request['neighborhood'],
                'city' => $request['city'],
                'state' => $request['state'],
                'lat' => $request['lat'],
                'lng' => $request['lng'],
                'rooms' => $request['rooms'],
                'bathrooms' => $request['bathrooms'],
                'parking_spaces' => $request['parking_spaces'],
                'area' => $request['area'],
                'quantity' => $request['quantity'],
                'price' => $request['price'],
                'tour' => $request['tour'],
                'video' => $this->saveVideo($request['video']),
                'picture' => json_encode($this->savePicture($request['picture'])),
                'blueprint' => json_encode($this->saveBlueprint($request['blueprint'])),
                'property_type' => json_encode($request['property_type'])
            ]);

            return response()->json(['success' => 'Property created', 'properties' => $property], 200);
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Couldn\'t create the property, try again'], 500);
        }
    }

    public function saveVideo($video)
    {
        return 'null';
    }

    public function savePicture($picture)
    {
        return 'null';
    }

    public function saveBlueprint($blueprint)
    {
        return 'null';
    }

    public function update($idProperty, Request $request)
    {
        $userId = Auth::user()->user_id;

        $this->validate($request, [
            'property_type' => 'array',
            'property_type.*' => 'in:apartment,house,condo,farmhouse,flat,studio,land,roof,warehouse,commercial_set,farm,store,commercial_room,commercial_building',
            'picture' => 'array',
            'picture.*' => 'image',
            'video' => 'file',
            'blueprint' => 'array',
            'blueprint.*' => 'image',
            'description' => 'nullable',
            'complement' => 'nullable',
            'cep' => 'size:8',
            'number' => 'nullable',
            'street' => 'nullable',
            'neighborhood' => 'nullable',
            'city' => 'nullable',
            'state' => 'size:2',
            'lat' => 'numeric',
            'lng' => 'numeric',
            'rooms' => 'numeric',
            'bathrooms' => 'numeric',
            'parking_spaces' => 'numeric',
            'area' => 'numeric',
            'quantity' => 'numeric',
            'price' => 'numeric',
            'tour' => 'nullable'
        ]);
        try {
            $propertExist = Property::findOrFail($idProperty);
            if ($userId == $propertExist->user_id) {
                try {

                    $request['property_type'] = json_encode($request['property_type']);
                    $request['picture'] = json_encode($this->savePicture($request['picture']));
                    $request['video'] = $this->saveVideo($request['video']);
                    $request['blueprint'] = json_encode($this->saveBlueprint($request['blueprint']));

                    $propertExist->update($request->only([
                        'description', 'complement', 'cep', 'number', 'street',
                        'neighborhood', 'city', 'state', 'lat', 'lng', 'rooms',
                        'bathrooms', 'parking_spaces', 'area', 'quantity', 'video',
                        'price', 'picture', 'blueprint', 'property_type', 'tour'
                    ]));

                    $propertExist = Property::find($propertExist->property_id);

                    return response()->json(['properties' => $propertExist], 200);
                } catch (\Exception $exception) {
                    return response()->json(['error' => 'Couldn\'t update the property'], 500);
                }
            } else {
                return response()->json(['error' => 'Property doesn\'t belong to user'], 403);
            }
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Couldn\'t find the property'], 404);
        }
    }
    public function delete($idProperty)
    {
        $userId = Auth::user()->user_id;

        try {
            $modelProperty = Property::findOrFail($idProperty);

            if ($userId == $modelProperty->user_id) {
                try {
                    $modelProperty->delete();
                    return response()->json(['success' => 'Property deleted with success'], 200);
                } catch (\Exception $exception) {
                    return response()->json(['error' => 'A problem occurred,the property doesn\'t deleted, try again'], 500);
                }
            } else {
                return response()->json(['error' => 'Property doesn\'t belong to user'], 403);
            }
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Couldn\'t find property'], 404);
        }
    }

    public function  show($idProperty)
    {
        //This function shows the asked property if it exists in the database
        try {
            $propertiesInformation = Property::findOrFail($idProperty);

            return response()->json(['properties' => $propertiesInformation], 200);
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Couldn\'t find property'], 404);
        }
    }
    public function newProperty(Advert $advert, Request $request)
    {
        $this->validate($request, [
            'property_type' => 'required|array',
            'title' => 'required',
            'property_type.*' => 'in:apartment,house,condo,farmhouse,flat,studio,land,roof,warehouse,commercial_set,farm,store,commercial_room,commercial_building',
            'description' => 'required|min:1',
            'complement' => 'nullable',
            'rooms' => 'required|numeric',
            'bathrooms' => 'required|numeric',
            'parking_spaces' => 'required|numeric',
            'area' => 'required|numeric',
            'price' => 'numeric',
            'quantity' => 'required|numeric',
            'price_min' => 'numeric|nullable',
            'price_max' => 'numeric|nullable'
        ]);

        $user = Auth::user();

        if ($advert->advert_type == 'development') {
            if ($advert->user_id == $user->user_id) {
                $property = Property::create([
                    'user_id' => $user->user_id,
                    'development_id' => $advert->property->id,
                    'property_type' => json_encode($request['property_type']),
                    'title' => $request['title'],
                    'description' => $request['description'],
                    'complement' => $request['complement'],
                    'cep' => $advert->property->cep ? $advert->property->cep : null,
                    'number' => $advert->property->number,
                    'street' => $advert->property->street,
                    'neighborhood' => $advert->property->neighborhood,
                    'city' => $advert->property->city,
                    'state' => $advert->property->state,
                    'lat' => $advert->property->lat,
                    'lng' => $advert->property->lng,
                    'rooms' => $request['rooms'],
                    'bathrooms' => $request['bathrooms'],
                    'parking_spaces' => $request['parking_spaces'],
                    'area' => $request['area'],
                    //                    'picture' => null,
                    'video' => null,
                    'price' => $request['price'],
                    'quantity' => $request['quantity'],
                    //                    'blueprint' => null,
                ]);

                //Save the pictures
                $this->savePictures($request, $advert->property->id, $property->property_id);

                $property = Property::find($property->property_id);
                $property->property_type = json_decode($property->property_type);
                $property->picture = $property->picture ? json_decode($property->picture) : null;
                $property->blueprint = $property->blueprint ? json_decode($property->blueprint) : null;

                $advert->price = $request['price_min'];
                $advert->price_max = $request['price_max'];
                $advert->save();

                return response()->JSON(['message' => 'success', 'property'=> $property], 200);
            } else {
                return response()->JSON(['error' => 'Advert doesn\'t belongs to user'], 400);
            }
        } else {
            return response()->JSON(['error' => 'Advert is not a development'], 400);
        }
    }

    public function savePictures(Request $request, $development_id, $property_id)
    {

        //Save the pictures
        if ($request->hasfile('picture')) {
            $savedFileArray = [];
            foreach ($request->file('picture') as $picture) {
                //Checks if file is an image
                if ($picture->getClientOriginalName() != "") {
                    if (exif_imagetype($picture)) {
                        $savedFile = Storage::put("public/developments/$development_id/$property_id", $picture);
                        $savedFileArray[] = (explode('public/', $savedFile))[1];
                    }
                }
            } 
            //Saves the path to the database
            Property::find($property_id)->update(['picture' => json_encode($savedFileArray)]);
        }

        //Save the blueprints
        if ($request->hasfile('blueprint')) {
            $savedFileArray = [];
            foreach ($request->file('blueprint') as $blueprint) {
                //Checks if file is an image
                if (exif_imagetype($blueprint)) {
                    //Saves the file in the disk
                    $savedFile = Storage::put("public/developments/$development_id/$property_id/blueprint", $blueprint);
                    $savedFileArray[] = (explode('public/', $savedFile))[1];
                }
            }
            //Saves the path to the database
            Property::find($property_id)->update(['blueprint' => json_encode($savedFileArray)]);
        }
    }

    public function updateProperty($subprop, Request $request)
    {
        $this->validate($request, [
            'property_type' => 'array',
            'property_type.*' => 'in:apartment,house,condo,farmhouse,flat,studio,land,roof,warehouse,commercial_set,farm,store,commercial_room,commercial_building'
        ]);

        $userId = Auth::user()->user_id;

        $property = Property::find($subprop);
        $advert = $property->development->advert;
        $advert->price = $request['price_min'];
        $advert->price_max = $request['price_max'];
        $advert->update();

        if (!$property) {
            return response()->json(['warning' => 'property ' . $subprop . ' not found'], 404);
        }

        if ($property->user_id == $userId) {
            try {
                $property->update([
                    'property_type' => (isset($request['property_type'])) ? json_encode($request['property_type']) : $property->property_type,
                    'title' => (isset($request['title']) ? $request['title'] : $property->title),
                    'description' => (isset($request['description'])) ? $request['description'] : $property->description,
                    'complement' => (isset($request['complement'])) ? $request['complement'] : $property->complement,
                    'rooms' => (isset($request['rooms'])) ? $request['rooms'] : $property->rooms,
                    'bathrooms' => (isset($request['bathrooms'])) ? $request['bathrooms'] : $property->bathrooms,
                    'parking_spaces' => (isset($request['parking_spaces'])) ? $request['parking_spaces'] : $property->parking_spaces,
                    'area' => (isset($request['area'])) ? $request['area'] : $property->area,
                    'price' => (isset($request['price'])) ? $request['price'] : $property->price,
                    'quantity' => (isset($request['quantity'])) ? $request['quantity'] : $property->quantity,
                ]);

                $this->updateImages($request, $property->development->id, $property->property_id);

                $property = Property::find($property->property_id);
                $property->property_type = json_decode($property->property_type);
                $property->picture = $property->picture?json_decode($property->picture):[];
                $property->blueprint = $property->blueprint?json_decode($property->blueprint):[];

                return response()->JSON($property, 200);
            } catch (\Exception $e) {
                return response()->JSON(['error' => 'Invalid field', 'message' => $e->getMessage()], 403);
            }
        } else {
            return response()->JSON(['error' => 'Property does not belong to the user'], 403);
        }
    }

    private function updateImages(Request $request, $development_id, $property_id)
    {
        //Updates the pictures
        if ($request['picture']) {
            $imagesVector = [];
            foreach ($request['picture'] as $picture) {
                if (gettype($picture) == 'string') {
                    //Keeps the image in the server
                    $imagesVector[] = $picture;
                } else {
                    //Save the new image
                    if (exif_imagetype($picture)) {
                        //Saves the file in the disk
                        $savedFile = Storage::put("public/developments/$development_id/$property_id", $picture);
                        $imagesVector[] = (explode('public/', $savedFile))[1];
                    }
                }
            }
            $pic = Property::find($property_id)->picture;
            $currentPictures = $pic ? json_decode($pic) : [];
            foreach ($currentPictures as $currentPicture) {
                if (gettype(array_search($currentPicture, $imagesVector)) != 'integer') {
                    //Deletes the image from the server
                    Storage::delete('public/' . $currentPicture);
                }
            }
            //Updates the database
            Property::find($property_id)->update(['picture' => json_encode($imagesVector)]);
        }

        //Updates the blueprints
        if ($request['blueprint']) {
            $blueprintsVector = [];
            foreach ($request['blueprint'] as $blueprint) {
                if (gettype($blueprint) == 'string') {
                    //Keeps the image in the server
                    $blueprintsVector[] = $blueprint;
                } else {
                    //Save the new image
                    if (exif_imagetype($blueprint)) {
                        //Saves the file in the disk
                        $savedFile = Storage::put("public/developments/$development_id/$property_id/blueprint", $blueprint);
                        $blueprintsVector[] = (explode('public/', $savedFile))[1];
                    }
                }
            }

            $currentBlueprints = json_decode(Property::find($property_id)->blueprint);
            foreach ($currentBlueprints as $currentBlueprint) {
                if (gettype(array_search($currentBlueprint, $blueprintsVector)) != 'integer') {
                    //Deletes the image from the server
                    Storage::delete('public/' . $currentBlueprint);
                }
            }
            //Updates the database
            Property::find($property_id)->update(['blueprint' => json_encode($blueprintsVector)]);
        }
    }

    public function deleteProperty($subprop)
    {
        $userId = Auth::user()->user_id;
        $property = Property::find($subprop);

        if ($property->user_id == $userId) {
            try {
                Storage::deleteDirectory('public/developments/' . $property->development->id . '/' . $property->property_id);

                $property->delete();
                return response()->JSON(['success' => 'Property deleted'], 200);
            } catch (\Exception $e) {
                return response()->JSON(['error' => 'Error while deleting property'], 400);
            }
        } else {
            return response()->JSON(['error' => 'Property does not belong to the user'], 403);
        }
    }

    public function getDevelopmentProperties(Advert $advert)
    {
        $properties = $advert->development_ad->properties;

        foreach ($properties as $index => $property) {
            $properties[$index]['property_type'] = json_decode($property->property_type);
            $properties[$index]['picture'] = json_decode($property->picture);
            $properties[$index]['video'] = json_decode($property->video);
            $properties[$index]['blueprint'] = json_decode($property->blueprint);
        }

        return response()->JSON([$properties], 200);
    }

    private function validateDataProperties(Request $request)
    {
        // This is function validate date that will be  send table plans.

        $this->validate($request, [
            'property_type' => 'required|array',
            'property_type.*' => 'in:apartment,house,condo,farmhouse,flat,studio,land,roof,warehouse,commercial_set,farm,store,commercial_room,commercial_building',
            'description' => 'required',
            'complement' => 'nullable',
            'cep' => 'required|size:8',
            'number' => 'required',
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
            'quantity' => 'required|numeric',
            'video' => 'nullable|file',
            'price' => 'numeric',
            'picture' => 'array',
            'picture.*' => 'image',
            'blueprint' => 'array',
            'blueprint.*' => 'image',
            'tour' => 'nullable'
        ]);
    }
}
