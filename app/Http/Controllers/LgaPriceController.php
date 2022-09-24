<?php

namespace App\Http\Controllers;

use App\Models\Lga;
use App\Models\Store;
use App\Models\LgaPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LgaPriceController extends Controller
{

    public function index()
    {
        return Lga::get();
    }
    public function getlgaprices($id)
    {
        return LgaPrice::where('lga_id', $id)->get();
    }
    public function getlgaprice($id, $storeId)
    {

        $store = Store::find($storeId);

        return LgaPrice::where('lga_id', $store->lga->id)->where('to_id', $id)->first();
    }
    public function show(LgaPrice $lga_price)
    {
        return $lga_price;
    }
    public function addlga(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'lga' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->toArray()
            ], 500);
        }



        return  Lga::create(
            [
                'lga' => $request->lga,

            ]
        );
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'lga_id' => 'required',
            'to_id' => 'required',
            'standard_fee' => 'required|numeric',
            'express_fee' => 'required|numeric',
            'scheduled_fee' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->toArray()
            ], 500);
        }


        $lga = Lga::find($request->lga_id);
        $to = Lga::find($request->to_id);
        return  LgaPrice::create(
            [
                'lga' => $lga->lga,
                'lga_id' => $lga->id,
                'to' => $to->lga,
                'to_id' => $to->id,
                'standard_fee' => intval($request->standard_fee),
                'express_fee' => intval($request->express_fee),
                'scheduled_fee' => intval($request->scheduled_fee),
            ]
        );
    }
    
    public function updatelga(Request $request, Lga $lga)
    {

        if ($request->has('lga') && $request->filled('lga')) {
            $lga->lga =  $request->lga;
        }

        $lga->save();
        return $lga;
    }
    public function update(Request $request, LgaPrice $lga_price)
    {

        if ($request->has('standard_fee') && $request->filled('standard_fee')) {
            $lga_price->standard_fee =  $request->standard_fee;
        }
        if ($request->has('express_fee') && $request->filled('express_fee')) {
            $lga_price->express_fee =  $request->express_fee;
        }
        if ($request->has('scheduled_fee') && $request->filled('scheduled_fee')) {
            $lga_price->scheduled_fee =  $request->scheduled_fee;
        }


        $lga_price->save();
        return $lga_price;
    }
    public function destroy( LgaPrice $lga_price)
    {
        $lga_price->delete();
        return response('ok');
    }
    public function deletelga(Lga $lga)
    {
        $lga->delete();
        return response('ok');
    }
}
