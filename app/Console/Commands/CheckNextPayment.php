<?php

namespace App\Console\Commands;

use App\Console\Commands\Controllers\PlanCronController;
use App\CronLog;
use App\Http\Controllers\PagSeguroController;
use App\Mail\ApprovedPayment;
use App\Mail\RefusedPayment;
use App\PaymentOrder;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckNextPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:next_e';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the next payment status when the schedule_date arrives';

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
        $paymentOrders = PaymentOrder::where('status', '1')->where('scheduling_date', '<=', Carbon::now())->get();

        foreach ($paymentOrders as $paymentOrder) {
            //Gets the new payment orders in the pagseguro API
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
                    if(PaymentOrder::where('code', $responsePaymentOrder->code)->count() == 0) {
                        //Creates the new PaymentOrder
                        PaymentOrder::create([
                            'code' => $responsePaymentOrder->code,
                            'status' => $responsePaymentOrder->status,
                            'amount' => $responsePaymentOrder->amount,
                            'last_event_date' => new Carbon($responsePaymentOrder->lastEventDate),
                            'scheduling_date' => new Carbon($responsePaymentOrder->schedulingDate),
                            'transactions' => json_encode($responsePaymentOrder->transactions),
                            'plan_id' => $paymentOrder->plan->plan_id
                        ]);
                    } else {
                        if($paymentOrder->code == $responsePaymentOrder->code) {
                            $controller = new PlanCronController();
                            $controller->updateCurrentPaymentOrder($paymentOrder, $responsePaymentOrder);
                        }
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
}
