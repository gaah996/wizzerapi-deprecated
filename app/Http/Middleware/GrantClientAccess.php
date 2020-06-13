<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class GrantClientAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if($request->header('Origin') == 'https://www.wizzer.com.br') {
            return $next($request);
        } else {
            $clientToken = $request->header('client-authorization');

            $client = '';
            if($clientToken) {
                $client = DB::table('oauth_clients')->where('secret', $clientToken)->first();
            }

            if($client){
                return $next($request);
            }

            return response()->JSON(['error' => 'You are not authorized to access the API'], 401);
        }
    }
}
