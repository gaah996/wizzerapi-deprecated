<?php

namespace Tests\Feature;

use App\Advert;
use App\Console\Commands\DeactivatePersonalPayment;
use App\Plan;
use App\PlanRule;
use App\User;
use Carbon\Carbon;
use App\Property;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronDeactivatePersonalPaymentTest extends TestCase
{
    public function token($profileType = 0) {
        User::where('email', 'teste@teste.com')->delete();

        $user = User::create([
            'name' => 'Gabriel Castro',
            'email' => 'teste@teste.com',
            'password' => bcrypt('abcd1234'),
            'profile_type' => $profileType,
            'phone' => '(35) 98765-4321',
            'cpf_cnpj' => '123.456.789-00'
        ]);

        $user->token = $user->createToken('PHPUnit')->accessToken;

        return $user;
    }
    public function testDeactivateWhenExpired() {
        $user = $this->token();

        $planRule = PlanRule::create([
            'description' => 'Anúncio único válido por 30 dias',
            'profile_type' => 0,
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

        $property = Property::create([
            'user_id' => $user->user_id,
            'property_type' => json_encode(['apartment']),
            'description' => 'Descrição do imóvel',
            'complement' => 'Ap 103',
            'cep' => '37031316',
            'number' => 160,
            'street' => 'Rua Dr. Benedito Hélio Gonçalves',
            'neighborhood' => 'Residencial Vale das Palmeiras',
            'city' => 'Varginha',
            'state' => 'MG',
            'lat' => 50,
            'lng' => 50,
            'rooms' => 2,
            'bathrooms' => 2,
            'parking_spaces' => 1,
            'area' => 80,
            'picture' => json_encode(['picture.jpg'])
        ]);
        $advert = Advert::create([
            'plan_id' => $plan->plan_id,
            'property_id' => $property->property_id,
            'user_id' => $user->user_id,
            'price' => 700,
            'condo' => 100,
            'transaction' => 'alugar',
            'status' => 1,
            'user_picture' => false,
            'phone' => json_encode([['(35) 98765-4321', false]]),
            'email' => json_encode(['teste@teste.com']),
            'advert_type' => 'default',
            'view_count' => 0,
            'message_count' => 0,
            'call_count' => 0
        ]);

        //Call the cronjob and certifies the plan is still valid
        $paymentDeactivateP = new DeactivatePersonalPayment();
        $paymentDeactivateP->handle();

        $plan = Plan::find($plan->plan_id);
        $advert = Advert::find($advert->advert_id);

        $this->assertEquals(2, $plan->payment_status);
        $this->assertEquals(1, $advert->status);

        //Change the signature date to simulate an expired plan
        $plan->update([
            'signature_date' => Carbon::now()->subDays(45)
        ]);

        //Call the cronjob and certifies the plan is not valid anymore
        $paymentDeactivateP->handle();

        $plan = Plan::find($plan->plan_id);
        $advert = Advert::find($advert->advert_id);

        $this->assertEquals(-1, $plan->payment_status);
        $this->assertEquals(0, $advert->status);

        $advert->delete();
        $property->delete();
        $plan->delete();
        $planRule->delete();
        $user->delete();
    }
    public function testRealocateAdvert(){
        //Criar o usuário
        $user = User::create([
            'name' => 'Gabriel Castro',
            'email' => 'teste@teste.com',
            'password' => bcrypt('abcd1234'),
            'profile_type' => $profileType,
            'phone' => '(35) 98765-4321',
            'cpf_cnpj' => '123.456.789-00'
        ]);
        //Criar a regra de plano
        $planRule = PlanRule:: create ([
            'description' => 'plano',
            'profile_type' => '0',
            'adverts_number' => '1',
            'images_per_advert' => '2',
            'price' => '49.90',
            'validity' => '30',
            'renewable' => '0'
        ]);
        //Criar dois planos
        $plan1 = Plan::create ([
            'signature_date'=> '2019-05-30',
            'payment_id' => 'free',
            'payment_status' => '2',
            'user_id' => $user->user_id,
            'plan_rule_id' => $planRule->plan_rule_id
        ]);
        $plan2 = Plan::create ([
            'signature_date'=> '2019-07-30',
            'payment_id' => 'free',
            'payment_status' => '2',
            'user_id' => $user->user_id,
            'plan_rule_id' => $planRule->plan_rule_id
        ]);
        //Criar o anúncio
        $property = Property::create([
            'property_type' => json_encode([
                'apartment'
            ]),
            'description'=> 'Novo imóvel',
            'cep' => '12345678',
            'number'=>'167',
            'street' => 'Pasteur',
            'neighborhood' => 'Novo Horizonte',
            'city'=> 'Varginha',
            'state' => 'MG',
            'lat'=> '50',
            'lng'=>'50',
            'rooms'=>'2',
            'bathrooms'=>'2',
            'parking_spaces'=>'2',
            'area'=> '300',
            'picture'=>'photo.jpg',
            'user_id'=> $user -> user_id
        ]);
        $advert = Advert::create([
            'price'=> '500000',
            'transaction'=> 'vender',
            'status'=> '1',
            'user_picture'=> '0',
            'phone'=>'3445-6767',
            'email'=>'teste@teste.com',
            'view_count'=>'0',
            'message_count'=> '0',
            'call_count'=>'0',
            'property_id'=>$property ->property_id,
            'user_id'=> $user-> user_id,
            'plan_id'=> $plan1 -> plan_id,
            'advert_type'=>'default'
        ]);
        //Desativar um dos planos

        //Chamar o cronjob
        $deactivatePersonalPayment= new DeactivatePersonalPayment();
        $deactivatePersonalPayment->handle();
        //Chamar as asserções
        $plan1=Plan::find($plan1 -> plan_id);
        $plan2=Plan::find($plan2 -> plan_id);
        $advert=Advert::find($advert ->advert_id);
        $this ->assertEquals(-1,$plan1 -> payment_status);
        $this -> assertEquals(1,$advert->status);
        $this -> assertEquals($plan2-> plan_id,$advert->plan_id);
        //Deletar o anúncio, os planos e o usuário
        $advert -> delete();
        $plan1 -> delete();
        $plan2 -> delete();
        $planRule -> delete();
        $user -> delete();
    }
}
