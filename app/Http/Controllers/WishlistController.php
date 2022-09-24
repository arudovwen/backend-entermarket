<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    public $wishlistservice;
    public $user;

    public function __construct(WishlistService $wishlistservice)
    {
        $this->wishlistservice = $wishlistservice;
        $this->user = auth('api')->user();
    }

    public function store(Request $request)
    {

        return  $this->wishlistservice->createwishlist($request, $this->user);
    }
    public function storeitem(Request $request)
    {
        return  $this->wishlistservice->createwishlistitem($request, $this->user);
    }
    public function index()
    {
        return $this->wishlistservice->userwishlist($this->user);
    }
    public function destroy(Wishlist $wishlist)
    {
        $wishlist->delete();
        return $this->response_success('removed');
    }
    public function destroyitem( $id)
    {
        WishlistItem::find($id)->delete();
       
        return $this->response_success('removed');
    }

    public function addlisttocart(Wishlist $wishlist)
    {


        return  DB::transaction(function () use ($wishlist) {
            $items = $wishlist->load('wishlistitems')->wishlistitems;
            $cart = new CartService;
            // $user, $store_name, $product_name, $brand_name, $price, $quantity, $description, $image, $store_id, $product_id
            if(count($items)){
                $store_id = $items[0]['store_id'];
            }
            foreach ($items as  $item) {
                if($store_id == $item['store_id']){
                    $store =  Store::find($item->store_id);
                    $product = Product::find($item->product_id);
                    $storeName = $store->name;
                    $cart->add($this->user, $storeName, $product->product_name, $product->product_name, $product->price, 1, $product->product_desc, $product->image[0], $item->store_id, $item->product_id, $item->weight);
                    $item->delete();
                }

            }


            return response('created', 200);
        });
    }

    public function destroyall()
    {
        $this->user->wishlist()->delete();
        return $this->response_success('wishlist cleared');
    }
}
