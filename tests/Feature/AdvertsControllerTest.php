<?php

namespace Tests\Feature;

use App\Plan;
use App\PlanRule;
use App\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvertsControllerTest extends TestCase
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
    public function testCreateAdvert( ){
        //Criar usuário
        $user = $this-> token(1);
        // Criar a regra de plano
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);
        // Criar o plano
        $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'creditCard',
                'plan_id' => $planRule->plan_rule_id,
                'sender_hash' => $this->senderHash,
                'credit_card_token' => $this->creditCardToken,
                'holder_name' => 'Gabriel Castro',
                'holder_document' => '12345678900',
                'holder_birth_date' => '26/01/1996',
                'billing_street' => 'Rua Dr Benedito Hélio Gonçalves',
                'billing_number' => '160',
                'billing_district' => 'Vale das Palmeiras',
                'billing_complement' => 'AP103',
                'billing_city' => 'Varginha',
                'billing_state' => 'MG',
                'billing_zip'  => '37031316'
            ]);
        // Aprovar o plano
        $plan = Plan:: where('user_id',$user -> user_id) -> first();
        $plan -> update(['payment_status'=>2]);
        //Criar o anúncio
        $response = $this -> withHeader('Authorization', 'Bearer'. $user->token) -> json('POST', '/api/v1/props', [
            'property_type'=>[
                'apartment'
            ],'description'=> 'apartment',
            "complement" =>'apartment1',
            'number' => '3',
            'street'=> 'Rua',
            'neighborhood' =>'bairro',
            'city'=> 'Varginha',
            'state' => 'MG',
            'cep'=> '37026000',
            'lat'=> '50',
            'lng'=>'50',
            'rooms'=> '2',
            'bathrooms'=> '2',
            'parking_spaces' =>'1',
            'area'=>'100',
            'price_sell'=> '100000',
            'transaction'=>[
                'vender'
            ],
            'picture' =>[
                UploadedFile::fake()->image('avatar.jpg')

            ],
            'user_picture'=>'0',
            'phone'=> [
                [
                    '(35) 3221-2233'
                ],[
                    false
                ]
            ],
            'email'=> [
                'teste@teste.com'
            ]

        ]);

        //Fazer as asserções
        $response->assertStatus(200);
        $this-> assertEquals(1, $user-> adverts()->count());
        $this->assertEquals(1, $user->adverts()-> first()->status );
        //Deletar o anúncio, plano e usuário
        $user-> adverts()-> delete();
        $plan-> delete();
        $user->delete();
    }
}
