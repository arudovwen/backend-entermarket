<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\CartService;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $cartservice;
    public $user;

    public function __construct(CartService $cartservice)
    {
        $this->cartservice = $cartservice;
        $this->user = auth('api')->user();
    }
    public function index()
    {

        return $this->cartservice->getCart($this->user);
    }

    public function store(Request $request)
    {


        $store = Store::find($request->store_id);
        $product = Product::find($request->product_id);
        $quantity =  1;

        return  $this->cartservice->add(
            $this->user,
            $store->name,
            $product->product_name,
            $product->product_name,
            $product->sales_price? $product->sales_price:$product->price,
            $quantity,
            $product->product_desc,
            $product->image[0],
            $store->id,
            $product->id,
            intval($product->weight),
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Cart  $cart
     * @return \Illuminate\Http\Response
     */
    public function show(Cart $cart)
    {
        return $cart;
    }

    public function gettotal()
    {

        return $this->cartservice->total($this->user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Cart  $cart
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Cart $cart)
    {


        return $this->cartservice->update($request->quantity, $cart);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Cart  $cart
     * @return \Illuminate\Http\Response
     */
    public function destroy(Cart $cart)
    {
        $cart->delete();
        return response()->json('removed');
    }
    public function destroyall()
    {
        return  $this->cartservice->clearcart($this->user);
    }
}
