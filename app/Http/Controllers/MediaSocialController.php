<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\User;
use Socialite;
use App\Http\Controllers\UsersController;
use lluminate\Http\JsonResponse;


class MediaSocialController extends Controller
{



    public function redirect(Request $request)
    {
        $this->validate($request, [
            'mediasocial' => 'required|in:google,facebook'
        ]);
        return Socialite::driver($request->mediasocial)->redirect();
    }

    public function callback(Request $request)
    {
        //Gets a new UserController to operate the changes
        $usersController = new UsersController();

        //Answer from social network API (either Google or Facebook)
        $answer = Socialite::driver($request->mediasocial)->stateless()->user();

        //Checks if the user exists
        $user = User::where('email', $answer->email)->first();
        if(!$user){
            //Makes a request with the user info: name, email, password
            $request = \Illuminate\Http\Request();
            $request->replace([
                'name' => $answer->name,

            ]);

            $user = $usersController->register($answer) ;
        }

        $user = $usersController->loginSocialMedia($answer->email,$answer->id);

        if($user->status == 420) {
            //Returns error message
            return response()->JSON(['error' => 'Internal error'], 500);
        } else {
            //Returns the logged user
            return response()->JSON(['user' => $user], 200);
        }
    }


    public function validationInformationUser(Request $request){

        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required'
        ]);
    }
}
