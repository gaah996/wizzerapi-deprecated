<?php

namespace App\Console\Commands;

use App\CronLog;
use App\Http\Controllers\PagSeguroController;
use App\Mail\NewBoleto;
use App\PaymentOrder;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateNewBoleto extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:boleto_e';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically generates a new boleto 7 days efore the next payment order scheduled date';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $paymentOrders = PaymentOrder::where('type', 'boleto')->where('status', '1')->get();

        foreach ($paymentOrders as $paymentOrder) {
            $schedulingDate = new Carbon($paymentOrder->scheduling_date);
            $user = $paymentOrder->plan->user;
            $planRule = $paymentOrder->plan->planRule;

            if($schedulingDate->subDays(7)->toDateString() == Carbon::now()->toDateString()) {
                $formParams = [
                    'firstDueDate' => $schedulingDate->addDays(7)->toDateString(),
                    'numberOfPayments' => '1',
                    'periodicity' => 'monthly',
                    'amount' => $planRule->price,
                    'instructions' => '',
                    'description' => 'Plano mensal de ' . $planRule->adverts_number . ' anúncios',
                    'customer' => [
                        'document' => [
                            'type' => (new PagSeguroController())->getDocument($user->cpf_cnpj)->type,
                            'value' => (new PagSeguroController())->getDocument($user->cpf_cnpj)->number
                        ],
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => [
                            'areaCode' => substr($user->phone, 1, 2),
                            'number' => preg_replace('/\D/i', '', substr($user->phone, 4))
                        ]
                    ]
                ];

                //Calls the API
                $client = new Client();
                try {
                    $response = $client->post('https://ws.pagseguro.uol.com.br/recurring-payment/boletos', [
                        RequestOptions::JSON => $formParams
                    ]);
                    $response = json_decode($response->getBody()->getContents());

                    //Saves the code in the payment order and updates the payment link in the plan
                    $paymentOrder->update([
                        'code' => preg_replace('/\W/i', '', $response->boletos[0]->code),
                        'status' => '2'
                    ]);

                    $paymentOrder->plan()->update([
                        'payment_link' => $response->boletos[0]->paymentLink
                    ]);

                    Mail::to($user->email)->send(new NewBoleto($user->name, $response->boletos[0]->paymentLink));

                } catch(ClientException $exception) {
                    CronLog::create([
                        'cron_signature' => $this->signature,
                        'log' => $exception->getResponse()->getBody()->getContents(),
                        'user_id' => $user->user_id,
                        'plan_id' => $paymentOrder->plan_id
                    ]);
                    CronLog::create([
                        'cron_signature' => $this->signature,
                        'log' => $formParams['firstDueDate'],
                        'user_id' => $user->user_id,
                        'plan_id' => $paymentOrder->plan_id
                    ]);
                }
            }
        }
    }
}
