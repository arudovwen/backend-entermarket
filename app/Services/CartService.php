<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;

class CartService
{
  public function createCart($store_name, $product_name, $brand_name, $price, $quantity, $description, $image, $store_id, $product_id, $weight)
  {
    $price = floatval($price);
    $quantity = intval($quantity);

    return [

      'store_name' => $store_name,
      'product_name' => $product_name,
      'brand_name' => $brand_name,
      'price' => $price,
      'quantity' => $quantity,
      'description' => $description,
      'image' => $image,
      'store_id' => $store_id,
      'product_id' => $product_id,
      'weight'=>$weight
    ];
  }

  public function add($user, $store_name, $product_name, $brand_name, $price, $quantity, $description, $image, $store_id, $product_id, $weight)
  {
    $cartitems = $user->cart()->get();
    if(count($cartitems)){
     $store_cart_id = $user->cart()->first()->store_id;
     if($store_cart_id !== $store_id){
        return response([
          'status' => false,
          'message' => 'Sorry, you can only shop one store at a time!'
        ], 400);
     }

    }
    $product = Product::find($product_id);
    if ($product->in_stock <= 0)  return response(['message' => 'Out of stock'], 400);
    $cartItems = $this->createCart($store_name, $product_name, $brand_name, $price, $quantity, $description, $image, $store_id, $product_id, $weight);

    $newcart = $user->cart()
      ->where([
        'product_id' => $cartItems['product_id'],
        'store_id' => $cartItems['store_id'],

      ])
      ->first();


    if (is_null($newcart)) {
      $item =  $user->cart()->create($cartItems);
      return response([
        'status' => 'success',
        'data' => $item
      ], 201);
    } else {
      if($product->in_stock < ($newcart->quantity + $quantity)) return response(['message' => 'Out of stock'], 400);

      $newcart->store_name = $store_name;
      $newcart->brand_name = $brand_name;
      $newcart->price = $price;
      $newcart->weight = $weight;
      $newcart->quantity =  $newcart->quantity + $quantity;
      $newcart->save();

      return response()->json([
        'status' => 'in_cart',
        'data' => $newcart
      ]);
    }
  }
  public function getCart($user)
  {
    $location = "";
    $cart = $user->cart()->get();
   if(count($cart)){
    $loc = Store::find($cart[0]->store_id);
    if(!is_null($loc)){
        $location =  $loc->location;
    }

   }
    $mappedcart = $cart->map(function ($a) {
      $a->subtotal = $a->quantity * $a->price;
      return $a;
    });
    $total =  $mappedcart->reduce(function ($total, $item) {
      return $total += $item->subtotal;
    });

    $commission = 0;
    $shipping = 0;
     $fulfilment = $commission + $shipping;
    return [
      'cart' => $mappedcart,
      'total' => $total,
      'commission' => $commission,
      'shipping' => $shipping,
      'fulfilment' => $fulfilment,
      'weight' => $this->totalweight($user),
      "location" => $location
    ];
  }
  public function update($quantity, $cart)
  {

    try {

         $product = Product::find($cart->product_id);
        if ($product->in_stock <=  $quantity)  return response(['message' => 'Out of stock'],400);

        $cart->quantity = $quantity;
        $cart->save();


      if ($cart) {
        return $cart;
      }
      return [];
    } catch (\Throwable $th) {
      return response()->json(
        [
          'status' => false,
          'message' => $th
        ],
        200
      );
    }
  }
  public function remove($cart)
  {
    try {
      if ($cart->quantity > 1) {
        $cart->quantity =  $cart->quantity - 1;
        $cart->save();
        return $cart;
      } else {
        $cart->delete();
        return;
      }
    } catch (\Throwable $th) {

      return response()->json(
        [
          'status' => false,
          'message' => 'no action'
        ],
        200
      );
    }
  }
  public function clearcart($user)
  {
    try {
      $user->cart()->delete();
      return response()->json(
        [
          'status' => true,
          'message' => 'cart cleared'
        ],
        200
      );
    } catch (\Throwable $th) {

      return response()->json(
        [
          'status' => false,
          'message' => 'no action'
        ],
        200
      );
    }
  }

  public function total($user)
  {
    $cart = $user->cart()->get();

    $mappedcart = $cart->map(function ($a) {
      $a->subtotal = $a->quantity * $a->price;
      return $a;
    });
    $total =  $mappedcart->reduce(function ($total, $item) {
      return $total += $item->subtotal;
    });

    $commission = 0;
    $shipping = 0;
    $fulfilment = $commission + $shipping;
    return [
      'cart' => $mappedcart,
      'total' => $total,
      'commission' => $commission,
      'shipping' => $shipping,
      'fulfilment' => $fulfilment,
      'weight' => $this->totalweight($user)

    ];
  }
  public function totalweight($user)
  {
    $cart = $user->cart()->get();

    $mappedcart = $cart->map(function ($a) {
      $a->totalWeight = $a->quantity * $a->weight;
      return $a;
    });
    $total =  $mappedcart->reduce(function ($total, $item) {
      return $total += $item->totalWeight;
    });

    return $total;
  }
}
