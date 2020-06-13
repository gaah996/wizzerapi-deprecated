<?php

namespace App\Http\Controllers;

//Models
use App\User;
use App\PasswordReset;

use http\Env\Response;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\PersonalAccessGrant;
use DateInterval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

use App\Mail\UserRegistration;
use App\Mail\NewUserEmail;
use App\Mail\OldUserEmail;
use App\Mail\PasswordChange;
use App\Mail\LostPassword;

use App\Http\Controllers\AdvertsController;

class UsersController extends Controller
{
    public function register(Request $request){//name, email, password
        //Data validation
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);
        if($request['phone']){
            $this->validate($request, [
                'phone' => 'celular_com_ddd',
            ]);
        }
        if($request['cpf_cnpj']){
            $this->validate($request, [
                'cpf_cnpj' => ['unique:users', new \App\Rules\CpfCnpj]
            ]);
        }
        if(isset($request['profile_type'])){
            $this->validate($request, [
                'profile_type' => 'in:0,1,2'
            ]);
        }


        //User creation and access token definition
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'profile_type' => 9
        ]);
        if(isset($request['profile_type'])){
            $user->update(['profile_type' => $request->profile_type]);
        }
        if($request['cpf_cnpj']){
            $user->update(['cpf_cnpj' => $request->cpf_cnpj]);
        }
        if($request['phone']){
            $user->update(['phone' => $request->phone]);
        }

        $token = $this->setUserToken($user);

        //Send email to the user
        $to =  $request->email;

        Mail::to($to)->send(new UserRegistration($request->name));

        $user->token = $token->accessToken;

        return response()->json(['user' => $user], 200);
    }

    public function setProfileType(Request $request){
        $user = Auth::user();

        $this->validate($request, [
            'profile_type' => 'in:0,1,2|required'
        ]);

        $user->update(['profile_type' => $request->profile_type]);

        return response()->json(['user' => $user], 200);
    }

    public function registerSocialMedia($user){
        //User creation and access token definition
        $user = User::create([
            'name' => $dateUser->name,
            'email' => $dateUser->email,
            'password' => bcrypt($dateUser->id),
            'avatar' =>$dateUser->avatar
        ]);

        $token = $this->setUserToken($user);
        return response()->json(['user' => $user, 'token' => $token->accessToken], 200);

    }


    public function login(Request $request){//email, password
        //Data validation
        $this->validate($request, [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8'
        ]);

        //Try to authenticate the user
        if (Auth::attempt($request->all())) {
            //If authentication worked creates the token
            $user = Auth::user();
            $user['token'] = $this->setUserToken($user)->accessToken;

            return response()->json(['user' => $user], 200);
        } else {
            return response()->json(['error' => 'Couldn\'t find user' ], 420);
        }
    }

    public function loginSocialMedia($email, $password){//email, password
        //This is function make login user of media social in aplication

        //Try to authenticate the user
        if (Auth::attempt(array('email'=>$email, 'password' => $password))) {
            //If authentication worked creates the token
            $user = Auth::user();
            $user['token'] = $this->setUserToken($user)->accessToken;

            return response()->json(['user' => $user], 200);
        } else {
            return response()->json(['error' => 'Couldn\'t find user' ], 420);
        }
    }



    public function sendPasswordResetCode(Request $request){
        //Sends a link to the user's email to reset the password
        //Validates the email sent by the user
        $this->validate($request, [
            'email' => 'required|email|exists:users,email'
        ]);

        //If the email exists create a reset code entry in the
        //database and send a link with the reset code via email

        //Creates the reset code
        do{
            $resetCode = str_random(32);
        } while(PasswordReset::where('token', $resetCode)->get() == '');

        //Checks if there is already a token for the user
        $passwordReset = PasswordReset::where('email', $request->email)->first();
        if($passwordReset == ''){
            PasswordReset::create([
                'email' => $request->email,
                'token' => $resetCode
            ]);
        } else{
            $passwordReset->update([
                'token' => $resetCode
            ]);
        }

        //Send email to the user
        $to = $request->email;
        $name = User::where('email', $request->email)->value('name');
        Mail::to($to)->send(new LostPassword($name, $resetCode));

        return response()->json(['success' => 'Token sent via email'], 200);
    }

    public function resetPassword(Request $request){
        //Resets the user password via the link sent to the user's e-mail
        //Validates the data
        $this->validate($request, [
            'token' => 'required|size:32',
            'password' => 'required|min:8'
        ]);

        $passwordReset = PasswordReset::where('token',$request->token)->first();

        if($passwordReset != ''){
            //Updates password in the users table
            User::where('email', $passwordReset->email)->update(['password' => bcrypt($request->password)]);

            //Excludes the token
            $passwordReset->delete();

            return response()->json(['success' => 'Password redefined'], 200);
        } else{
            return response()->json(['error' => 'Invalid token'], 400);
        }
    }

    public function saveProfilePicture(Request $request){
        //Saves the picture in the server and the path in the database
        $this->validate($request, [
            'avatar' => 'required|image'
        ]);

        $avatar = $request->file('avatar');

        //Checks if file is an image
        if(exif_imagetype($avatar)){
            //Checks if user already has an avatar
            $currentAvatar = Auth::user()->avatar;

            if($currentAvatar != ''){
                //Deletes the current user avatar
                Storage::delete('public/' . $currentAvatar);
            }

            //Saves the file in the disk
            $savedFile = Storage::put('public/avatars', $avatar);
            $savedFile = (explode('public/', $savedFile))[1];

            //Saves the path to the database
            Auth::user()->update(['avatar' => $savedFile]);

            return response()->json(['avatar' => $savedFile], 200);
        } else{
            return response()->json(['error' => 'Filetype is not supported'], 400);
        }
    }

    public function deleteProfilePicture() {
        $currentAvatar = Auth::user()->avatar;

        if($currentAvatar != ''){
            //Deletes the current user avatar
            Storage::delete('public/' . $currentAvatar);
        }

        Auth::user()->update(['avatar' => null]);

        return response()->json(['success' => 'Profile picture deleted with success'], 200);

    }

    public function show(){
        return response()->json(Auth::user(), 200) ;
    }

    public function update(Request $request){//name, email, password(optional), cpf_cnpj(optional),
        //creci(optional), phone(optional), site(optional)
        //Data validation
        $this->validate($request, [
            'email' => 'email|unique:users',
            'password' => 'min:8',
            'profile_type' => 'in:0,1,2'
        ]);
        if($request['phone']){
            $this->validate($request, [
                'phone' => 'celular_com_ddd',
            ]);
        }
        if($request['cpf_cnpj']){
            if($request['cpf_cnpj'] != Auth::user()->cpf_cnpj)
            $this->validate($request, [
                'cpf_cnpj' => ['unique:users', new \App\Rules\CpfCnpj]
            ]);
        }

        //Update the current user info
        $user = Auth::user();
        $user->update($request->all());
        return response()->json(['user' => $user], 200);
    }

    public function updateEmail(Request $request){
        //Data validation
        $this->validate($request, [
            'email' => 'email|unique:users'
        ]);

        //Update the current user info
        $user = Auth::user();
        $oldEmail = $user->email;
        $user->update($request->all());

        //Send email to the user
        $toNew = $user->email;
        $toOld = $oldEmail;

        Mail::to($toNew)->send(new NewUserEmail($user->name, $toNew));
        Mail::to($toOld)->send(new OldUserEmail($user->name, $toOld, $toNew));

        return response()->json(['user' => $user], 200);
    }

    public function updatePassword(Request $request){
        //Data validation
        $this->validate($request, [
            'current_password' => 'required',
            'new_password' => 'required|min:8'
        ]);

        //Update the current user info
        $user = Auth::user();

        $currentPassword = DB::table('users')->where('user_id', $user->user_id)->value('password');

        if(Hash::check($request->current_password, $currentPassword)){
            if($request->current_password == $request->new_password){
                return response()->json(['error' => 'Password are equals'], 400);
            } else {
                $user->update(['password' => bcrypt($request->new_password)]);

                Mail::to($user->email)->send(new PasswordChange($user->name));

                return response()->json(['success' => 'Password changed'], 200);
            }
        } else {
            return response()->json(['error' => 'Current password doesn\'t match'], 400);
        }
    }

    public function delete(){
        //Delete the current user
        $user = Auth::user();

        //Deletes the current user adverts
        try {
            $advertsController = new AdvertsController();

            foreach ($user->adverts as $advert) {
                $advertsController->delete($advert);
            }
        } catch(\Exception $error) {
            return response()->json(['response' => 'Problem while deleting adverts'], 400);
        }

        //Sets all the user plans to deactivated
        try {
            $plansController = new PagSeguroController();

            foreach($user->plans as $plan) {
                if($plan->payment_status != '-1') {
                    if($plan->pagseguro_plan_id != NULL && ($plan->payment_status == '3' || $plan->payment_status == '60')) {
                        $plansController->cancelPlan();
                    } else {
                        $plan->update(['payment_status' => '-1']);
                    }
                }
            }
        } catch(\Exception $error) {
            return response()->json(['response' => 'Problem while deactivating plans'], 400);
        }

        //Deletes the user
        if($user->delete()){
            return response()->json(['response' => 'User deleted'], 200);
        } else{
            return response()->json(['error' => 'A problem occurred'], 501);
        }
    }

    public function checkToken(){
        return response()->json(['success' => 'Token is valid'], 200);
    }

    private function setUserToken(User $user){
        //Sets the token validity
        $authorizarionServer = app()->make(\League\OAuth2\Server\AuthorizationServer::class);
        $authorizarionServer->enableGrantType(
            new PersonalAccessGrant, new DateInterval('PT12H')
        );

        //Checks if there is already a token for the user and deletes it
//        $token = DB::table('oauth_access_tokens')->where('user_id', $user->user_id)->get();
//
//        if($token != []){
//            DB::table('oauth_access_tokens')->where('user_id', $user->user_id)->delete();
//        }

        //Creates a new token for the user
        $token = $user->createToken('AccessToken');

        return $token;
    }
}
