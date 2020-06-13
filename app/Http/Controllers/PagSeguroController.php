<?php

namespace App\Http\Controllers;

use App\CronLog;
use App\DiscountCode;
use App\Mail\NewBoleto;
use App\PaymentOrder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\PlanRule;
use App\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PagSeguroController extends Controller
{
    const MODE = 'production'; //development or production

    public function getURL($endpoint) {
        //This function automatically generates the request url
        //with the correct baseURL and credentials based on the
        //state of the API: development or production
        if (self::MODE == 'development') {
            $email = '';
            $token = '';
            return "https://ws.sandbox.pagseguro.uol.com.br$endpoint?email=$email&token=$token";
        } else {
            $email = '';
            $token = '';
            return "https://ws.pagseguro.uol.com.br$endpoint?email=$email&token=$token";
        }
    }

    public function mapPaymentStatus($status) {
        //Transform pagseguro status in WizzerAPI status where:
        //-1 = arquivado
        // 0 = cancelado
        // 1 = aguardando pagamento
        // 2 = pago

        switch ($status) {
            case '1': return 1;
            case '2': return 1;
            case '3': return 2;
            case '4': return 2;
            case '5': return 2;
            case '6': return 0;
            case '7': return 0;
            default: return 0;
        }
    }

    public function mapPaymentOrders($status) {
        //Transform pagseguro status in WizzerAPI status where:
        //-1 = arquivado
        // 0 = cancelado
        // 1 = aguardando pagamento
        // 2 = pago

        switch ($status) {
            case '1': return 2;
            case '2': return 1;
            case '3': return 1;
            case '4': return 0;
            case '5': return 2;
            case '6': return 0;
            default: return 0;
        }
    }

    public function getDocument($documentNumber) {
        $documentNumber = preg_replace('/\D/i', '', $documentNumber);

        $document = new \stdClass();
        $document->number = $documentNumber;
        $document->type = (strlen($documentNumber) == 11) ? 'CPF' : 'CNPJ';

        return $document;
    }

    public function getSessionId() {
        $client = new Client();

        //Tries to get the session ID for the frontend to generate the creditcard token
        try {
            $response = $client->post($this->getURL('/v2/sessions'), [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);

            //Transforms the $answer in a relational array
            $response = simplexml_load_string($response->getBody()->getContents());

            //The string prefix is needed to avoid the answer from being an array
            return response()->json(['sessionId' => (string)$response->id], 200);
        } catch (ClientException $exception) {
            return response()->JSON(['error' => 'Couldn\'t get a Session ID'], 502);
        }
    }

    public function makePayment(Request $request) {
        //This function acts like a gateway, for deciding which type of
        //payment the user will be redirected based on it's profile type

        //Request data validation
        $this->validate($request, [
            'payment_mode' => 'required|in:creditCard,boleto',
            'plan_id' => 'exists:plan_rules,plan_rule_id|required',
            'discount_code' => '',
            'sender_hash' => 'required',
            'credit_card_token' => 'required_if:payment_mode,==,creditCard',
            'holder_name' => 'min:1|max:50|required_if:payment_mode,==,creditCard',
            'holder_document' => 'min:11|max:14|required_if:payment_mode,==,creditCard',
            'holder_birth_date' => 'size:10|required_if:payment_mode,==,creditCard',
            'billing_street' => 'max:80|required_if:payment_mode,==,creditCard',
            'billing_number' => 'max:20|required_if:payment_mode,==,creditCard',
            'billing_district' => 'max:60|required_if:payment_mode,==,creditCard',
            'billing_complement' => 'max:40',
            'billing_city' => 'min:2|max:60|required_if:payment_mode,==,creditCard',
            'billing_state' => 'size:2|required_if:payment_mode,==,creditCard',
            'billing_zip'  => 'size:8|required_if:payment_mode,==,creditCard'
        ]);

        $user = Auth::user();
        $planRule = PlanRule::find($request['plan_id']);

        //Check if the user can sign the given plan
        if($user->profile_type == $planRule->profile_type) {
            if($planRule->renewable == 0) {
                return $this->personalPayment($request);
            } else {
                if($request['payment_mode'] == 'creditCard') {
                    return $this->planPayment($request);
                } else {
                    return $this->planPaymentBoleto($request);
                }
            }
        } else {
            return response()->json(['error' => 'User can\'t buy this plan'], 403);
        }
    }

    public function personalPayment(Request $request) {
        //Gets the user and the plan rule
        $user = Auth::user();
        $planRule = PlanRule::find($request['plan_id']);

        if(isset($request['discount_code'])) {
            //Check for the discount percentage
            $discount = $this->validateDiscountCode($request['discount_code'], $planRule->plan_rule_id, $user->user_id);
            if($discount != false) {
                //Update the price applying the discount
                $planRule->price = ((100 - $discount) / 100) * $planRule->price;
            }
        }

        //Set the payment params based on the user option
        //Static params
        $formParams = [
            'paymentMode' => 'default',
            'paymentMethod' => $request['payment_mode'],
            'currency' => 'BRL',
            'senderHash' => $request['sender_hash'],
            'senderName' => $user->name,
            'senderEmail' => (self::MODE == 'development') ? 'email@sandbox.pagseguro.com.br' : $user->email,
            'senderAreaCode' => substr($user->phone, 1, 2),
            'senderPhone' => preg_replace('/\D/i', '', substr($user->phone, 4)),
            'itemId1' => $planRule->plan_rule_id,
            'itemDescription1' => $planRule->description,
            'itemAmount1' => number_format($planRule->price, 2, '.', ''),
            'itemQuantity1' => 1,
            'shippingAddressRequired' => 'false'
        ];

        //Document param
        $user->cpf_cnpj = preg_replace('/\D/i', '', $user->cpf_cnpj);
        if(strlen($user->cpf_cnpj) == 11) {
            $formParams = array_merge($formParams, ['senderCPF' => $user->cpf_cnpj]);
        } else {
            $formParams = array_merge($formParams, ['senderCNPJ' => $user->cpf_cnpj]);
        }

        //Creditcard extra params
        if($request['payment_mode'] == 'creditCard') {
            $formParams = array_merge($formParams, [
                'creditCardToken' => $request['credit_card_token'],
                'installmentQuantity' => 1,
                'installmentValue' => $formParams['itemAmount1'],
                'creditCardHolderName' => $request['holder_name'],
                'creditCardHolderBirthDate' => $request['holder_birth_date'],
                'creditCardHolderAreaCode' => $formParams['senderAreaCode'],
                'creditCardHolderPhone' =>  $formParams['senderPhone'],
                'billingAddressStreet' => $request['billing_street'],
                'billingAddressNumber' => $request['billing_number'],
                'billingAddressDistrict' => $request['billing_district'],
                'billingAddressCity' => $request['billing_city'],
                'billingAddressState' => $request['billing_state'],
                'billingAddressCountry' => 'BRA',
                'billingAddressPostalCode' => $request['billing_zip'],
                'billingAddressComplement' => (isset($request['billing_complement'])) ? $request['billing_complement'] : ''
            ]);

            //Document param
            if(strlen($request['holder_document']) == 11) {
                $formParams = array_merge($formParams, ['creditCardHolderCPF' => $request['holder_document']]);
            } else {
                $formParams = array_merge($formParams, ['creditCardHolderCNPJ' => $request['holder_document']]);
            }
        }

        //Call pag seguro to handle payment
        $client = new Client();
        try {
            if($planRule->price != 0) {
                $response = $client->post($this->getURL('/v2/transactions'), [
                    'form_params' => $formParams,
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded; charset=ISO-8859-1'
                    ]
                ]);

                $response = simplexml_load_string($response->getBody()->getContents());

                $lastEventDate = new Carbon($response->lastEventDate);
            } else {
                $response = new \stdClass();
                $lastEventDate = Carbon::now();
                $response->code = 'FREE';
                $response->status = 3;
                $response->paymentLink = null;

            }

            //Saves the payment in the database
            $plan = Plan::create([
                'user_id' => $user->user_id,
                'plan_rule_id' => $planRule->plan_rule_id,
                'discount_code' => (isset($request['discount_code']) && $discount != false) ? $request['discount_code'] : null,
                'signature_date' => $lastEventDate,
                'payment_id' => preg_replace('/\W/i', '', $response->code),
                'payment_link' => ($request['payment_mode'] == 'boleto') ? (string)$response->paymentLink : null,
                'payment_status' => $this->mapPaymentStatus((string)$response->status)
            ]);

            //Fast checkout
            if(($plan->payment_status == '1') && $request['payment_mode'] == 'creditCard') {
                $plan = $this->fastApprovalCheck($plan->payment_id);
            }



            return response()->JSON(['plan' => $plan], 200);
        } catch (ClientException $exception) {
            DB::table('payment_logs')->insert([
                'log' => $exception->getResponse()->getBody()->getContents(),
                'user_id' =>$user->user_id
            ]);

            return response()->JSON(['error' => 'Error while processing payment'], 502);
        }
    }

    public function planPayment(Request $request) {
        //Gets the user and the plan rule
        $user = Auth::user();
        $planRule = PlanRule::find($request['plan_id']);

        if(Plan::where('user_id', $user->user_id)->whereIn('payment_status', ['1', '2'])->count() == 0) {
            if(isset($request['discount_code'])) {
                //Check for the discount percentage
                $discount = $this->validateDiscountCode($request['discount_code'], $planRule->plan_rule_id, $user->user_id);
                if($discount != false) {
                    //Converts the discount_percentage to integer
                    //To be used as index
                    $discount = intval($discount);

                    //Update the price applying the discount
                    $planRule->description = json_decode($planRule->description)[$discount];
                } else {
                    $planRule->description = json_decode($planRule->description)[0];
                }
            } else {
                $planRule->description = json_decode($planRule->description)[0];
            }

            //Set the payment params based on the user option
            $formParams = [
                'plan' => $planRule->description,
                'sender' => [
                    'name' => $user->name,
                    'email' => (self::MODE == 'development') ? 'email@sandbox.pagseguro.com.br' : $user->email,
                    'hash' => $request['sender_hash'],
                    'phone' => [
                        'areaCode' => substr($user->phone, 1, 2),
                        'number' => preg_replace('/\D/i', '', substr($user->phone, 4)),
                    ],
                    'documents' => [[
                        'type' => $this->getDocument($user->cpf_cnpj)->type,
                        'value' => $this->getDocument($user->cpf_cnpj)->number,
                    ]],
                    'address' => [
                        'street' => $request['billing_street'],
                        'number' => $request['billing_number'],
                        'complement' => (isset($request['billing_complement'])) ? $request['billing_complement'] : '',
                        'district' => $request['billing_district'],
                        'city' => $request['billing_city'],
                        'state' => $request['billing_state'],
                        'country' => 'BRA',
                        'postalCode' => $request['billing_zip']
                    ]
                ],
                'paymentMethod' => [
                    'type' => 'CREDITCARD',
                    'creditCard' => [
                        'token' => $request['credit_card_token'],
                        'holder' => [
                            'name' => $request['holder_name'],
                            'birthDate' => $request['holder_birth_date'],
                            'documents' => [[
                                'type' => $this->getDocument($request['holder_document'])->type,
                                'value' => $this->getDocument($request['holder_document'])->number,
                            ]],
                            'phone' => [
                                'areaCode' => substr($user->phone, 1, 2),
                                'number' => preg_replace('/\D/i', '', substr($user->phone, 4)),
                            ],
                            'billingAddress' => [
                                'street' => $request['billing_street'],
                                'number' => $request['billing_number'],
                                'complement' => (isset($request['billing_complement'])) ? $request['billing_complement'] : '',
                                'district' => $request['billing_district'],
                                'city' => $request['billing_city'],
                                'state' => $request['billing_state'],
                                'country' => 'BRA',
                                'postalCode' => $request['billing_zip']
                            ]
                        ]
                    ]
                ]
            ];

            $client = new Client();

            try {
                //Makes the request
                $response = $client->post($this->getURL('/pre-approvals'), [
                    RequestOptions::JSON => $formParams,
                    'headers' => [
                        'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1'
                    ]
                ]);

                //Creates a plan in the database
                $response = json_decode($response->getBody()->getContents());

                if(isset($response->code)) {
                    //Get the plan information
                    try {
                        $planInfo = $client->get($this->getURL('/pre-approvals/' . $response->code), [
                            'headers' => [
                                'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1'
                            ]
                        ]);
                        $paymentOrders = $client->get($this->getURL('/pre-approvals/' . $response->code . '/payment-orders'), [
                            'headers' => [
                                'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1'
                            ]
                        ]);

                        $planInfo = json_decode($planInfo->getBody()->getContents());
                        $paymentOrders = json_decode($paymentOrders->getBody()->getContents());

                        //Creates the plan
                        $plan = Plan::create([
                            'signature_date' => new Carbon( ),
                            'discount_code' => (isset($request['discount_code'])) ? $request['discount_code'] : null,
                            'payment_id' => null,
                            'payment_status' => 1,
                            'pagseguro_plan_id' => $planInfo->code,
                            'user_id' => $user->user_id,
                            'plan_rule_id' => $planRule->plan_rule_id
                        ]);

                        //Save the payment orders in the database
                        foreach ($paymentOrders->paymentOrders as $paymentOrder) {
                            PaymentOrder::create([
                                'code' => $paymentOrder->code,
                                'status' => $paymentOrder->status,
                                'amount' => $paymentOrder->amount,
                                'last_event_date' => new Carbon($paymentOrder->lastEventDate),
                                'scheduling_date' => new Carbon($paymentOrder->schedulingDate),
                                'transactions' => json_encode($paymentOrder->transactions),
                                'plan_id' => $plan->plan_id
                            ]);
                        }

                        //Get the last paid payment order and updates the plan status
                        //If there are two payment orders user did not use a discount code
                        //If there is only one the user has used the discount code
                        $paymentOrders = PaymentOrder::where('plan_id', $plan->plan_id)->orderBy('scheduling_date', 'desc')->get();
                        $nextPaymentDate = null;
                        if($paymentOrders->count() == 1) {
                            $plan->update(['payment_status' => $this->mapPaymentOrders($paymentOrders[0]->status)]);
                            $plan = Plan::find($plan->plan_id);
                            $nextPaymentDate = new Carbon($paymentOrders[0]->scheduling_date);
                        } else {
                            $plan->update([
                                'payment_status' => $this->mapPaymentOrders($paymentOrders[1]->status),
                                'payment_id' => json_decode($paymentOrders[1]->transactions)[0]->code
                                ]);

                            $plan = Plan::find($plan->plan_id);

                            //Sets the next payment date
                            if($plan->payment_status == 2) {
                                $nextPaymentDate = new Carbon($paymentOrders[0]->scheduling_date);
                            } else {
                                $nextPaymentDate = new Carbon($paymentOrders[1]->scheduling_date);
                            }
                        }

                        //Adds the next payment_date to the answer
                        $plan->validity = $nextPaymentDate->toDateString();

                        return response()->json(['plan' => $plan], 200);
                    } catch (ClientException $exception) {
                        try {
                            //Cancel semi-processed plan
                            $this->cancelPlan($response->code, $user->user_id);

                            return response()->JSON(['error' => 'Error while processing payment'], 502);
                        } catch (ClientException $exception) {
                            DB::table('payment_logs')->insert([
                                'log' => $exception->getResponse()->getBody()->getContents(),
                                'user_id' =>$user->user_id
                            ]);

                            return response()->JSON(['error' => 'Error while canceling semi-processed payment'], 502);
                        }
                    }
                }
            } catch (ClientException $exception) {
                DB::table('payment_logs')->insert([
                    'log' => $exception->getResponse()->getBody()->getContents(),
                    'user_id' =>$user->user_id
                ]);

                return response()->JSON(['error' => 'Error while processing payment'], 502);
            }
        } else {
            return response()->json(['error' => 'User already has an active plan'], 403);
        }
    }

    public function planPaymentBoleto(Request $request) {
        //Gets the user and the plan rule
        $user = Auth::user();
        $planRule = PlanRule::find($request['plan_id']);

        if($user->plans()->whereIn('payment_status', ['1','2'])->count() == 0) {
            //Validates the discount code
            $hasDiscount = false;
            if(isset($request['discount_code'])) {
                //Check for the discount percentage
                $discount = $this->validateDiscountCode($request['discount_code'], $planRule->plan_rule_id, $user->user_id);
                if($discount != false) {
                    //Update the price applying the discount
                    $discount = intval($discount);
                    $hasDiscount = true;
                }
            }

            //Prepares the formdata for the request
            $formParams = [
                'paymentMode' => 'default',
                'paymentMethod' => $request['payment_mode'],
                'currency' => 'BRL',
                'senderHash' => $request['sender_hash'],
                'senderName' => $user->name,
                'senderEmail' => (self::MODE == 'development') ? 'email@sandbox.pagseguro.com.br' : $user->email,
                'senderAreaCode' => substr($user->phone, 1, 2),
                'senderPhone' => preg_replace('/\D/i', '', substr($user->phone, 4)),
                'itemId1' => $planRule->plan_rule_id,
                'itemDescription1' => 'Plano recorrente de ' . $planRule->adverts_number . ' anúncios com pagamento por boleto',
                'itemAmount1' => number_format($planRule->price, 2, '.', ''),
                'itemQuantity1' => 1,
                'shippingAddressRequired' => 'false'
            ];

            //Document param
            $user->cpf_cnpj = preg_replace('/\D/i', '', $user->cpf_cnpj);
            if(strlen($user->cpf_cnpj) == 11) {
                $formParams = array_merge($formParams, ['senderCPF' => $user->cpf_cnpj]);
            } else {
                $formParams = array_merge($formParams, ['senderCNPJ' => $user->cpf_cnpj]);
            }

            //Makes the $request for the pagseguro API
            $client = new Client();
            try {
                if(!$hasDiscount) {
                    $response = $client->post($this->getURL('/v2/transactions'), [
                        'form_params' => $formParams,
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded; charset=ISO-8859-1'
                        ]
                    ]);

                    $response = simplexml_load_string($response->getBody()->getContents());

                    $lastEventDate = new Carbon($response->lastEventDate);
                } else {
                    $response = new \stdClass();
                    $lastEventDate = Carbon::now();
                    $response->code = 'FREE';
                    $response->status = 3;
                }

                //Saves the payment in the database
                $plan = Plan::create([
                    'user_id' => $user->user_id,
                    'plan_rule_id' => $planRule->plan_rule_id,
                    'discount_code' => ($hasDiscount != false) ? $request['discount_code'] : null,
                    'signature_date' => $lastEventDate,
                    'payment_id' => null,
                    'payment_link' => ($request['payment_mode'] == 'boleto' && !$hasDiscount) ? (string)$response->paymentLink : null,
                    'payment_status' => $this->mapPaymentStatus((string)$response->status),
                    'pagseguro_plan_id' => 'BOLETO'
                ]);

                //Creates the payment orders
                if($hasDiscount) {
                    //Only one payment order scheduled for 60+ days
                    $paymentOrder = PaymentOrder::create([
                        'type' => 'boleto',
                        'code' => null,
                        'status' => 1,
                        'amount' => $planRule->price,
                        'last_event_date' => $lastEventDate,
                        'scheduling_date' => $lastEventDate->addMonths($discount),
                        'transactions' => null,
                        'plan_id' => $plan->plan_id
                    ]);

                } else {
                    //One payment order with the current boleto
                    PaymentOrder::create([
                        'type' => 'boleto',
                        'code' => preg_replace('/\W/i', '', $response->code),
                        'status' => 2,
                        'amount' => $planRule->price,
                        'last_event_date' => $lastEventDate,
                        'scheduling_date' => $lastEventDate,
                        'transactions' => null,
                        'plan_id' => $plan->plan_id
                    ]);

                    //One payment order scheduled for 30+ days
                    $paymentOrder = PaymentOrder::create([
                        'type' => 'boleto',
                        'code' => null,
                        'status' => 1,
                        'amount' => $planRule->price,
                        'last_event_date' => $lastEventDate,
                        'scheduling_date' => $lastEventDate->addMonth(),
                        'transactions' => null,
                        'plan_id' => $plan->plan_id
                    ]);
                }

                //Gets the next payment date
                $plan->validity = (new Carbon($paymentOrder->scheduling_date))->toDateString();

                return response()->json(['plan' => $plan], 200);
            } catch(ClientException $exception) {
                DB::table('payment_logs')->insert([
                    'log' => $exception->getResponse()->getBody()->getContents(),
                    'user_id' =>$user->user_id
                ]);

                return response()->JSON(['error' => 'Error while processing payment'], 502);
            }
        } else {
            return response()->json(['error' => 'User already has an active plan'], 403);
        }
    }

    public function fastApprovalCheck($paymentId) {
        //This function waits for three seconds before checking the payment
        //status in pagseguro again. As most of the payments will be approved
        //in less than that, it will give to the user an approved payment status
        //instead of the wait for payment one
        sleep(3);

        $client = new Client();

        try {
            $response = $client->get($this->getURL("/v3/transactions/$paymentId"));
            $response = simplexml_load_string($response->getBody()->getContents());

            $plan = Plan::where('payment_id', $paymentId)->first();

            if($this->mapPaymentStatus((string)$response->status) == '2') {
                $plan->update([
                    'payment_status' => $this->mapPaymentStatus((string)$response->status),
                    'signature_date' => new Carbon((string)$response->lastEventDate)
                ]);
            }

            return $plan;
        } catch(ClientException $exception) {
            return Plan::where('payment_id', $paymentId)->first();
        }
    }

    public function checkDiscountCode(Request $request) {
        $user = Auth::user();

        $this->validate($request, [
            'plan_id' => 'required|exists:plan_rules,plan_rule_id',
            'discount_code' => 'required|exists:discount_codes,discount_code'
        ]);

        $discount = $this->validateDiscountCode($request['discount_code'], $request['plan_id'], $user->user_id);

        if($discount != false) {
            return response()->json([
                'success' => 'Discount code is valid',
                'discount_percentage' => $discount
                ], 200);
        } else {
            return response()->json(['error' => 'Discount code is invalid'], 404);
        }
    }

    public function validateDiscountCode($discountCode, $planRuleId, $userId) {
        $user = User::find($userId);
        $planRule = PlanRule::find($planRuleId);

        foreach ($planRule->discountCodes as $validCode) {
            if($discountCode == $validCode->discount_code) {
                //Checks if the user can use the discount code
                if($validCode->first_buy_only) {
                    if($user->plans()->count() == 0) {
                        return $validCode->discount_percentage;
                    } else {
                        break;
                    }
                } else {
                    if($user->plans()->where('discount_code', $discountCode)->count() < $validCode->number_of_uses) {
                        return $validCode->discount_percentage;
                    } else {
                        break;
                    }
                }
            }
        }

        return false;
    }

    public function cancelPlan($pagSeguroPlanId = null, $userId = null) {
        //Gets the pagseguro plan id for the current user
        if($pagSeguroPlanId == null) {
            $plan = Auth::user()->plans()->whereIn('payment_status', ['1', '2'])->first();
            $userId = $plan->user_id;
            $pagSeguroPlanId = $plan->pagseguro_plan_id;
        }

        if(!isset($plan)) {
            $plan = Plan::where('pagseguro_plan_id', $pagSeguroPlanId)->first();
        }

        if($plan->pagseguro_plan_id != 'BOLETO') {
            $client = new Client();
            try {
                $client->put($this->getURL('/pre-approvals/' . $pagSeguroPlanId . '/cancel'), [
                    'headers' => [
                        'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1'
                    ]
                ]);
            } catch(ClientException $exception) {
                DB::table('payment_logs')->insert([
                    'log' => $exception->getResponse()->getBody()->getContents(),
                    'user_id' =>$userId
                ]);

//                return response()->json(['error' => 'Plan couldn\'t be cancelled'], 502);
            }
        }

        //Updates the database
        $paymentOrders = $plan->paymentOrders()->orderBy('scheduling_date', 'desc')->get();

        foreach ($paymentOrders as $paymentOrder) {
            $paymentOrder->update(['status' => '-1']);
        }

        if($paymentOrders->count() == 1) {
            //Files the plan
            $plan->update(['payment_status' => '-1']);

            //Deactivates all the adverts
            foreach ($plan->adverts as $advert) {
                $advert->update(['status' => 0]);
            }
        } else {
            if ($plan->payment_status == '2') {
                //Cancels
                $plan->update(['payment_status' => '0']);
            } else {
                //Files the plan
                $plan->update(['payment_status' => '-1']);

                //Deactivates all the adverts
                foreach ($plan->adverts as $advert) {
                    $advert->update(['status' => 0]);
                }
            }
        }

        return response()->json(['success' => 'Plan cancelled'], 200);
    }

    public function generateNewBoleto() {
        $user = Auth::user();
        $plan = $user->plans()->where('payment_status', '2')->first();
        $paymentOrder = $plan->paymentOrders()->where('status', '2')->first();
        $schedulingDate = new Carbon($paymentOrder->scheduling_date);

        if($paymentOrder->status == 5) {
            //Generate new boleto
            $formParams = [
                'firstDueDate' => $schedulingDate->addDays(10)->toDateString(),
                'numberOfPayments' => '1',
                'periodicity' => 'monthly',
                'amount' => $plan->planRule->price,
                'instructions' => '',
                'description' => 'Plano mensal de ' . $plan->planRule->adverts_number . ' anúncios',
                'customer' => [
                    'document' => [
                        'type' => $this->getDocument($user->cpf_cnpj)->type,
                        'value' => $this->getDocument($user->cpf_cnpj)->number
                    ],
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => [
                        'areaCode' => substr($user->phone, 1, 2),
                        'number' => preg_replace('/\D/i', '', substr($user->phone, 4))
                    ]
                ]
            ];

            $client = new Client();
            try {
                $response = $client->post('https://ws.pagseguro.uol.com.br/recurring-payment/boletos', [
                    RequestOptions::JSON => $formParams
                ]);
                $response = json_decode($response->getBody()->getContents());

                //Saves the code in the payment order and updates the payment link in the plan
                $paymentOrder->update([
                    'code' => preg_replace('/\W/i', '', $response->boletos[0]->code),
                    'status' => '2',
                ]);

                $paymentOrder->plan()->update([
                    'payment_link' => $response->boletos[0]->paymentLink
                ]);

                return response()->json(['payment_link' => $response->boletos[0]->paymentLink], 200);
            } catch(ClientException $exception) {
                DB::table('payment_logs')->insert([
                    'log' => $exception->getResponse()->getBody()->getContents(),
                    'user_id' =>$user->user_id
                ]);

                return response()->json(['error' => 'A problem occurred while generating the new boleto'], 502);
            }
        } else {
            //Current boleto is still valid
            return response()->json(['error' => 'Current boleto is still valid'], 403);
        }
    }
}
