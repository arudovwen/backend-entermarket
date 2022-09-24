<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    // public function getprice(val){
    //   array_map(function($a){
    //     return $a['subtotal'];
    //   }, val);
    // }
    public function toArray($request)
    {
        return [

            'id' => $this->id,
            'distance' => $this->distance,
            'image' => $this->image,
            'location' => $this->location,
            'name' => $this->name,
            'status' => $this->status,
            'products' => count($this->products),
            'storeorders' => collect($this->storeorders)->count(),
            'price'=> collect($this->storeorders)->sum('subtotal')
        ];
    }
}
