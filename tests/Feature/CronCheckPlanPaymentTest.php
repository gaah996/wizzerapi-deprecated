<?php

namespace Tests\Feature;

use App\Console\Commands\CheckPlanPayment;
use App\PlanRule;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronCheckPlanPaymentTest extends TestCase
{
    //*** IMPORTANT INFORMATION ***
    //For these tests to be able to pass you have to go to the
    //pag seguro API and approve the payment (for the first test),
    //or refuse it (for the second test).

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

    public function testSuccessfulPayment()
    {
        //Create user
        $user = $this->token(1);

        //Create plan rule
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //Sign a new plan
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

        //Waits for the tester to accept the payment
        sleep(15);

        //Checks the payment in the pag seguro API
        $checkPlanPayment = new CheckPlanPayment();

        $checkPlanPayment->handle();

        //Asserts
        $paymentOrders = $user->plans[0]->paymentOrders()->orderBy('scheduling_date', 'asc')->get();

        $this->assertEquals(2, $paymentOrders->count());
        $this->assertEquals(5, $paymentOrders[0]->status);
        $this->assertEquals(2, $user->plans[0]->payment_status);

        $user->plans()->delete();
        $planRule->delete();
    }
    public function testRefusedPayment() {
        //Create user
        $user = $this->token(1);

        //Create plan rule
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //Sign a new plan
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

        //Waits for the tester to accept the payment
        sleep(15);

        //Checks the payment in the pag seguro API
        $checkPlanPayment = new CheckPlanPayment();

        $checkPlanPayment->handle();

        //Asserts
        $paymentOrders = $user->plans[0]->paymentOrders()->orderBy('scheduling_date', 'asc')->get();

        $this->assertEquals(2, $paymentOrders->count());
        $this->assertEquals(6, $paymentOrders[0]->status);
        $this->assertEquals(1, $user->plans[0]->payment_status);

        $user->plans()->delete();
        $planRule->delete();
    }

    public function testCheckPlanPaymentBoleto() {
        //Create the user
        $user = $this->token(1);
        //Create the plan rule
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //Buys the plan
        $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'boleto',
                'plan_id' => $planRule->plan_rule_id,
                'sender_hash' => $this->senderHash,
            ]);

        //Change the statuses
        $user->plans()->first()->update([
            'payment_status' => '1'
        ]);
        $user->plans()->first()->paymentOrders()->orderBy('scheduling_date', 'asc')->first()->update([
            'status' => '2'
        ]);

        //Call the cron job
        $command = new CheckPlanPayment();
        $command->handle();

        //Asserts
        $this->assertEquals(2, $user->plans()->first()->payment_status);
        $this->assertEquals(5, $user->plans()->first()->paymentOrders()->orderBy('scheduling_date', 'asc')->first()->status);
    }
}
