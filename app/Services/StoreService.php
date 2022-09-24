<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Product;
use App\Support\Collection;
use Spatie\Geocoder\Geocoder;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\StoreResource;

class StoreService
{
  public function createstore($request)
  {
    $client = new \GuzzleHttp\Client();
    $geocoder = new Geocoder($client);
    $geocoder->setApiKey(config('geocoder.key'));
    // $geocoder->setCountry(config('geocoder.country', 'Nigeria'));
    $data = $request->all();
    $response = $geocoder->getCoordinatesForAddress($request->location);

    $data['lat'] = $response['lat'];
    $data['long'] = $response['lng'];
    $data['location'] = $response['formatted_address'];
    $data['place'] = $response['address_components'][2]->long_name;

    $data['password'] = Hash::make($data['password']);
    return  Store::create($data);
  }
  public function findStoreById()
  {
  }

  public function getallstores($request)
  {


    $stores = Store::where('status', 1)->latest()->get();
    $result = $stores->map(function ($a) use ($request) {
      $a['distance'] = $this->distanceCalculation($request['lat'], $request['long'], $a['lat'], $a['long']);
      return $a;
    })->sortBy(function ($a) {
      return $a['distance'] ;
    });

    return  StoreResource::collection($result->values()->paginate(20));
  }

  public function searchstores($request)
  {
    if (is_null($request['query'])) {
      $stores = Store::where('status', 1)->latest()->get();
      $result = $stores->map(function ($a) use ($request) {
        $a['distance'] = $this->distanceCalculation($request['lat'], $request['long'], $a['lat'], $a['long']);
        return $a;
      })->sortBy(function ($a) {
        return $a['distance'];
      });

      return  StoreResource::collection($result->values()->paginate(20));
    }

  $stores = Store::query()->where('status', 1)->whereLike('name', $request['query'])->get();
    $result = $stores->map(function ($a) use ($request) {
      $a['distance'] = $this->distanceCalculation($request['lat'], $request['long'], $a['lat'], $a['long']);
      return $a;
    })->sortBy(function ($a) {
      return $a['distance'];
    });

    return  StoreResource::collection($result->values()->paginate(20));
  }
  public function searchsite($request)
  {
    if (is_null($request['query'])) {
      $stores = Store::where('status', 1)->latest()->get();
      $result = $stores->map(function ($a) use ($request) {
        $a['distance'] = $this->distanceCalculation($request['lat'], $request['long'], $a['lat'], $a['long']);
        return $a;
      })->sortBy(function ($a) {
        return $a['distance'];
      });

      return  StoreResource::collection($result->values()->paginate(20));
    }

  $stores = Store::query()->where('status', 1)->whereLike('name', $request['query'])->get();
  $products = Product::query()->with('store')->where('in_stock','>' ,1)->whereLike('product_name', $request['query'])->get();
    $result = $stores->map(function ($a) use ($request) {
      $a['distance'] = $this->distanceCalculation($request['lat'], $request['long'], $a['lat'], $a['long']);
      return $a;
    })->sortBy(function ($a) {
      return $a['distance'];
    });

    return  [
      'stores'=>StoreResource::collection($result->values()->all()),
      'products' => $products
    ];
  }
  
  public function showallstores()
  {

   return StoreResource::collection(Store::latest()->paginate(20));
  }
  public  function distanceCalculation($point1_lat, $point1_long, $point2_lat, $point2_long, $unit = 'mi', $decimals = 2)
  {
    // Calculate the distance in degrees
    $degrees = rad2deg(acos((sin(deg2rad($point1_lat)) * sin(deg2rad($point2_lat))) + (cos(deg2rad($point1_lat)) * cos(deg2rad($point2_lat)) * cos(deg2rad($point1_long - $point2_long)))));

    // Convert the distance in degrees to the chosen unit (kilometres, miles or nautical miles)
    switch ($unit) {
      case 'km':
        $distance = $degrees * 111.13384; // 1 degree = 111.13384 km, based on the average diameter of the Earth (12,735 km)
        break;
      case 'mi':
        $distance = $degrees * 69.05482; // 1 degree = 69.05482 miles, based on the average diameter of the Earth (7,913.1 miles)
        break;
      case 'nmi':
        $distance =  $degrees * 59.97662; // 1 degree = 59.97662 nautic miles, based on the average diameter of the Earth (6,876.3 nautical miles)
    }
    return round($distance, $decimals);
  }
}
