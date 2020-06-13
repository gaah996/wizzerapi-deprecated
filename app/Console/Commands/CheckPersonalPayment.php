<?php

namespace App\Console\Commands;

use App\CronLog;
use App\Http\Controllers\PagSeguroController;
use App\Plan;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;

class CheckPersonalPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:check_p';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all the personal payments that have not yet been approved';

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
        //Get the personal plans waiting for payment confirmation
        $plans = Plan::where('payment_status', '1')
            ->where('pagseguro_plan_id', null)
            ->get();

        //Checks the pagseguro API, uses the pagseguro controller to get the authentication information
        $pagSeguroController = new PagSeguroController();
        $client = new Client();

        foreach ($plans as $plan) {
            try {
                $response = $client->get($pagSeguroController->getURL("/v3/transactions/$plan->payment_id"));
                $response = simplexml_load_string($response->getBody()->getContents());

                //Check if the payment was approved to update the signature date
                if($pagSeguroController->mapPaymentStatus((string)$response->status) == '2') {
                    $lastEventDate = new Carbon($response->lastEventDate);
                }

                //Update the plan's information
                $plan->update([
                    'payment_status' => $pagSeguroController->mapPaymentStatus((string)$response->status),
                    'signature_date' => (isset($lastEventDate)) ? $lastEventDate : $plan->signature_date
                ]);

                if($pagSeguroController->mapPaymentStatus((string)$response->status) == 2) {
                    //Activates all adverts related to the plan
                    foreach ($plan->adverts as $advert) {
                        $advert->update(['status' => '1']);
                    }
                }
            } catch (ClientException $exception) {
                //Creates a log in the database for debugging the error
                CronLog::create([
                    'cron_signature' => $this->signature,
                    'log' => $exception->getResponse()->getBody()->getContents(),
                    'user_id' => $plan->user_id,
                    'plan_id' => $plan->plan_id
                ]);
            }
        }
    }
}
