<?php

namespace App\Http\Controllers;

use App\Seller;
use App\Advert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;


class SellersController extends Controller
{
    public function add(Advert $advert, Request $request) {
        $this->validate($request, [
            'avatar' => 'image',
            'name' => 'required',
            'phones' => 'array|max:3|min:1',
            'phones.*.0' => 'celular_com_ddd',
            'emails' => 'array|max:3|min:1',
            'emails.*' => 'email',
        ]);

        $user = Auth::user();

        if($user->user_id == $advert->user_id) {
            $seller = Seller::create([
                'name' => $request['name'],
                'phones' => json_encode($request['phones']),
                'emails' => json_encode($request['emails']),
                'site' => (isset($request['site'])) ? $request['site'] : null,
                'creci' => (isset($request['creci'])) ? $request['creci'] : null,
                'development_ad_id' => $advert->development->id
            ]);

            if($seller) {
                //Tries to save the picture
                $avatar = $this->saveAvatar($request, $seller);

                if($avatar) {
                    $seller = Seller::find($seller->id);
                    $seller->phones = json_decode($seller->phones);
                    $seller->emails = json_decode($seller->emails);

                    return response()->JSON(['seller' => $seller], 200);
                } else {
                    $seller->phones = json_decode($seller->phones);
                    $seller->emails = json_decode($seller->emails);

                    return response()->JSON(['seller' => $seller, 'error' => 'Couldn\'t save seller avatar'], 200);
                }
            } else {
                return response()->JSON(['error' => 'Couldn\'t create seller'], 400);
            }
        } else {
            return response()->JSON(['error' => 'Advert doesn\'t belong to user'], 400);
        }
    }

    public function update(Advert $advert, $seller, Request $request) {
        $this->validate($request, [
            'avatar' => 'image',
            'phones' => 'array|max:3|min:1',
            'phones.*.0' => 'celular_com_ddd',
            'emails' => 'array|max:3|min:1',
            'emails.*' => 'email',
        ]);

        $seller = Seller::find($seller);

        if(!$seller) {
            return response()->json(['warning' => 'seller not found'], 400);
        }

        $user = Auth::user();

        if($user->user_id == $advert->user_id) {
            if($advert->development->id == $seller->development_ad_id) {
                //Updates the seller
                $sellerId = $seller->id;
                $seller->update([
                    'name' => (isset($request['name'])) ? $request['name'] : $seller->name,
                    'phones' => (isset($request['phones'])) ? json_encode($request['phones']) : $seller->phones,
                    'emails' => (isset($request['emails'])) ? json_encode($request['emails']) : $seller->emails,
                    'site' => (isset($request['site'])) ? $request['site'] : $seller->site,
                    'creci' => (isset($request['creci'])) ? $request['creci'] : $seller->creci
                ]);

                $this->saveAvatar($request, $seller);

                $seller = Seller::find($sellerId);
                $seller->phones = json_decode($seller->phones);
                $seller->emails = json_decode($seller->emails);

                return response()->JSON(['seller' => $seller], 200);
            } else {
                return response()->JSON(['error' => 'Seller doesn\'t belong to development'], 403);
            }
        } else {
            return response()->JSON(['error' => 'Advert doesn\'t belong to user'], 403);
        }
    }

    public function delete(Advert $advert, Seller $seller) {
        $user = Auth::user();

        if($user->user_id == $advert->user_id) {
            if($advert->development->id == $seller->development_ad_id) {
                //Deletes the seller
                $seller->delete();
                //Deletes the avatar
                Storage::delete('public/' . $seller->avatar);

                return response()->JSON(['success' => 'Seller deleted'], 200);
            } else {
                return response()->JSON(['error' => 'Seller doesn\'t belong to development'], 403);
            }
        } else {
            return response()->JSON(['error' => 'Advert doesn\'t belong to user'], 403);
        }
    }

    public function getSeller(Seller $seller) {
        $seller->update([
            'view_count' => ($seller->view_count + 1)
        ]);

        $seller->phones = json_decode($seller->phones);
        $seller->emails = json_decode($seller->emails);

        return response()->JSON(['seller' => $seller]);
    }

    public function saveAvatar(Request $request, Seller $seller) {
        if($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');

            if(exif_imagetype($avatar)) {
                if($seller->avatar != null) {
                    //Deletes the current user avatar
                    Storage::delete('public/' . $seller->avatar);
                }

                $savedFile = Storage::put('public/sellers', $avatar);
                $savedFile = (explode('public/', $savedFile))[1];

                $seller->update([
                    'avatar' => $savedFile
                ]);

                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
}
