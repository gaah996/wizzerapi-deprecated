<?php

namespace Tests\Unit;

use App\DiscountCode;
use App\Http\Controllers\PagSeguroController;
use App\PlanRule;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PagSeguroControllerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetURL()
    {
        $pagSeguroController = new PagSeguroController();

        $url = $pagSeguroController->getURL('/teste');

        if($pagSeguroController::MODE == 'development') {
            $this->assertEquals('https://ws.sandbox.pagseguro.uol.com.br/teste', $url);
        } else {
            $this->assertEquals('https://ws.pagseguro.uol.com.br/', $url);
        }
    }

    public function testDiscountCodeValidation() {
        $pagSeguroController = new PagSeguroController();

        //Create a user
        $user = User::create([
            'name' => 'Gabriel Castro',
            'email' => 'teste@teste.com',
            'password' => bcrypt('abcd1234'),
            'profile_type' => $profileType,
            'phone' => '(35) 98765-4321',
            'cpf_cnpj' => '123.456.789-00'
        ]);

        //Create a plan_rule
        $planRule = PlanRule::create([
            'description' => 'Anúncio único válido por 30 dias',
            'profile_type' => 0,
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'price' => 49.90,
            'validity' => 30,
            'renewable' => 0
        ]);

        //Create a discount code
        $discountCode = DiscountCode::create([
            'discount_code' => 'wizzer60',
            'discount_percentage' => '60',
            'first_buy_only' => false,
            'number_of_uses' => 10
        ]);

        $discount = $pagSeguroController->validateDiscountCode($discountCode->discount_code, $planRule->plan_rule_id, $user->user_id);

        //Validates the discount code is invalid
        $this->assertEquals(false, $discount);

        //Assign the discount code to the plan
        $planRule->discountCodes()->save($discountCode);

        $discount = $pagSeguroController->validateDiscountCode($discountCode->discount_code, $planRule->plan_rule_id, $user->user_id);

        //Validates the discount code is valid
        $this->assertEquals(60, $discount);

        $planRule->delete();
        $discountCode->delete();
        $user->delete();
    }

    public function testMapPaymentStatus() {
        $pagSeguroController = new PagSeguroController();

        $this->assertEquals(1, $pagSeguroController->mapPaymentStatus('1'));
        $this->assertEquals(1, $pagSeguroController->mapPaymentStatus('2'));
        $this->assertEquals(2, $pagSeguroController->mapPaymentStatus('3'));
        $this->assertEquals(2, $pagSeguroController->mapPaymentStatus('4'));
        $this->assertEquals(2, $pagSeguroController->mapPaymentStatus('5'));
        $this->assertEquals(0, $pagSeguroController->mapPaymentStatus('6'));
        $this->assertEquals(0, $pagSeguroController->mapPaymentStatus('7'));
        $this->assertEquals(0, $pagSeguroController->mapPaymentStatus('10'));
    }

    public function testGetDocumentCPF() {
        $pagSeguroController = new PagSeguroController();

        $document = "123.456.789-00";

        $this->assertEquals('CPF', $pagSeguroController->getDocument($document)->type);
        $this->assertEquals('12345678900', $pagSeguroController->getDocument($document)->number);
    }

    public function testGetDocumentCNPJ() {
        $pagSeguroController = new PagSeguroController();

        $document = "32.419.931/0001-15";

        $this->assertEquals('CNPJ', $pagSeguroController->getDocument($document)->type);
        $this->assertEquals('32419931000115', $pagSeguroController->getDocument($document)->number);
    }
}
