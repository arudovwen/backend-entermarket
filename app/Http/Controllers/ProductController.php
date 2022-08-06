<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{



    public function index()
    {
        $product = Product::with('store', 'category', 'brand')->latest()->paginate(20);
        return ProductResource::collection($product);
    }
    public function getallproducts()
    {
        $product = Product::with('store', 'category', 'brand')->latest()->paginate(20);
        return ProductResource::collection($product);
    }

    public function storeproducts(Request $request)
    {
        return Product::with('store', 'category', 'brand')->where('store_id', $request->store_id)->where('category_id', $request->category_id)->latest()->get();
    }

    public function allstoreproducts(Request $request)
    {

        $brand = $request->query->get('brand');
        $category = $request->query->get('category');
        $product = Product::with('store', 'category', 'brand')->where('store_id', $request->store_id)->where('active', 1)
            ->where(function ($query) use ($category) {
                if ($category) {
                    return $query->where('category_id', $category);
                }
            })->where(function ($query) use ($brand) {
                if ($brand) {
                    return $query->where('brand_id', $brand);
                }
            })->latest()->get();
        return ProductResource::collection($product->values()->paginate(20));
    }
    public function searchproducts(Request $request)
    {
        if (is_null($request['query'])) {
            $product = Product::with('store', 'category', 'brand')->where('store_id', $request->store_id)->where('active', 1)->latest()->get();
            return ProductResource::collection($product->values()->paginate(20));
        }
        $product = Product::query()->where('store_id', $request->store_id)->where('active', 1)->whereLike('product_name', $request['query'])->with('store', 'category', 'brand')->latest()->get();
        return ProductResource::collection($product->values()->paginate(20));
    }

    public function show(Product $product)
    {
        return $product->load('store', 'category', 'brand');
    }
    public function store(Request $request)
    {


        $store = auth('store_api')->user();
        $data = $request->all();
        $data['image'] = $request->image;

        $data['product_no'] = rand(000000, 999999);
        $product = $store->products()->create($data);
        return $product->load('store', 'category', 'brand');
    }

    public function bulkupload(Request $request)
    {


        $store = auth('store_api')->user();
        $products = $store->products()->createMany($request->all());
        return $products->load('store', 'category', 'brand');
    }




    public function update(Request $request, Product $product)
    {

        if ($request->filled('active')  && $request->has('active')) {

            $product->active = $request->active;
        }
        if (!empty($request->input('product_name')) && $request->filled('product_name')  && $request->has('product_name')) {
            $product->product_name = $request->product_name;
        }
        if (!empty($request->input('product_desc')) && $request->filled('product_desc')  && $request->has('product_desc')) {
            $product->product_desc = $request->product_desc;
        }
        if (!empty($request->input('in_stock')) && $request->filled('in_stock')  && $request->has('in_stock')) {
            $product->in_stock = $request->in_stock;
        }
        if (!empty($request->input('price')) && $request->filled('price')  && $request->has('price')) {
            $product->price = $request->price;
        }
        if (!empty($request->input('sales_price')) && $request->filled('sales_price')  && $request->has('sales_price')) {
            $product->sales_price = $request->sales_price;
        }
        if (!empty($request->input('image')) && $request->filled('image')  && $request->has('image')) {
            $product->image = $request->image;
        }
        if (!empty($request->input('product_no')) && $request->filled('product_no')  && $request->has('product_no')) {
            $product->product_no = $request->product_no;
        }

        if (!empty($request->input('weight')) && $request->filled('weight')  && $request->has('weight')) {
            $product->weight = $request->weight;
        }


        $product->save();
        return $product->load('category', 'brand');
    }

    public function getsimilarproducts($id)
    {
        $product = Product::find($id);
        $similar = Product::with('store', 'category', 'brand')->where(strtolower('product_name'), 'like', '%' . strtolower($product->product_name) . '%')
            ->orWhere('brand_id', $product->brand_id)->orWhere('category_id', $product->category_id)->inRandomOrder()->get();
        return $similar->filter(function ($a) use ($id) {
            return $a['id'] != $id;
        })->values()->all();
    }

    public function destroy(Product $product)
    {
        $id = $product->id;
        $product->delete();
        return response()->json(['id' => $id, 'message' => 'deleted']);
    }
}
