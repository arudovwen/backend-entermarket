<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class StoreOrderController extends Controller
{

    public function store(Request $request)
    {
        $user = auth('api')->user();
        $user->storeorder()->create([
            'subtotal' => $request->subtotal,
            'quantity' => $request->quantity,
            'price' => $request->price,
            'product_id' => $request->product_id,
            'store_id' => $request->store_id,
        ]);
    }

    public function index()
    {
        return auth('store_api')->user()->storeorders()->with('product', 'orderinfo', 'orderhistories', 'myorder')->where('payment_status', 'paid')->latest()->get();
    }
    public function storegetorderbystatus($status)
    {
        return auth('store_api')->user()->storeorders()->with('product', 'orderinfo', 'orderhistories', 'myorder')->where('payment_status', 'paid')->where('status', $status)->latest()->get();
    }
    
    public function gettotals()
    {
        $store = auth('store_api')->user();

        $orders  = $store->storeorders()->where('payment_status', 'paid')->get();
        $products  = $store->products()->get();
        $outofstocks = $products->filter(function ($a) {
            return !$a->in_stock;
        });
        $available = $products->filter(function ($a) {
            return $a->in_stock;
        });

        $ordersthismonth = $store->storeorders()->where('payment_status', 'paid')->whereMonth('created_at', '=', Carbon::now()->month)->whereYear('created_at', '=', Carbon::now()->year)->get();


        return response([
            [
                'icon' => "bx bx-copy-alt",
                'title' => "Total Products",
                'value' =>  count($products),
                'badgeValue' => count($outofstocks),

                'color' => "warning",
                'desc' => "Out of stock",
            ],
            [
                'icon' => "bx bx-archive-in",
                'title' => "Available Products",
                'value' => count($available),
                'badgeValue' => "+0 %",
                'color' => "success",
                'desc' => "From previous period",
            ],
            [
                'icon' => "bx bx-purchase-tag-alt",
                'title' => "Total Orders",
                'value' => count($orders),
                'badgeValue' => count($ordersthismonth),
                'color' => "info",
                'desc' => "Orders this month",
            ]



        ], 200);
    }

    public function gettopearner()
    {
        $store = auth('store_api')->user();
        return  $store->storeorders()->where('payment_status', 'paid')->with('product')->whereMonth('created_at', '=', Carbon::now()->month)->whereYear('created_at', '=', Carbon::now()->year)->orderByDesc('subtotal')->take(5)->get();
    }

    public function getearnings()
    {
        $store = auth('store_api')->user();
        $orders  = $store->storeorders()->where('payment_status', 'paid')->get()->map(function ($q) {
            return $q->subtotal;
        })->reduce(function ($a, $b) {
            return $a + $b;
        },0);
        $ordersthismonth = $store->storeorders()->where('payment_status', 'paid')->whereMonth('created_at', '=', Carbon::now()->month)->whereYear('created_at', '=', Carbon::now()->year)->get()->map(function ($q) {
            return $q->subtotal;
        })->reduce(function ($a, $b) {
            return $a + $b;
        },0);

        $orderslastmonth = $store->storeorders()->where('payment_status', 'paid')->whereMonth('created_at', '=', Carbon::now()->subMonth())->whereYear('created_at', '=', Carbon::now()->year)->get()->map(function ($q) {
            return $q->subtotal;
        })->reduce(function ($a, $b) {
            return $a + $b;
        },0);

        function sortOrders($month){
            $store = auth('store_api')->user();
          return  $store->storeorders()->where('payment_status', 'paid')->whereMonth('created_at', '=',$month)->whereYear('created_at', '=', Carbon::now()->year)->get()->map(function ($q) {
                return $q->subtotal;
            })->reduce(function ($a, $b) {
                return ($a + $b) | 0;
            },0);

        }
        $earningData = [];
        $a = 1;
        while ($a <= 12) {
            array_push($earningData, sortOrders($a));
            $a++;
        };


        return response([
            'earning' => $orders,
            'earningthismonth' => $ordersthismonth,
            'earninglastmonth' => $orderslastmonth,
            'earningChart' => $earningData


        ], 200);
    }
}
