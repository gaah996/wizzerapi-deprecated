<?php


namespace App\Console\Commands\Controllers;


use App\Advert;
use App\Mail\ApprovedPayment;
use App\Mail\RefusedPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class PlanCronController
{
    public function updateCurrentPaymentOrder($paymentOrder, $responsePaymentOrder) {
        //Atualiza a ordem de pagamento
        $paymentOrder->update([
            'status' => $responsePaymentOrder->status,
            'last_event_date' => new Carbon($responsePaymentOrder->lastEventDate),
            'transactions' => json_encode($responsePaymentOrder->transactions),
        ]);

        if($responsePaymentOrder->status == '5') {
            //Updates the plan status to active
            $paymentOrder->plan()->update([
                'payment_status' => '2'
            ]);
            //Send approved payment email
            Mail::to($paymentOrder->plan->user->email)->send(new ApprovedPayment($paymentOrder->plan->user->name));
        } elseif($responsePaymentOrder->status == '6') {
            //Send payment error email
            Mail::to($paymentOrder->plan->user->email)->send(new RefusedPayment($paymentOrder->plan->user->name));
        }
    }

    public function realocateAdvert(Advert $advert)
    {
        $user = $advert -> user;
        foreach($user->plans as $plan)
        {
            $maxAdverts = $plan -> planRule -> adverts_number;
            $currentAdverts = $plan -> adverts ( )->count();
            if($maxAdverts > $currentAdverts){
                $advert -> update([
                    'plan_id' => $plan -> plan_id
                ]);
                return true;
            };
        }
        return false;
    }
}