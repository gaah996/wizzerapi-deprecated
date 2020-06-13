<?php

namespace Tests\Feature;

use App\Console\Commands\CheckPersonalPayment;
use App\Plan;
use App\PlanRule;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronCheckPersonalPaymentTest extends TestCase
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
    public function testCronJob()
    {
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

        $response = $this->withHeader('Authorization', "Bearer $user->token")
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

        $plan = Plan::where('user_id', $user->user_id)->first();
        $plan->update([
            'payment_status' => '6'
        ]);
        $this->assertEquals(6, $plan->payment_status);

        //Calls the cronjob
        $checkPersonalPayment = new CheckPersonalPayment();
        $checkPersonalPayment->handle();

        $plan = Plan::where('user_id', $user->user_id)->first();
        $this->assertEquals(6, $plan->payment_status);
        $plan->update([
            'payment_status' => '1'
        ]);

        $checkPersonalPayment->handle();
        $plan = Plan::where('user_id', $user->user_id)->first();

        $this->assertEquals(2, $plan->payment_status);

        $plan->delete();
        $planRule->delete();
    }
}
