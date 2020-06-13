<?php

namespace Tests\Feature;

use App\Property;
use App\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PropertiesControllerTest extends TestCase
{
    public function testindex()
    {
        $user = User::create([
            'name' => 'Gabriel Castro',
            'email' => 'teste@teste.com',
            'password' => bcrypt('abcd1234'),
            'profile_type' => $profileType,
            'phone' => '(35) 98765-4321',
            'cpf_cnpj' => '123.456.789-00'
        ]);
        $token = $user->createToken('Token')->accessToken;

        $response=$this->withHeaders(['Authorization'=>'Bearer ' . $token])->json('get', '/api/v1/properties',[
            Property::all()
        ]);
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'properties');

        $user->delete();
    }

    public function teststore(){
        $user = User::create([
            'name' => 'Fabio',
            'email' => 'email@teste.com',
            'password' => bcrypt('12345678'),
            'profile_type' => 0
        ]);
        $token = $user->createToken('Token')->accessToken;
        $response=$this->withHeaders(['Authorization'=>'Bearer ' . $token])->json('post', '/api/v1/properties',[
            'property_type' => ['apartment'],
            'description' => 'casa bonita',
            'complement' => 'A',
            'cep' => '37010640',
            'number' => '178',
            'street' => 'Rua Alvaro Mendes',
            'neighborhood' => 'Bom pastor',
            'city' => 'Varginha',
            'state' => 'MG',
            'lat' => '50',
            'lng' => '50',
            'rooms' => '2',
            'bathrooms' => '2',
            'parking_spaces' => '1',
            'area' => '500',
            'quantity' => '1',
            'price' => '1000',
            'picture' =>  [UploadedFile::fake()->image('avatar.jpg')]
        ]);

        $response->assertStatus(200);
        $property = Property::where('description','=','casa bonita')->first();
        $this->assertNotNull($property);
        $user->delete();
    }
    public function testupdate(){
        $user = User::create([
            'name' => 'Fabio',
            'email' => 'email@teste.com',
            'password' => bcrypt('12345678'),
            'profile_type' => 0
        ]);
        $token = $user->createToken('Token')->accessToken;
        $property = Property::create([
            'user_id' => $user->user_id,
            'property_type' => 'apartment',
            'description' => 'casa bonita',
            'complement' => 'A',
            'cep' => '37010640',
            'number' => '178',
            'street' => 'Rua Alvaro Mendes',
            'neighborhood' => 'Bom pastor',
            'city' => 'Varginha',
            'state' => 'MG',
            'lat' => '50',
            'lng' => '50',
            'rooms' => '2',
            'bathrooms' => '2',
            'parking_spaces' => '1',
            'area' => '500',
            'quantity' => '1',
            'price' => '1000',
            'tour' =>  null,
            'video' => null,
            'picture' =>  json_encode(['avatar.jpg']),
            'blueprint' =>null
        ]);
        $response=$this->withHeaders(['Authorization'=>'Bearer ' . $token])->json('post', '/api/v1/properties/' . $property->property_id,[
            'description'=>'casa bem localizada'
        ]);
        $response->assertStatus(200);
        $property = Property::find($property->property_id);
        $this->assertEquals('casa bem localizada', $property->description);
        $user->delete();
    }

    public function testshow(){
        $user = User::create([
            'name' => 'Fabio',
            'email' => 'email@teste.com',
            'password' => bcrypt('12345678'),
            'profile_type' => 0
        ]);
        $property = Property::create([
            'user_id' => $user->user_id,
            'property_type' => 'apartment',
            'description' => 'casa bonita',
            'complement' => 'A',
            'cep' => '37010640',
            'number' => '178',
            'street' => 'Rua Alvaro Mendes',
            'neighborhood' => 'Bom pastor',
            'city' => 'Varginha',
            'state' => 'MG',
            'lat' => '50',
            'lng' => '50',
            'rooms' => '2',
            'bathrooms' => '2',
            'parking_spaces' => '1',
            'area' => '500',
            'quantity' => '1',
            'price' => '1000',
            'tour' =>  null,
            'video' => null,
            'picture' =>  json_encode(['avatar.jpg']),
            'blueprint' =>null
        ]);
        $response = $this->json('get','/api/v1/properties/'. $property->property_id);
        $response->assertStatus(200);
        $this->assertArrayHasKey('properties', $response->json());
        $this->assertNotNull($property);

        $user->delete();
        $property->delete();
    }

    public function testdelete(){
        $user = User::create([
            'name' => 'Fabio',
            'email' => 'email@teste.com',
            'password' => bcrypt('12345678'),
            'profile_type' => 0
        ]);
        $token = $user->createToken('Token')->accessToken;
        $property = Property::create([
            'user_id' => $user->user_id,
            'property_type' => 'apartment',
            'description' => 'casa bonita',
            'complement' => 'A',
            'cep' => '37010640',
            'number' => '178',
            'street' => 'Rua Alvaro Mendes',
            'neighborhood' => 'Bom pastor',
            'city' => 'Varginha',
            'state' => 'MG',
            'lat' => '50',
            'lng' => '50',
            'rooms' => '2',
            'bathrooms' => '2',
            'parking_spaces' => '1',
            'area' => '500',
            'quantity' => '1',
            'price' => '1000',
            'tour' =>  null,
            'video' => null,
            'picture' =>  json_encode(['avatar.jpg']),
            'blueprint' =>null
        ]);
        $response = $this->withHeaders(['Authorization'=>'Bearer ' . $token])->json('delete', '/api/v1/properties/' . $property->property_id);
        $response->assertStatus(200);
        $property = Property::find($property->property_id);
        $this->assertNull($property);

        $user->delete();
    }
}
