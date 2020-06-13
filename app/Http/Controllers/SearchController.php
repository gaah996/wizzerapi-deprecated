<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function mapOrder($order) {
        switch ($order) {
            case 'low-price':
                return 'a.price ASC';
            case 'high-price':
                return 'a.price DESC';
            case 'popular':
                return 'a.view_count DESC';
            default:
                return 'a.created_at DESC';
        }
    }

    public function list(Request $request) {
        //Validate the filters
        $this->validate($request, $this->validationRules());
        $this->validate($request, [
            'order' => 'nullable|in:recent,low-price,high-price,popular'
        ]);

        //Define the filters to apply
        $filterQuery = $this->defineFilters($request);

        $query = 'SELECT * FROM (';

        $query .= 'SELECT DISTINCT a.advert_id, a.transaction, a.advert_type,a.price, p.property_type, p.neighborhood,';
        $query .= ' p.city, p.state, p.area, p.rooms, p.bathrooms, p.picture, a.view_count, null as title,';
        $query .= ' null as price_max, a.created_at FROM adverts a INNER JOIN properties p ON p.property_id = a.property_id';
        $query .= ' WHERE a.advert_type = \'default\' '.$filterQuery.' UNION ALL ';

        $query .= 'SELECT DISTINCT a.advert_id, a.transaction, a.advert_type, a.price, null as property_type,';
        $query .= ' d.neighborhood, d.city, d.state, null as area, null as rooms, null as bathrooms, d.logo as picture,';
        $query .= ' a.view_count, d.title, a.price_max, a.created_at FROM adverts a INNER JOIN development_ads d ON d.id = a.property_id';
        $query .= ' INNER JOIN properties p ON p.development_id = d.id WHERE a.advert_type = \'development\' '.$filterQuery;

        $query .= ') as a ORDER BY '. $this->mapOrder($request->input('order', 'recent'));

        $adverts = DB::select($query);

        $adverts = $this->applyPropertyTypeFilter($adverts, $request);

        $adverts = new LengthAwarePaginator($adverts->forPage($request->input('page', 1), 16), count($adverts), 16);

        return $this->response($adverts);
    }

    public function markers(Request $request) {
        //Validate the filters
        $this->validate($request, $this->validationRules());

        //Define the filters to apply
        $filterQuery = $this->defineFilters($request);

        $query = 'SELECT * FROM (';

        $query .= 'SELECT DISTINCT GROUP_CONCAT(a.advert_id) as advert_id, MAX(p.property_type) as property_type, MAX(a.transaction) as transaction, MAX(a.advert_type) as advert_type, MAX(a.price) as price, MAX(NULL) as price_max, MAX(p.lat) as lat, MAX(p.lng) as lng,';
        $query .= ' MAX(p.rooms) as rooms, MAX(p.bathrooms) as bathrooms, MAX(p.area) as area, MAX(p.picture) as picture, MAX(NULL) as title, p.street as street, p.neighborhood as neighborhood FROM adverts a INNER JOIN properties p ON p.property_id = a.property_id';
        $query .= ' WHERE a.advert_type = \'default\' '.$filterQuery.' GROUP BY p.street, p.neighborhood UNION ALL ';

        $query .= 'SELECT DISTINCT GROUP_CONCAT(a.advert_id) as advert_id, MAX(p.property_type) as property_type, MAX(a.transaction) as transaction, MAX(a.advert_type) as advert_type, MAX(a.price) as price, MAX(a.price_max) as price_max, MAX(d.lat) as lat,';
        $query .= ' MAX(d.lng) as lng, MAX(NULL) AS rooms, MAX(NULL) AS bathrooms, MAX(p.area) as area, MAX(d.logo) AS picture, MAX(d.title) as title, d.street as street, d.neighborhood as neighborhood FROM adverts a';
        $query .= ' INNER JOIN development_ads d ON d.id = a.property_id INNER JOIN properties p ON p.development_id = d.id';
        $query .= ' WHERE a.advert_type = \'development\' '.$filterQuery.' GROUP BY d.street, d.neighborhood';

        $query .= ') as a ORDER BY RAND() LIMIT 250';

        $adverts = DB::select($query);

        $adverts = $this->applyPropertyTypeFilter($adverts, $request);

        foreach ($adverts as $key=>$advert) {
            $adverts[$key]->advert_id = explode(',', json_encode($advert->advert_id));
            foreach ($adverts[$key]->advert_id as $innerkey=>$advert_id) {
                $adverts[$key]->advert_id[$innerkey] = preg_replace('/["]+/', '', $advert_id);
            }
        }

        return $this->response($adverts);
    }

    private function applyPropertyTypeFilter($adverts, $request) {
        $filteredAdverts = [];
        if(sizeof($request['filters']['propertyType']) != 0) {
            foreach($adverts as $advert) {
                $advert->property_type = gettype(json_decode($advert->property_type)) == 'array' ? json_decode($advert->property_type) : [];
                foreach ($advert->property_type as $propertyType) {
                    if(in_array($propertyType, $request['filters']['propertyType'])) {
                        array_push($filteredAdverts, $advert);
                    }
                }
            }
        } else {
            $filteredAdverts = $adverts;
        }

        return new Collection($filteredAdverts);
    }

    private function validationRules() {
        return [
            'maxLat' => 'required|numeric|min:-90|max:90',
            'minLat' => 'required|numeric|min:-90|max:90',
            'maxLng' => 'required|numeric|min:-180|max:180',
            'minLng' => 'required|numeric|min:-180|max:180',

            'filters.transaction' => 'required|array|max:2',
            'filters.transaction.*' => 'in:comprar,alugar',

            'filters.propertyType' => 'nullable|array|max:14',
            'filters.propertyType.*' => 'in:apartment,house,condo,farmhouse,flat,studio,land,roof,warehouse,commercial_set,farm,store,commercial_room,commercial_building',

            'filters.price.min' => 'required|numeric|min:0',
            'filters.price.max' => 'required|numeric|min:0',

            'filters.area.min' => 'required|numeric|min:0',
            'filters.area.max' => 'required|numeric|min:0',

            'filters.condo.min' => 'required|numeric|min:0',
            'filters.condo.max' => 'required|numeric|min:0',

            'filters.rooms' => 'nullable|array|max:7',
            'filters.rooms.*' => 'numeric|min:1|max:7',

            'filters.bathrooms' => 'nullable|array|max:7',
            'filters.bathrooms.*' => 'numeric|min:1|max:7',

            'filters.parkingSpaces' => 'nullable|array|max:7',
            'filters.parkingSpaces.*' => 'numeric|min:1|max:7',

            'filters.daysOnWizzer' => 'required|numeric|min:0'
        ];
    }

    private function defineFilters(Request $request) {
        $filterQuery = ' AND a.status = 1 AND p.lat BETWEEN '.$request['minLat'].' AND '.$request['maxLat'].' AND p.lng BETWEEN '.
            $request['minLng'].' AND '.$request['maxLng'];

        //Transaction filter
        if(sizeof($request['filters']['transaction']) == 1) {
            $transaction = $request['filters']['transaction'][0] == 'comprar' ? 'vender' : $request['filters']['transaction'][0];
            $filterQuery .= ' AND a.transaction = \''.$transaction.'\'';
        }

        //Price filter
        if($request['filters']['price']['min'] > 0) $filterQuery .= ' AND a.price > '.$request['filters']['price']['min'];
        if($request['filters']['price']['max'] > 0) $filterQuery .= ' AND a.price < '.$request['filters']['price']['max'];

        //Condo filter
        if($request['filters']['condo']['min'] > 0) $filterQuery .= ' AND a.condo > '.$request['filters']['price']['min'];
        if($request['filters']['condo']['max'] > 0) $filterQuery .= ' AND a.condo < '.$request['filters']['price']['max'];

        //Area filter
        if($request['filters']['area']['min'] > 0) $filterQuery .= ' AND p.area > '.$request['filters']['area']['min'];
        if($request['filters']['area']['max'] > 0) $filterQuery .= ' AND p.area < '.$request['filters']['area']['max'];

        //Rooms filter
        if(sizeof($request['filters']['rooms']) > 0) $filterQuery .= ' AND p.rooms IN ('.preg_replace('/[\[\] "]+/', '', json_encode($request['filters']['rooms'])).')';
        if(in_array('7', $request['filters']['rooms'])) $filterQuery .= ' AND p.rooms > 7';

        //Bathrooms filter
        if(sizeof($request['filters']['bathrooms']) > 0) $filterQuery .= ' AND p.bathrooms IN ('.preg_replace('/[\[\] "]+/', '', json_encode($request['filters']['bathrooms'])).')';
        if(in_array('7', $request['filters']['bathrooms'])) $filterQuery .= ' AND p.bathrooms > 7';

        //Parking spaces filter
        if(sizeof($request['filters']['parkingSpaces']) > 0) $filterQuery .= ' AND p.parking_spaces IN ('.preg_replace('/[\[\] "]+/', '', json_encode($request['filters']['parkingSpaces'])).')';
        if(in_array('7', $request['filters']['parkingSpaces'])) $filterQuery .= ' AND p.parking_spaces > 7';

        //Days on wizzer filter
        if($request['filters']['daysOnWizzer'] > 0) {
            $date = Carbon::now()->subDays($request['filters']['daysOnWizzer']);
            $filterQuery .= ' AND a.created_at >= \''.$date.'\'';
        }

        return $filterQuery;
    }

    private function response($adverts) {
        foreach ($adverts as $key=>$advert) {
            if(gettype(json_decode($adverts[$key]->picture)) == 'array') {
                $adverts[$key]->picture = sizeof(json_decode($adverts[$key]->picture)) > 0 ? json_decode($adverts[$key]->picture)[0] : null;
            }
        }

        return response()->json($adverts, 200);
    }
}