<?php

namespace Tests\Feature;

use App\Advert;
use App\Console\Commands\DeleteOldAdverts;
use App\DevelopmentAd;
use App\Plan;
use App\PlanRule;
use App\Property;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronDeleteOldAdvertsTest extends TestCase
{
    public function createUserPlan($profileType) {
        $user = User::create([
            'name' => 'Gabriel Castro',
            'email' => 'teste@teste.com',
            'password' => bcrypt('abcd1234'),
            'profile_type' => $profileType,
            'phone' => '(35) 98765-4321',
            'cpf_cnpj' => '123.456.789-00'
        ]);
        $planRule = PlanRule::create([
            'description' => 'Anúncio único válido por 30 dias',
            'profile_type' => $profileType,
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'price' => 49.90,
            'validity' => 30,
            'renewable' => 0
        ]);

        $plan = Plan::create([
            'user_id' => $user->user_id,
            'plan_rule_id' => $planRule->plan_rule_id,
            'signature_date' => Carbon::now(),
            'discount_code' => 'wizzer100',
            'payment_id' => 'FREE',
            'payment_status' => 2
        ]);

        return $user;
    }
    public function createProperty($user, $developmentId = null) {
        $property = Property::create([
            'user_id' => $user->user_id,
            'development_id' => $developmentId,
            'property_type' => json_encode(['apartment']),
            'description' => 'Apartamento em Varginha',
            'complement' => 'Ap 103',
            'cep' => '37031316',
            'number' => '160',
            'street' => 'Rua Dr Benedito Hélio Gonçalves',
            'neighborhood' => 'Vale das Palmeiras',
            'city' => 'Varginha',
            'state' => 'MG',
            'lat' => 50,
            'lng' => 50,
            'rooms' => 2,
            'bathrooms' => 2,
            'parking_spaces' => 1,
            'area' => 80,
            'quantity' => 1,
            'picture' => json_encode(['picture.jpg'])
        ]);

        return $property;
    }
    public function createDevelopment() {
        $developmentAd = DevelopmentAd::create([
            'title' => 'Residencial Vale das Palmeiras',
            'description' => 'Prédio Residencial com 8 apartamentos',
            'number' => '160',
            'street' => 'Rua Dr Benedito Hélio Gonçalves',
            'neighborhood' => 'Vale das Palmeiras',
            'city' => 'Varginha',
            'state' => 'MG',
            'cep' => '37031316',
            'lat' => 50,
            'lng' => 50,
            'picture' => json_encode(['picture.jpg'])
        ]);

        return $developmentAd;
    }
    public function createAdvert($user, $propertyId) {
        $advert = Advert::create([
            'plan_id' => $user->plans[0]->plan_id,
            'property_id' => $propertyId,
            'user_id' => $user->user_id,
            'price' => 1000,
            'condo' => 100,
            'transaction' => 'alugar',
            'status' => 0,
            'user_picture' => false,
            'phone' => json_encode([['(35) 98765-4321', true]]),
            'email' => json_encode(['teste@teste.com']),
            'advert_type' => $user->profile_type == 2 ? 'development' : 'default',
            'view_count' => 0,
            'message_count' => 0,
            'call_count' => 0
        ]);

        return $advert;
    }

    public function testAdvertNotOlderThan60Days() {
        $user = $this->createUserPlan(0);
        $property = $this->createProperty($user);
        $advert = $this->createAdvert($user, $property->property_id);

        //Calls the command
        $deleteOldAdverts = new DeleteOldAdverts();
        $deleteOldAdverts->handle();

        $dbAdvert = Advert::find($advert->advert_id)->count();
        $dbProperty = Property::find($property->property_id)->count();

        $this->assertEquals(1, $dbAdvert);
        $this->assertEquals(1, $dbProperty);

        $advert->delete();
        $property->delete();
        $user->delete();
    }
    public function testDevelopmentNotOlderThan60Days() {
        $user = $this->createUserPlan(2);
        $development = $this->createDevelopment();
        $property = $this->createProperty($user, $development->id);
        $advert = $this->createAdvert($user, $development->id);

        //Calls the command
        $deleteOldAdverts = new DeleteOldAdverts();
        $deleteOldAdverts->handle();

        $dbAdvert = Advert::find($advert->advert_id)->count();
        $dbDevelopment = DevelopmentAd::find($development->id)->count();
        $dbProperty = Property::find($property->property_id)->count();

        $this->assertEquals(1, $dbAdvert);
        $this->assertEquals(1, $dbDevelopment);
        $this->assertEquals(1, $dbProperty);

        $advert->delete();
        $property->delete();
        $development->delete();
        $user->delete();
    }
    public function testAdvertOlderThan60Days() {
        $user = $this->createUserPlan(0);
        $property = $this->createProperty($user);
        $advert = $this->createAdvert($user, $property->property_id);

        //Updates the updated_at for more than five days ago
        DB::table('adverts')->where('advert_id', $advert->advert_id)->update([
            'updated_at' => Carbon::now()->subDays(70)
        ]);

        //Calls the command
        $deleteOldAdverts = new DeleteOldAdverts();
        $deleteOldAdverts->handle();

        $dbAdvert = Advert::find($advert->advert_id);
        $dbProperty = Property::find($property->property_id);

        $this->assertNull($dbAdvert);
        $this->assertNull($dbProperty);

        $user->delete();
    }
    public function testDevelopmentOlderThan60Days() {
        $user = $this->createUserPlan(2);
        $development = $this->createDevelopment();
        $property = $this->createProperty($user, $development->id);
        $advert = $this->createAdvert($user, $development->id);

        //Updates the updated_at for more than five days ago
        DB::table('adverts')->where('advert_id', $advert->advert_id)->update([
            'updated_at' => Carbon::now()->subDays(70)
        ]);

        //Calls the command
        $deleteOldAdverts = new DeleteOldAdverts();
        $deleteOldAdverts->handle();

        $dbAdvert = Advert::find($advert->advert_id);
        $dbDevelopment = DevelopmentAd::find($development->id);
        $dbProperty = Property::find($property->property_id);

        $this->assertNull($dbAdvert);
        $this->assertNull($dbDevelopment);
        $this->assertNull($dbProperty);

        $user->delete();
    }
}
