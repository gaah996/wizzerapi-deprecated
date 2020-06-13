<?php

namespace App\Console\Commands;

use App\Console\Commands\Controllers\PlanCronController;
use App\CronLog;
use App\Http\Controllers\PagSeguroController;
use App\Mail\ApprovedPayment;
use App\Mail\RefusedPayment;
use App\PaymentOrder;
use App\Plan;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckPlanPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:check_e';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all the plan payments that have not yet been approved';

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
        $paymentOrders = PaymentOrder::where('status', '2')->get();

        foreach ($paymentOrders as $paymentOrder) {
            if($paymentOrder->type == 'boleto') {
                $this->checkBoleto($paymentOrder);
            } else {
                $this->checkCreditCard($paymentOrder);
            }
        }
    }

    public function checkBoleto($paymentOrder) {
        //Check the status in the pagseguro API
        $pagSeguroController = new PagSeguroController();

        $client = new Client();
        try {
            $response = $client->get($pagSeguroController->getURL("/v3/transactions/$paymentOrder->code"));
            $response = simplexml_load_string($response->getBody()->getContents());

            $lastEventDate = new Carbon($response->lastEventDate);

            //Updates the payment order and the plan
            if($pagSeguroController->mapPaymentStatus((string)$response->status) == '2') {
                $paymentOrder->update([
                    'status' => 5,
                    'last_event_date' => $lastEventDate
                ]);

                $paymentOrder->plan()->update([
                    'payment_status' => 2,
                    'payment_link' => null
                ]);

                PaymentOrder::create([
                    'type' => 'boleto',
                    'code' => null,
                    'status' => 1,
                    'amount' => $paymentOrder->plan->planRule->price,
                    'last_event_date' => $lastEventDate,
                    'scheduling_date' => (new Carbon($paymentOrder->scheduling_date))->addMonth(),
                    'transactions' => null,
                    'plan_id' => $paymentOrder->plan_id
                ]);
            } else {
                if((new Carbon($paymentOrder->scheduling_date)) < Carbon::now()) {
                    $paymentOrder->update([
                        'status' => 6,
                        'last_event_date' => $lastEventDate
                    ]);
                } else {
                    $paymentOrder->update([
                        'last_event_date' => $lastEventDate
                    ]);
                }
            }

        } catch(ClientException $exception) {
            CronLog::create([
                'cron_signature' => $this->signature,
                'log' => $exception->getResponse()->getBody()->getContents(),
                'user_id' => $paymentOrder->plan->user_id,
                'plan_id' => $paymentOrder->plan_id
            ]);
        }
    }

    public function checkCreditCard($paymentOrder) {
        //Check the status in the pagseguro API
        $pagSeguroController = new PagSeguroController();

        $client = new Client();
        try {
            $response = $client->get($pagSeguroController->getURL('/pre-approvals/' . $paymentOrder->plan->pagseguro_plan_id . '/payment-orders'), [
                'headers' => [
                    'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1'
                ]
            ]);
            $response = json_decode($response->getBody()->getContents());

            foreach ($response->paymentOrders as $responsePaymentOrder) {
                if($responsePaymentOrder->code == $paymentOrder->code) {
                    $controller = new PlanCronController();
                    $controller->updateCurrentPaymentOrder($paymentOrder, $responsePaymentOrder);
                }
            }
        } catch(ClientException $exception) {
            CronLog::create([
                'cron_signature' => $this->signature,
                'log' => $exception->getResponse()->getBody()->getContents(),
                'user_id' => $paymentOrder->plan->user_id,
                'plan_id' => $paymentOrder->plan_id
            ]);
        }
    }
}
