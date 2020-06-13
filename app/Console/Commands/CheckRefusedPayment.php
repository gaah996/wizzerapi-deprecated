<?php

namespace App\Console\Commands;

use App\Console\Commands\Controllers\PlanCronController;
use App\CronLog;
use App\Http\Controllers\PagSeguroController;
use App\PaymentOrder;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;

class CheckRefusedPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:retry_e';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Makes a new payment trial to plans that have not been paid every 3 days during 15 days';

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
        //Get all refused payments
        $paymentOrders = PaymentOrder::where('status', '6')->get();

        foreach ($paymentOrders as $paymentOrder) {
            if ($paymentOrder->type == 'boleto') {
                $this->checkBoleto($paymentOrder);
            } else {
                $this->checkCreditCard($paymentOrder);
            }
        }
    }

    public function checkCreditCard($paymentOrder) {
        //Checks how long the payment order has been waiting to be approved
        $schedulingDate = new Carbon($paymentOrder->scheduling_date);

        if($schedulingDate->addDays(16) > Carbon::now()) {
            //Checks how long ago was the last retry
            $lastEventDate = new Carbon($paymentOrder->last_event_date);

            if($lastEventDate->addDays(3) <= Carbon::now()) {
                //Tries to charge again
                $pagSeguroController = new PagSeguroController();

                $client = new Client();
                try {
                    $response = $client->post($pagSeguroController->getURL('/pre-approvals/' . $paymentOrder->plan->pagseguro_plan_id . '/payment-orders/' . $paymentOrder->code . '/payment'), [
                        'headers' => [
                            'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1'
                        ]
                    ]);
                } catch(ClientException $exception) {
                    CronLog::create([
                        'cron_signature' => $this->signature,
                        'log' => $exception->getResponse()->getBody()->getContents(),
                        'user_id' => $paymentOrder->plan->user_id,
                        'plan_id' => $paymentOrder->plan_id
                    ]);
                }

                //Checks the paymentOrder status
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
        } else {
            //Cancels the plan
            $pagSeguroController = new PagSeguroController();
            $pagSeguroController->cancelPlan($paymentOrder->plan->pagseguro_plan_id, $paymentOrder->plan->user_id);
        }
    }

    public function checkBoleto($paymentOrder) {
        //Checks how long the payment order has been waiting to be approved
        $schedulingDate = new Carbon($paymentOrder->scheduling_date);

        if($schedulingDate->addDays(16) > Carbon::now()) {
            $checkPlanPayment = new CheckPlanPayment();
            $checkPlanPayment->checkBoleto($paymentOrder);
        } else {
            $paymentOrder->plan->paymentOrders()->update([
                'status' => '-1'
            ]);
            $paymentOrder->plan()->update([
                'payment_status' => '-1'
            ]);
            $paymentOrder->plan->adverts()->update([
                'status' => '0'
            ]);
        }
    }
}
