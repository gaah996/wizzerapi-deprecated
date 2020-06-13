<?php

namespace Tests\Feature;

use App\Console\Commands\CheckCancelledPlan;
use App\Plan;
use App\PlanRule;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CronCheckCancelledPlanTest extends TestCase
{
    public function initialConfiguration() {
        //Create user
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

        //Create plan
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

        //Change status to 0
        $plan->update(['payment_status' => '0']);

        $return = new \stdClass();
        $return->user = $user;
        $return->plan = $plan;

        return $return;
    }

    public function testStillValidPlan() {
        //Initial configuration
        $info = $this->initialConfiguration();

        //Call the cronjob
        $checkCancelledPlan = new CheckCancelledPlan();
        $checkCancelledPlan->handle();

        //Asserts
        $plan = Plan::find($info->plan->plan_id);

        $this->assertEquals('0', $plan->payment_status);

        $plan->delete();
    }
    public function testInvalidPlan() {
        //Initial configuration
        $info = $this->initialConfiguration();

        //Change last payment order scheduling date to today
        $paymentOrders = $info->plan->paymentOrders()->orderBy('scheduling_date', 'desc')->get();
        $paymentOrders[0]->update(['scheduling_date' => Carbon::now()]);
        $paymentOrders[1]->update(['scheduling_date' => Carbon::now()->subDay()]);

        //Call the cronjob
        $checkCancelledPlan = new CheckCancelledPlan();
        $checkCancelledPlan->handle();

        //Asserts
        $plan = Plan::find($info->plan->plan_id);

        $this->assertEquals('-1', $plan->payment_status);

        $plan->delete();
    }
}
