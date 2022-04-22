<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Coupon;
use App\Models\CouponUser;
use Illuminate\Http\Request;
use App\Services\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    public $user;
    public $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->user = auth('api')->user();
        $this->orderService = $orderService;
    }

    public function index()
    {
        return $this->user->orders()->with('orderhistories', 'orderinfo')->where('payment_status', 'paid')->latest()->get();
    }

    public function adminindex()
    {
        return OrderResource::collection(Order::with('orderhistories', 'orderinfo')->where('payment_status', 'paid')->latest()->paginate(20));
    }
    public function adminorderspending()
    {
        return Order::with('orderhistories', 'orderinfo')->where('status', 'pending')->where('payment_status', 'paid')->latest()->paginate(20);
    }
    public function adminordersassigned()
    {
        return Order::with('orderhistories', 'orderinfo')->where('status', 'assigned')->where('payment_status', 'paid')->latest()->paginate(20);
    }


    public function storeorders()
    {
        return auth('store_api')->user()->orders()->with('orderhistories', 'orderinfo')->latest()->get();
    }


    public function store(Request $request)
    {

        return DB::transaction(function () use ($request) {

            $name = $request->input('name') ? $request->input('name') : 'Order-' . rand(0000, 9999);

            if ($request->has('coupon') && $request->filled('coupon')) {
                //check coupon code
                $coupon = $request->coupon;
                $usercoupon = Coupon::where('code', $coupon)->where('status', 'active')->where('available', '>', 0)->first();
                $check  = CouponUser::where('user_id', $this->user->id)->where('coupon_id', $usercoupon->id)->first();

                if (!is_null($usercoupon && is_null($check))) {
                    $discount_percent =  ($usercoupon->discount / 100);
                    $couponuser = new CouponUser();
                    $couponuser->user_id = $this->user->id;
                    $couponuser->coupon_id = $usercoupon->id;
                    $couponuser->save();


                    //reduce available coupons
                    $usercoupon->available = $usercoupon->available - 1;
                    $usercoupon->save();
                } else {
                    $discount_percent = 1;
                }
            } else {
                $discount_percent = 1;
            }

            return $this->orderService->create(
                $this->user,
                $name,
                $request->shipping ? $request->shipping : 0,
                $request->coupon,
                $discount_percent,
                $request->commission,
                $request->allAddress,
                $request->pickupPoint,
                $request->extraInstruction,
                $request->paymentMethod,
                $request->title,
                $request->deliverymethod,
                $request->coupon ? $request->coupon : null,

            );
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {

        return $order->load('orderhistories', 'orderinfo');
    }
    public function adminshow(Order $order)
    {

        return $order->load('orderhistories', 'orderinfo');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function updateorderstatus(Request $request, Order $order)
    {
        $order->status = $request->status;
        if ($request->status === 'delivered') {
            $order->status = 'delivered';
        }


        $order->save();
        return $order->load('orderhistories', 'orderinfo');
    }
    public function assignlogistic(Request $request, Order $order)
    {

        if ($request->has('status') && $request->filled('status')) {
            $order->status = $request->status;
        }
        if ($request->has('logistic') && $request->filled('logistic')) {
            $order->logistic = $request->logistic;
        }
        if ($request->has('logistic_status') && $request->filled('logistic_status')) {
            $order->logistic_status = $request->logistic_status;
        }
        if ($request->logistic_status === 'delivered') {
            $order->status = 'delivered';
        }
        if ($request->has('view_at') && $request->filled('view_at')) {
            $order->view_at = Carbon::now();
        }

        $order->save();
        return $order->load('orderhistories', 'orderinfo');
    }

    public function queryorder(Order $order)
    {
        return $order;
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        return $this->orderService->remove($order);
    }

    public function searchorder(Request $request)
    {
        $query = $request->get('query');
        if (!is_null($query)) {
            return  OrderResource::collection(Order::whereLike('order_no', $query)->orWhere('name', 'LIKE', "%{$query}%")->orWhere('weight', $query)->with('orderhistories', 'orderinfo')->paginate(20));
        }
        return OrderResource::collection(Order::with('orderhistories', 'orderinfo')->where('payment_status', 'paid')->latest()->paginate(20));
    }
    public function searchbydate(Request $request)
    {
        $start = $request->start;
        $end = $request->end;

        return
            OrderResource::collection(Order::whereBetween('created_at', [$start, $end])->with('orderhistories', 'orderinfo')->paginate(20));
    }


    public function searchpendingorder(Request $request)
    {
        $query = $request->get('query');
        if (!is_null($query)) {
            return  OrderResource::collection(Order::whereLike('order_no', $query)->orWhere('name', 'LIKE', "%{$query}%")->orWhere('weight', $query)->where('status', 'pending')->with('orderhistories', 'orderinfo')->paginate(20));
        }
        return OrderResource::collection(Order::with('orderhistories', 'orderinfo')->where('status', 'pending')->where('payment_status', 'paid')->latest()->paginate(20));
    }
    public function searchpendingbydate(Request $request)
    {
        $start = $request->start;
        $end = $request->end;

        return   OrderResource::collection(Order::whereBetween('created_at', [$start, $end])->where('status', 'pending')->with('orderhistories', 'orderinfo')->paginate(20));
    }

    public function searchassignedorder(Request $request)
    {
        $query = $request->get('query');
        if (!is_null($query)) {
            return  OrderResource::collection(Order::whereLike('order_no', $query)->orWhere('name', 'LIKE', "%{$query}%")->orWhere('weight', $query)->where('status', 'assigned')->with('orderhistories', 'orderinfo')->paginate(20));
        }
        return OrderResource::collection(Order::with('orderhistories', 'orderinfo')->where('status', 'assigned')->where('payment_status', 'paid')->latest()->paginate(20));
    }
    public function searchassignedbydate(Request $request)
    {
        $start = $request->start;
        $end = $request->end;
        return   OrderResource::collection(Order::whereBetween('created_at', [$start, $end])->where('status', 'assigned')->with('orderhistories', 'orderinfo')->paginate(20));
    }
}
