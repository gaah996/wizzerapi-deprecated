<?php

namespace App\Console\Commands;

use App\Console\Commands\Controllers\PlanCronController;
use App\Plan;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckCancelledPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:cancelled_e';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all the cancelled plans to deactivate it in the expiration date';

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
        $plans = Plan::where('pagseguro_plan_id', '!=', null)->where('payment_status', '0')->get();

        foreach ($plans as $plan) {
            //Get the last payment order
            $paymentOrder = $plan->paymentOrders()->orderBy('scheduling_date', 'desc')->first();
            $expirationDate = new Carbon($paymentOrder->scheduling_date);

            if ($expirationDate < Carbon::now()) {
                //Deactivates all the adverts and files the plan
                foreach($plan->adverts as $advert) {
                    $controller = new PlanCronController();
                    if (!$controller->realocateAdvert($advert)) {
                        $advert->update(['status' => 0]);
                    }
                }
                $plan->update(['payment_status' => -1]);
            }
        }
    }
}
