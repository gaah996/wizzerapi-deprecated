<?php

namespace App\Console\Commands;

use App\Advert;
use App\Console\Commands\Controllers\PlanCronController;
use App\CronLog;
use App\Mail\PersonalPlanExpired;
use App\Plan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DeactivatePersonalPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:deactivate_p';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivates all the personal payments that have expired';

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
        //Gets all the active plans to check the validity
        $plans = Plan::where('payment_status', '2')
            ->where('pagseguro_plan_id', null)
            ->get();

        foreach ($plans as $plan) {
            $signatureDate = new Carbon($plan->signature_date);
            if ($signatureDate->addDays($plan->planRule->validity) < Carbon::now()) {
                //Files the plan and deactivates the adverts
                $plan->update(['payment_status' => '-1']);
                foreach ($plan->adverts as $advert) {
                    $controller = new PlanCronController();
                    if (!$controller->realocateAdvert($advert)) {
                        $advert->update(['status' => 0]);
                    }
                }
                try {
                    //Send an email informing the plan has expired
                    Mail::to($plan->user->email)->send(new InvalidPlan($plan->user->name, $plan->planRule->validity, $plan->adverts[0]->view_count));
                } catch (\Exception $exception) {
                    CronLog::create([
                        'cron_signature' => $this->signature,
                        'log' => $exception,
                        'user_id' => $plan->user->user_id,
                        'plan_id' => $plan->plan_id
                    ]);
                }
            }
        }
    }
}
