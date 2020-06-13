<?php

namespace Tests\Feature;

use App\DiscountCode;
use App\Http\Controllers\PagSeguroController;
use App\Plan;
use App\PlanRule;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PagSeguroControllerTest extends TestCase
{
//    use RefreshDatabase;

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
    public function testGetSession()
    {
        $user = $this->token();

        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->get('/api/v1/payment/session');

        $response->assertStatus(200);
        $this->assertInternalType('array', $response->json());
        $this->assertArrayHasKey('sessionId', $response->json());
    }

    public function testPersonalPayment() {
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

        $plan = Plan::where('user_id', $user->user_id)->get();

        $response->assertStatus(200);
        $this->assertInternalType('array', $response->json());
        $this->assertEquals(1, $plan->count());
        $this->assertEquals(2, $plan[0]->payment_status);

        $plan[0]->delete();
        $planRule->delete();
    }

    public function testPersonalPaymentWithDiscount() {
        //Authenticates the test user
        $user = $this->token();

        //Creates the plan rule
        $planRule = PlanRule::create([
            'description' => 'Anúncio único válido por 30 dias',
            'profile_type' => 0,
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'price' => 49.90,
            'validity' => 30,
            'renewable' => 0
        ]);

        //Creates the discount code
        $discountCode = DiscountCode::create([
            'discount_code' => 'wizzer60',
            'discount_percentage' => '60',
            'first_buy_only' => false,
            'number_of_uses' => 10
        ]);
        $planRule->discountCodes()->save($discountCode);

        //Make the payment
        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'creditCard',
                'discount_code' => $discountCode->discount_code,
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

        $plan = Plan::where('user_id', $user->user_id)->get();

        $response->assertStatus(200);
        $this->assertInternalType('array', $response->json());
        $this->assertEquals(1, $plan->count());
        $this->assertEquals(2, $plan[0]->payment_status);

        $discount = (new PagSeguroController())->validateDiscountCode($plan[0]->discount_code, $planRule->plan_rule_id, $user->user_id);
        $pricePaid = ((100 - $discount) / 100) * $planRule->price;

        $this->assertEquals(19.96, $pricePaid);

        $plan[0]->delete();
        $discountCode->delete();
        $planRule->delete();
    }

    public function testDiscountCodeValidation() {
        //Create and authenticate the user
        $user = $this->token();

        //Creates the plan rule
        $planRule = PlanRule::create([
            'description' => 'Anúncio único válido por 30 dias',
            'profile_type' => 0,
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'price' => 49.90,
            'validity' => 30,
            'renewable' => 0
        ]);

        //Creates the discount code
        $discountCode = DiscountCode::create([
            'discount_code' => 'wizzer60',
            'discount_percentage' => '60',
            'first_buy_only' => false,
            'number_of_uses' => 10
        ]);
        $planRule->discountCodes()->save($discountCode);

        //Make the request
        $validResponse = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/discount', [
                'discount_code' => $discountCode->discount_code,
                'plan_id' => $planRule->plan_rule_id
            ]);

        $invalidResponse = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/discount', [
                'discount_code' => $discountCode->discount_code . '100',
                'plan_id' => $planRule->plan_rule_id
            ]);

        //Valid response asserts
        $validResponse->assertStatus(200);
        $validResponse->assertJson([
            'discount_percentage' => 60
        ]);

        //Invalid response asserts
        $invalidResponse->assertStatus(422);

        $planRule->delete();
        $discountCode->delete();
    }
    public function testPersonalPaymentBoleto() {
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
                'payment_mode' => 'boleto',
                'plan_id' => $planRule->plan_rule_id,
                'sender_hash' => $this->senderHash,
            ]);

        $plan = Plan::where('user_id', $user->user_id)->get();

        $response->assertStatus(200);
        $this->assertInternalType('array', $response->json());
        $this->assertNotNull($plan[0]->payment_link);
        $this->assertEquals(1, $plan->count());
        $this->assertEquals(2, $plan[0]->payment_status);

        $plan[0]->delete();
        $planRule->delete();
    }

    public function testFreePersonalPayment() {
        //Authenticates the test user
        $user = $this->token();

        //Creates the plan rule
        $planRule = PlanRule::create([
            'description' => 'Anúncio único válido por 30 dias',
            'profile_type' => 0,
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'price' => 49.90,
            'validity' => 30,
            'renewable' => 0
        ]);

        //Creates the discount code
        $discountCode = DiscountCode::create([
            'discount_code' => 'wizzer100',
            'discount_percentage' => '100',
            'first_buy_only' => true,
            'number_of_uses' => 1
        ]);
        $planRule->discountCodes()->save($discountCode);

        //Make the payment
        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'creditCard',
                'discount_code' => $discountCode->discount_code,
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

        $plan = Plan::where('user_id', $user->user_id)->get();

        $response->assertStatus(200);
        $this->assertInternalType('array', $response->json());
        $this->assertEquals(1, $plan->count());
        $this->assertEquals(2, $plan[0]->payment_status);
        $this->assertEquals('FREE', $plan[0]->payment_id);

        $plan[0]->delete();
        $discountCode->delete();
        $planRule->delete();
    }

    //Has to create a plan in the pagseguro API
    public function testPlanPaymentWithoutDiscountCode() {
        $user = $this->token(1);
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
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

        $response->assertStatus(200);

        $plan = Plan::where('user_id', $user->user_id)->first();
        $this->assertEquals(2, $plan->paymentOrders()->count());
        $paymentOrder = $plan->paymentOrders()->orderBy('scheduling_date', 'asc')->first();
        $response->assertJson([
            'plan' => [
                'next_payment' => (new Carbon($paymentOrder->scheduling_date))->toDateString()
            ]
        ]);

        $plan->delete();
    }

    public function testPlanPaymentWithDiscountCode() {
        $user = $this->token(1);
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //Creates the discount code
        $discountCode = DiscountCode::create([
            'discount_code' => 'wizzer60',
            'discount_percentage' => '60',
            'first_buy_only' => false,
            'number_of_uses' => 10
        ]);
        $planRule->discountCodes()->save($discountCode);

        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'creditCard',
                'discount_code' => 'wizzer60',
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

        $response->assertStatus(200);

        $plan = Plan::where('user_id', $user->user_id)->first();
        $this->assertEquals(1, $plan->paymentOrders()->count());
        $paymentOrder = $plan->paymentOrders()->orderBy('scheduling_date', 'desc')->first();
        $response->assertJson([
            'plan' => [
                'next_payment' => (new Carbon($paymentOrder->scheduling_date))->toDateString()
            ]
        ]);

        $plan->delete();
        $discountCode->delete();
        $planRule->delete();
    }

    public function testCancelPlanProcessingPayment() {
        //Create a user
        $user = $this->token(1);

        //Create a plan rule
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //Sign a plan for the user
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

        //Cancel the plan
        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST', '/api/v1/payment/cancel');

        $plan = Plan::where('user_id', $user->user_id)->first();

        //Asserts
        $response->assertStatus(200);
        $this->assertEquals('-1', $plan->payment_status);
        $this->assertEquals(2, $plan->paymentOrders()->count());
        foreach($plan->paymentOrders as $paymentOrder) {
            $this->assertEquals('-1', $paymentOrder->status);
        }

        $plan->delete();
        $user->delete();
    }

    public function testCancelPlanApprovedPayment() {
        //Create a user
        $user = $this->token(1);

        //Create a plan rule
        $planRule = PlanRule::create([
            'description' => json_encode(['FF455B8A959598D664A7AFB7A8D9EDFA','698202E8BDBDB9AAA4FFEFAEF64E8CD4']),
            'profile_type' => 1,
            'adverts_number' => 10,
            'images_per_advert' => 25,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        //Sign a plan for the user
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

        Plan::where('user_id', $user->user_id)->first()->update([
            'payment_status' => '2'
        ]);

        //Cancel the plan
        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST', '/api/v1/payment/cancel');

        $plan = Plan::where('user_id', $user->user_id)->first();

        //Asserts
        $response->assertStatus(200);
        $this->assertEquals('0', $plan->payment_status);
        $this->assertEquals(2, $plan->paymentOrders()->count());
        foreach($plan->paymentOrders as $paymentOrder) {
            $this->assertEquals('-1', $paymentOrder->status);
        }

        $plan->delete();
        $user->delete();
    }

    public function testPlanPaymentBoletoWithoutDiscount() {
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

        //Tries to pay
        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'boleto',
                'plan_id' => $planRule->plan_rule_id,
                'sender_hash' => $this->senderHash,
            ]);

        //Asserts
        $response->assertStatus(200);
        $response->assertJson([
            'plan' => [
                'next_payment' => Carbon::now()->addMonth()->toDateString()
            ]
        ]);
        $this->assertEquals(2, $user->plans[0]->paymentOrders()->count());

        $user->plans()->delete();
        $planRule->delete();
        $user->delete();
    }

    public function testPlanPaymentBoletoWithDiscount() {
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

        //Create discount code
        $discountCode = DiscountCode::create([
            'discount_code' => 'wizzer60',
            'discount_percentage' => '60',
            'first_buy_only' => false,
            'number_of_uses' => 10
        ]);
        $planRule->discountCodes()->save($discountCode);

        //Tries to pay
        $response = $this->withHeader('Authorization', "Bearer $user->token")
            ->json('POST','/api/v1/payment/pay', [
                'payment_mode' => 'boleto',
                'discount_code' => 'wizzer60',
                'plan_id' => $planRule->plan_rule_id,
                'sender_hash' => $this->senderHash
            ]);

        //Asserts
        $response->assertStatus(200);
        $response->assertJson([
            'plan' => [
                'next_payment' => Carbon::now()->addMonths(2)->toDateString()
            ]
        ]);
        $this->assertEquals(1, $user->plans[0]->paymentOrders()->count());

        $user->plans()->delete();
        $discountCode->delete();
        $planRule->delete();
        $user->delete();
    }
}
