<?php

namespace Tests\Feature;

use App\Console\Commands\CheckRefusedPayment;
use App\Plan;
use App\PlanRule;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronCheckRefusedPaymentTest extends TestCase
{
    public function initialConfiguration(int $days) {
        //Create the user
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

        //Create a plan
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
        $plan = Plan::where('user_id', $user->user_id)->first();

        //Approves the plan
        $plan->update(['payment_status' => 2]);

        //Change the last event date for X days ago
        //Change the status to 6
        $paymentOrder = $plan->paymentOrders()->orderBy('scheduling_date', 'asc')->first();
        $paymentOrder->update([
            'last_event_date' => (new Carbon($paymentOrder->last_event_date))->subDays($days),
            'scheduling_date' => (new Carbon($paymentOrder->scheduling_date))->subDays($days),
            'status' => 6
        ]);

        $return = new \stdClass();
        $return->user = $user;
        $return->plan = $plan;

        return $return;
    }

    public function testRefusedPaymentApproved() {
        //Initial configuration
        $info = $this->initialConfiguration(3);

        //Waits for 15 seconds to approve the payment in pag seguro
        sleep(15);

        //Calls the cron
        $checkRefusedPayment = new CheckRefusedPayment();
        $checkRefusedPayment->handle();

        //Asserts
        $paymentOrder = $info->plan->paymentOrders()->orderBy('scheduling_date', 'asc')->first();
        $this->assertEquals('2', $paymentOrder->status);

        $info->plan->delete();
    }
    public function testRefusedPaymentMaxTries() {
        //Initial configuration
        $info = $this->initialConfiguration(15);

        //Waits for 15 seconds to approve the payment in pag seguro
        sleep(15);

        //Calls the cron
        $checkRefusedPayment = new CheckRefusedPayment();
        $checkRefusedPayment->handle();

        //Asserts
        $paymentOrder = $info->plan->paymentOrders()->orderBy('scheduling_date', 'asc')->first();
        $this->assertEquals('2', $paymentOrder->status);

        $info->plan->delete();
    }
    public function testRefusedPaymentExpired() {
        //Initial configuration
        $info = $this->initialConfiguration(16);

        //Calls the cron
        $checkRefusedPayment = new CheckRefusedPayment();
        $checkRefusedPayment->handle();

        //Asserts
        $plan = Plan::find($info->plan->plan_id);
        $paymentOrder = $info->plan->paymentOrders()->orderBy('scheduling_date', 'asc')->first();
        $this->assertEquals('-1', $paymentOrder->status);
        $this->assertEquals('0',$plan->payment_status);

        $info->plan->delete();
    }

    public function testRefusedPaymentBoleto() {
        //Create the user
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

        //Create a plan
        $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'boleto',
                'plan_id' => $planRule->plan_rule_id,
                'sender_hash' => $this->senderHash
            ]);
        $plan = Plan::where('user_id', $user->user_id)->first();

        //Approves the plan
        $plan->update(['payment_status' => 2]);

        //Change the last event date for X days ago
        //Change the status to 6
        $paymentOrder = $plan->paymentOrders()->orderBy('scheduling_date', 'asc')->first();
        $paymentOrder->update([
            'last_event_date' => (new Carbon($paymentOrder->last_event_date))->subDays(16),
            'scheduling_date' => (new Carbon($paymentOrder->scheduling_date))->subDays(16),
            'status' => 6
        ]);

        //Call the command
        $checkRefusedPayment = new CheckRefusedPayment();
        $checkRefusedPayment->handle();

        //Asserts
        $this->assertEquals(-1, Plan::where('user_id', $user->user_id)->first()->payment_status);
        foreach ($plan->paymentOrders as $paymentOrder) {
            $this->assertEquals(-1, $paymentOrder->status);
        }
    }
}
