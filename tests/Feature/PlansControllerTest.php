<?php

namespace Tests\Feature;

use App\PlanRule;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlansControllerTest extends TestCase
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
   public function testGetUserPlans(){
       // Criar Usuário
       $user = $this -> token (1);
       // Gerar uma regra de plano
       $planRule = PlanRule::create([
           'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
           'profile_type' => 1,
           'adverts_number' => 10,
           'images_per_advert' => 25,
           'price' => 79.90,
           'validity' => 30,
           'renewable' => 1
       ]);
       // Gerar plano para o Usuário
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
       // Obter informações de plano dentro do API
       $response = $this -> withHeader('Authorization', "Bearer $user->token") -> json('GET','/api/v1/plans');
       // Pegar data do banco de dados
       $validity = $user -> plans [0] -> paymentOrders () ->orderBy ('scheduling_date', 'desc') -> first () ->scheduling_date;
       $validity = (new Carbon($validity)) -> toDateString();
       // Fazer as asserções
       $response -> assertStatus(200);
       $response -> assertJson([
           'plans'=>[
               '0'=>[
                   'validity' => $validity
               ]
           ]
       ]);
       // Deletar plano e usuário
       $user -> plans() -> delete ();
       $user -> delete();

   }
}
