<?php

namespace App\Http\Controllers;

//Models
use App\Advert;
use App\Plan;

use http\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use App\Mail\MailSupport;
use App\Mail\RequestUnderReview;
use App\Mail\PlanInformation;
use App\Mail\MailSupportUser;

class DashboardController extends Controller
{
    public function index()
    {
        //Gets the information showed in the dashboard home
        $user = Auth::User();

        $plans = $user->plans;
        $payment_status = '0';

        foreach ($plans as $plan) {
            if ($plan->payment_status == 2) {
                $payment_status = '2';
            }
        }

        if ($plans == null) {
            return response()->json(['error' => 'User do not have a plan'], 400);
        } else if ($payment_status != '2') {
            return response()->json(['error' => 'User plan is not yet active'], 400);
        } else {
            $totalMessages = 0;
            $totalViews = 0;
            $totalCalls = 0;

            //Counts all the messages and views
            $adverts = Advert::where('user_id', $user->user_id)->select('message_count', 'view_count', 'call_count', 'status')->get();
            foreach ($adverts as $advert) {
                $totalMessages += $advert->message_count;
                $totalViews += $advert->view_count;
                $totalCalls += $advert->call_count;
            }

            //Counts all the adverts
            $totalAdverts = $adverts->where('status', '1')->count();

            //Gets the max number of adverts the user can create
            $maxAdverts = 0;
            foreach ($plans as $plan) {
                if ($plan->payment_status == '2') {
                    $maxAdverts += $plan->planRule->adverts_number;
                }
            }

            return response()->json([
                'messages' => $totalMessages,
                'views' => $totalViews,
                'calls' => $totalCalls,
                'adverts' => $totalAdverts,
                'adverts_limit' => $maxAdverts
            ], 200);
        }
    }

    public function sendEmailSupport(Request $request)
    {
        //Gets the current user
        $user = Auth::user();

        $attachment = [];
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $attachment[] = $file;
            }
        }

        //Sends the support e-mail
        Mail::send(new MailSupport($user->name, $user->email, $request->subject, $request->message,$attachment));
        Mail::to($user->email)->send(new MailSupportUser($user->name, $user->email));

        return $request;
    }

    public function sendEmailCustomPlan(Request $request)
    {
        $user = Auth::user();
        $advertsNumber = $request['adverts_number'];
        try {
            if ($user->profile_type == 1) {
                if ($advertsNumber >= 110 && $advertsNumber < 500) {
                    
                    $price = ($advertsNumber - 100) * 0.75 + 129.90;

                    Mail::to($user['email'])->send(new RequestUnderReview($user,$advertsNumber, $price));
                    Mail::to('planos@wizzer.com.br')->send(new PlanInformation($user,$advertsNumber,$price));
                    return response()->json(['message'=>'Email sent with success'], 200);

                } elseif($advertsNumber >= 500){

                    $price = $advertsNumber * 0.75;

                    Mail::to($user['email'])->send(new RequestUnderReview($user,$advertsNumber,$price));
                    Mail::to('planos@wizzer.com.br')->send(new PlanInformation($user,$advertsNumber,$price));
                    return response()->json(['message'=>'Email sent with succes'], 200);

                }else {
                    return response()->json(['error' => 'This plan needs at least one hundred and ten adverts'], 403);
                }
            } else if ($user->profile_type == 2) {
                if ($advertsNumber >= 4) {
                    
                    $price = $advertsNumber * 149.90;
                    
                    Mail::to($user['email'])->send(new RequestUnderReview($user,$advertsNumber,$price));
                    Mail::to('planos@wizzer.com.br')->send(new PlanInformation($user,$advertsNumber,$price));
                    return response()->json(['price'=>$price], 200);

                } else {
                    return response()->json(['error' => 'This plan needs at least four adverts'], 403);
                }
            } else {
                return response()->json(['error' => 'User not allowed'], 403);
            }
        }catch(\Exception $exception){
            return response()->json(['error'=>'Mail not sent'], 404);
        }
    }
}





