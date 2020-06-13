<?php

namespace Tests\Feature;

use App\Console\Commands\CheckNextPayment;
use App\PaymentOrder;
use App\PlanRule;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronCheckNextPaymentTest extends TestCase
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

    public function testCheckNextPayment()
    {
        //Creates the user
        $user = $this->token(1);

        //Creates the plan_rule
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //User signs the plan
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

        //Removes the status 1 and updates the status 2 to 1
        PaymentOrder::where('status', '1')->delete();
        PaymentOrder::where('status', '2')->first()->update([
            'status' => '1',
            'scheduling_date' => Carbon::now()->subDay()
        ]);

        //Calls the cronjob
        $checkNextPayment = new CheckNextPayment();

        $checkNextPayment->handle();

        //Asserts
        $paymentOrders = $user->plans[0]->paymentOrders()->orderBy('scheduling_date', 'asc')->get();

        $this->assertEquals(2, $paymentOrders->count());
        $this->assertEquals(2, $paymentOrders[0]->status);
        $this->assertEquals(1, $paymentOrders[1]->status);
        $this->assertEquals(1, $user->plans[0]->payment_status);

        //Remove the rows
        $user->plans()->delete();
        $planRule->delete();
    }
}
