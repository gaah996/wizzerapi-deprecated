<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Plan;
use App\PlanRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class PlansController extends Controller
{
    public function index(Request $request){
        $userID = Auth::user()->user_id;

        $plans = Plan::where('user_id', $userID)->where('payment_status','!=', '-1')->orderBy('signature_date', 'desc')->get();

        if (count($plans) == 0){
            return response()->JSON(['error' => 'User doesn\'t have a plan'], 404);
        } else {
            foreach($plans as $index => $plan){
                if($plan->planRule->renewable == 1){
                    $validity = $plan->paymentOrders()->orderBy('scheduling_date', 'desc')->first()->scheduling_date;
                    $validity = (new Carbon($validity)) ;
                }
                else{
                    $validity = (new Carbon($plan->signature_date))->addDays($plan->planRule->validity);
                }
                $response[$index]['profile_type'] = $plan->planRule->profile_type;
                $response[$index]['adverts_number'] = $plan->planRule->adverts_number;
                $response[$index]['images_per_advert'] = $plan->planRule->images_per_advert;
                $response[$index]['price'] = number_format($plan->planRule->price, 2);
                $response[$index]['payment_id'] = $plan->payment_id;
                $response[$index]['payment_link'] = $plan->payment_link;
                $response[$index]['pagseguro_plan_id'] = $plan->pagseguro_plan_id;
                $response[$index]['payment_status'] = $plan->payment_status;
                $response[$index]['signature_date'] = $plan->signature_date;
                $response[$index]['plan_rule_id'] = $plan->plan_rule_id;
                $response[$index]['validity'] = $validity-> toDateString();
            }
            return response()->JSON(['plans' => $response], 200);
        }
    }

    public function recover() {
        $user = Auth::user();

        $plans = PlanRule::where('profile_type', $user->profile_type)->get();

        $plansFiltered = [];

        foreach($plans as $plan){
            $plan->price = number_format($plan->price, 2, '.', '');

            if($plan->user_id == null || $plan->user_id == $user->user_id) {
                $plansFiltered[] = $plan;
            }
        }

        return response()->JSON(['plans' => $plansFiltered], 200);
    }

    public function store(Request $request){
        //Creates a new plan for the current user based on the informed plan_rule

        //Checks if the plan_rule exists and creates the plan for the user
        $planExist = Plan::find($request['plan_id']);


        if(is_null($planExist)){
         

            //Calls validation function
            $this->validateDataPlans($request);

            $planRule = PlanRule::find($request['plan_rule_id']);


           if(!is_null( $planRule)){

                $request['user_id'] = Auth::user()->user_id;
                $request['signature_date'] = now();


                //Exception treatment : if user_id is duplicate the program trigger a message error
                try {

                    $modelPlan = Plan::create($request->all());
                    return $this->integrationJSON('Plan not added with sucess',
                        $this->dataDatabasePlans($request), $modelPlan, 201, 403);
                } catch (\Illuminate\Database\QueryException $e) {

                    return $this->integrationJSON('Inconsistence data in database.');

               }
            } else{
                return $this->integrationJSON('The rule plan informed is not defined.');
            }
        }else{
            return $this->integrationJSON('The plan really exist.');
        }
    }

    public function show(){
        //Shows the current plan signed by the current user
        $userId = Auth::user()->user_id;

        //This is clausule SQL show information the table plans and plan_rules
        //and validate user_id in the tables plan_rules and plans
        $planInformation = DB::table('plans')
            ->leftjoin('users as users', 'users.user_id', '=', 'plans.user_id')
            ->leftjoin('plan_rules as plan_rules', 'plan_rules.plan_rule_id',
                '=', 'plans.plan_rule_id')
            ->select('plans.signature_date', 'plans.payment_info',
                'plan_rules.adverts_number','plan_rules.price', 'plan_rules.validity',
                'users.name')
            ->where('plans.user_id', $userId)
            ->get();

        return $planInformation;
    }


    public  function update(Request $request){
        //Updates the current user's plan

        $userId = Auth::user()->user_id;

        // Validate data
        $this->validateDataPlans($request);

        //Checks if the user already have a plan
        $modelPlan = Plan::where('user_id', $userId)->first();

        $alterPlan = null;

        if(!is_null($modelPlan)){

            $planRule = PlanRule::find($request['plan_rule_id']);
            if(!is_null($planRule)){

                $request['user_id'] = $userId;
                $request['signature_date'] = now();
                $modelPlan->update($this->dataDatabasePlans($request));
                $alterPlan = $this->integrationJSON('Plan not updated with sucess',
                    $this->dataDatabasePlans($request), $modelPlan, 201,403);
            } else{
                $alterPlan =  $this->integrationJSON('error' ,
                    'The rule of plan  informed  is not defined', 403);
            }
        } else{
            $alterPlan = $this->integrationJSON('error' ,
                'User doesn\'t have any active plan', 204);
        }
        return $alterPlan ;
    }

    public function delete(){
        //Deletes the current user's plan
        $userId = Auth::user()->user_id;

        $modelPlan = Plan::where('user_id', $userId);
        $responseJSON = null;


        if(!is_null($modelPlan)){
            try{
                $modelPlan->delete();
                $responseJSON = $this->integrationJSON('sucess', 'Plan deleted with sucess', 204);
            }catch (\Illuminate\Database\QueryException $e){
                $responseJSON = $this->integrationJSON('error', 'A problem occurred', 400);
            }
        }else {
            $responseJSON = $this->integrationJSON( 'Plans not exist.');
        }

        return $responseJSON;

    }
    private function dataDatabasePlans(Request $request)
    {
        return ['signature_date' => $request->signature_date,
               'payment_info'    => $request->payment_info,
               'plan_rule_id'    => $request->plan_rule_id,
               'user_id'         => $request->user_id];
    }

    private function validateDataPlans(Request $request){
        //Validates the data sent by the user.
        $this->validate($request, [
             'plan_rule_id'=>'required|integer',
             'payment_info' =>'nullable',
        ]);
    }



}
