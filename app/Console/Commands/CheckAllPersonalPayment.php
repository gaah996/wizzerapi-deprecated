<?php

namespace App\Console\Commands;

use App\CronLog;
use App\Http\Controllers\PagSeguroController;
use App\Plan;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;

class CheckAllPersonalPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:check_all_p';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all the payments with a positive status to deactivate the ones needed (it means the ones that have a status ';

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
        $plans = Plan::where('payment_status', '>', '0')
            ->where('payment_id', '!=', 'FREE')
            ->where('pagseguro_plan_id', null)
            ->get();

        //Checks the pagseguro API, uses the pagseguro controller to get the authentication information
        $pagSeguroController = new PagSeguroController();
        $client = new Client();

        foreach ($plans as $plan) {
            try {
                $response = $client->get($pagSeguroController->getURL("/v3/transactions/$plan->payment_id"));
                $response = simplexml_load_string($response->getBody()->getContents());

                if($pagSeguroController->mapPaymentStatus((string)$response->status) != $plan->payment_status) {
                    //Updates the date if the status has changed to approved
                    if($plan->payment_status != '2' && $pagSeguroController->mapPaymentStatus((string)$response->status) == '2') {
                        $lastEventDate = new Carbon($response->lastEventDate);
                    }

                    //Update the plan's information
                    $plan->update([
                        'payment_status' => $pagSeguroController->mapPaymentStatus((string)$response->status),
                        'signature_date' => (isset($lastEventDate)) ? $lastEventDate : $plan->signature_date
                    ]);
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
