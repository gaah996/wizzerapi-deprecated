<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\Model;


class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function integrationJSON(){

        /*Database data is added to a response JSON file.
         *Send request api for integrate with other modules in the project
         *This is an example code for future updates
         * */
        $responseAPI = response()->json(['error' => ' Not Found'], 404);

        $numberArguments = (int)func_num_args();
        $valueArguments =  func_get_args();


        if ($numberArguments == 5 or $numberArguments == 3 or $numberArguments == 1 or $numberArguments == 2) {
            // This is condition ensure that will
            // not be occur errors in my function
            if($numberArguments == 1){

                /*
                 * This is function automate response JSON of Delete Database
                 * $valueArguments[0] - Message if request executed success
                 *                    - Message if  request failed
                 * */

                $responseAPI = response()->json(['error' => $valueArguments[0]], 404);

            }
            if($numberArguments == 2) {
                //This is function automate response generic the JSON, but
                //your parameters require only vector and number message http
                $responseAPI = response()->json($valueArguments[0], $valueArguments[1]);
            }
            else if ($numberArguments == 3) {
                /*This is function automate response generic the JSON
                * $valueArguments[0] - Name atribute of the message JSON
                * $valueArguments[1] - Message send JSON
                * $valueArguments[2] - Number send API
                * */

                if (is_string($valueArguments[0])  and is_int($valueArguments[2])) {
                    $responseAPI = response()->json([$valueArguments[0] => $valueArguments[1]],
                        $valueArguments[2]);
                }


            }else if ($numberArguments == 5) {
                /*
                 * This is function automate response JSON of Update and Create Date Database
                 * $valueArguments[0] - Message
                 * $valueArguments[1] - Array with Info Jason
                 * $valueArguments[2] - If request executed success If request failed - Model
                 * $valueArguments[3] - Number send API if send request
                 * $valueArguments[4] - Number send API if give error
                 * */



                if ($valueArguments[2] <> null) {
                    $responseAPI = response()->json($valueArguments[1], $valueArguments[3]);

                }
                else{
                    $responseAPI = response()->json(['error' =>  $valueArguments[0]],
                        $valueArguments[4]);
                }


            }
        }
        return $responseAPI;

    }


}
