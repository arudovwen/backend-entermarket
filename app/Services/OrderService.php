<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\LgaPrice;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Notifications\OrderCreated;
use App\Http\Controllers\BankDetailController;

class OrderService
{

  public function generateUniqueCode()
  {
    $code = null;
    do {
      $code = random_int(10000000, 99999999);
    } while (Order::where("order_no", "=", $code)->first());

    return $code;
  }


  public function create(
    $user,
    $name,
    $shipping_charges,
    $promo,
    $discount_percent,
    $commission,
    $allAddress,
    $pickup_location,
    $extra_instruction,
    $payment_method,
    $title,
    $delivery_method,
    $tx_ref, $mode
  ) {

    // try {
    return  DB::transaction(function () use (
      $user,
      $name,
      $shipping_charges,
      $promo,
      $discount_percent,
      $commission,
      $allAddress,
      $pickup_location,
      $extra_instruction,
      $payment_method,
      $title,
      $delivery_method,
      $tx_ref, $mode
    ) {
      $cartservice = new CartService;
      $usercart =  $cartservice->getCart($user)['cart'];
      $isScheduled = false;

      if (!count($usercart)) {
        return response()->json(
          [
            'status' => false,
            'message' => 'Empty cart',

          ],
          401
        );
      }
      if ($delivery_method === 'home') {
        $total = ($cartservice->total($user)['total']) * count($allAddress);
        $singleordertotal = $cartservice->total($user)['total'];
        $weight = $cartservice->total($user)['weight'];
        $deliveryFee = collect($allAddress)->map(function ($a) {
          return $a->shipping_fee;
        })->reduce(function ($a, $b) {
          return $a + $b;
        });
        $order_no = $this->generateUniqueCode();
        $discount = $discount_percent * intval($total);
        $grand_total = (intval($total) + intval($deliveryFee)) - $discount;

        $items = $usercart->map(function ($a) {
          return $a['quantity'];
        })->reduce(function ($a, $b) {
          return $a + $b;
        });

        //create orders
        foreach ($allAddress as $address) {

          if ($address->shipping === 'scheduled') {
            $isScheduled = true;
          }


          $order =  $user->orders()->create([
            'order_no' => $order_no,
            'name' => $name,
            'status' => 'pending',
            'sub_total' => $singleordertotal,
            'total_amount' => $singleordertotal,
            'commission' => $commission,
            'tax' => 0,
            'shipping_charges' => $address->shipping_fee,
            'promo' => $promo,
            'discount' => $discount,
            'grand_total' => $grand_total,
            'title' => $title,
            'isScheduled' => $isScheduled,
            'schedule_time' => $address->schedule_time,
            'items' => $items,
            'shipping_method' => $address->shipping,
            'weight' => $weight

          ]);

          $order->orderhistories()->createMany($usercart->toArray());



          $mappedarray = array_map(function ($a) use ($order_no) {
            $a['order_no'] = $order_no;
            return $a;
          }, $usercart->toArray());
          $user->storeorder()->createMany($mappedarray);
          $this->reducequantity($usercart);


          //update order information
          $lga = Lga::find($address->lga);
          $orderinfoAddress = $address->address . ', ' . $lga->lga;

          $order->orderinfo()->create([
            'user_id' => $user->id,
            'firstName' =>  $address->contact_name,
            'lastName' => $address->contact_name,
            'delivery_method' => $delivery_method,
            'shipping_method' => $address->shipping,
            'shipping_address' => $orderinfoAddress,
            '$pickup_location' => $pickup_location,
            'email' =>  $address->contact_email,
            'city' =>  $lga->lga,
            'state' => 'lagos',
            'phoneNumber' =>  $address->phoneNumber,
            'extra_instruction' => $extra_instruction,
            'payment_method' => $payment_method
          ]);


          //update user profile here

          $addresses = $user->address;

          $mappedaddress = collect($addresses)->map(function ($a) {
            return $a['id'];
          });


          if (!in_array($address->id, $mappedaddress->toArray())) {
            array_push($addresses, [
              'id' => $address->id,
              'address' => $address->address,
              'lga' => $address->lga,
              'phoneNumber' => $address->phoneNumber,
              'contact_name' => $address->contact_name,
              'contact_email' => $address->contact_email,
            ]);
            $user->address =  $addresses;
            $user->save();
          }
        }
      } else {
        $total = ($cartservice->total($user)['total']);
        $singleordertotal = $cartservice->total($user)['total'];
        $weight = $cartservice->total($user)['weight'];
        $deliveryFee = 0;

        $discount = $discount_percent * intval($total);
        $grand_total = (intval($total) + intval($deliveryFee)) - $discount;

        $items = $usercart->map(function ($a) {
          return $a['quantity'];
        })->reduce(function ($a, $b) {
          return $a + $b;
        });

        //create orders

        $order_no = $this->generateUniqueCode();
        $order =  $user->orders()->create([
          'order_no' => $order_no,
          'name' => $name,
          'status' => 'pending',
          'sub_total' => $singleordertotal,
          'total_amount' => $singleordertotal,
          'commission' => $commission,
          'tax' => 0,
          'shipping_charges' => 0,
          'promo' => $promo,
          'discount' => $discount,
          'grand_total' => $grand_total,
          'title' => $title,
          'isScheduled' => null,
          'schedule_time' => null,
          'items' => $items,
          'shipping_method' => 'pickup',
          'weight' => $weight

        ]);

        $order->orderhistories()->createMany($usercart->toArray());

        $mappedarray = array_map(function ($a) use ($order_no, $order) {
          $a['order_no'] = $order_no;
          $a['order_id'] = $order->id;
          return $a;
        }, $usercart->toArray());
        $user->storeorder()->createMany($mappedarray);
        $this->reducequantity($usercart);


        //update order information

        $orderinfoAddress =  $pickup_location;

        $order->orderinfo()->create([
          'user_id' => $user->id,
          'firstName' =>  $user->firstName,
          'lastName' => $user->lastName,
          'delivery_method' => $delivery_method,
          'shipping_method' => 'pickup',
          'shipping_address' =>  $pickup_location,
          '$pickup_location' => $pickup_location,
          'email' =>  $user->email,
          'city' =>  'lagos',
          'state' => 'lagos',
          'phoneNumber' => $user->phoneNumber,
          'extra_instruction' => $extra_instruction,
          'payment_method' => $payment_method
        ]);
      }




      $myrequest = new Request();
      $myrequest->setMethod('POST');
      $myrequest->request->add([
        'amount' => $grand_total,
        'email' => $user->email,
        'order_id' => $order->id,
        'tx_ref' =>  $tx_ref,
        'mode' => $mode
      ]);


      $payment  = new BankDetailController();
      $payment_data = $payment->makepayment($myrequest);



      // clear cart
      // $cartservice->clearcart($user);


      return response()->json(
        [
          'status' => true,
          'message' => 'order created',
          'data' => $payment_data,
          'order' => $order
        ],
        201
      );
    });
  }


  public function createweb(
    $user,
    $name,
    $shipping_charges,
    $promo,
    $discount_percent,
    $commission,
    $allAddress,
    $pickup_location,
    $extra_instruction,
    $payment_method,
    $title,
    $delivery_method,
    $coupon,
    $tx_ref, $mode
  ) {


    return  DB::transaction(function () use (
      $user,
      $name,
      $shipping_charges,
      $promo,
      $discount_percent,
      $commission,
      $allAddress,
      $pickup_location,
      $extra_instruction,
      $payment_method,
      $title,
      $delivery_method,
      $tx_ref, $mode

    ) {
      $cartservice = new CartService;
      $usercart =  $cartservice->getCart($user)['cart'];
      $isScheduled = false;

      if (!count($usercart)) {
        return response()->json(
          [
            'status' => false,
            'message' => 'Empty cart',

          ],
          401
        );
      }

      if ($delivery_method === 'home') {
        $total = ($cartservice->total($user)['total']) * count($allAddress);
        $singleordertotal = $cartservice->total($user)['total'];
        $weight = $cartservice->total($user)['weight'];
        $deliveryFee = collect($allAddress)->map(function ($a) {
          return $a['shipping_fee'];
        })->reduce(function ($a, $b) {
          return $a + $b;
        });

        $discount = $discount_percent * intval($total);
        $grand_total = (intval($total) + intval($deliveryFee)) - $discount;

        $items = $usercart->map(function ($a) {
          return $a['quantity'];
        })->reduce(function ($a, $b) {
          return $a + $b;
        });

        //create orders
        foreach ($allAddress as $address) {
          $order_no = $this->generateUniqueCode();
          if ($address['shipping'] === 'scheduled') {
            $isScheduled = true;
          }


          $order =  $user->orders()->create([
            'order_no' => $order_no,
            'name' => $name,
            'status' => 'pending',
            'sub_total' => $singleordertotal,
            'total_amount' => $singleordertotal,
            'commission' => $commission,
            'tax' => 0,
            'shipping_charges' => $address['shipping_fee'],
            'promo' => $promo,
            'discount' => $discount,
            'grand_total' => $grand_total,
            'title' => $title,
            'isScheduled' => $isScheduled,
            'schedule_time' => $address['schedule_time'],
            'items' => $items,
            'shipping_method' => $address['shipping'],
            'weight' => $weight

          ]);

          $order->orderhistories()->createMany($usercart->toArray());



          $mappedarray = array_map(function ($a) use ($order_no, $order) {
            $a['order_no'] = $order_no;
            $a['order_id'] = $order->id;
            return $a;
          }, $usercart->toArray());
          $user->storeorder()->createMany($mappedarray);
          $this->reducequantity($usercart);


          //update order information
          $lga = Lga::find($address['lga']);
          $orderinfoAddress = $address['address'] . ', ' . $lga->lga;

          $order->orderinfo()->create([
            'user_id' => $user->id,
            'firstName' =>  $address['contact_name'],
            'lastName' => $address['contact_name'],
            'delivery_method' => $delivery_method,
            'shipping_method' => $address['shipping'],
            'shipping_address' => $orderinfoAddress,
            '$pickup_location' => $pickup_location,
            'email' =>  $address['contact_email'],
            'city' =>  $lga->lga,
            'state' => 'lagos',
            'phoneNumber' =>  $address['phoneNumber'],
            'extra_instruction' => $extra_instruction,
            'payment_method' => $payment_method
          ]);


          //update user profile here

          $addresses = $user->address;

          $mappedaddress = collect($addresses)->map(function ($a) {
            return $a['id'];
          });


          if (!in_array($address['id'], $mappedaddress->toArray())) {
            array_push($addresses, [
              'id' => $address['id'],
              'address' => $address['address'],
              'lga' => $address['lga'],
              'phoneNumber' => $address['phoneNumber'],
              'contact_name' => $address['contact_name'],
              'contact_email' => $address['contact_email'],
            ]);
            $user->address =  $addresses;
            $user->save();
          }
        }
      } else {
        $total = ($cartservice->total($user)['total']);
        $singleordertotal = $cartservice->total($user)['total'];
        $weight = $cartservice->total($user)['weight'];
        $deliveryFee = 0;

        $discount = $discount_percent * intval($total);
        $grand_total = (intval($total) + intval($deliveryFee)) - $discount;

        $items = $usercart->map(function ($a) {
          return $a['quantity'];
        })->reduce(function ($a, $b) {
          return $a + $b;
        });

        //create orders

        $order_no = $this->generateUniqueCode();
        $order =  $user->orders()->create([
          'order_no' => $order_no,
          'name' => $name,
          'status' => 'pending',
          'sub_total' => $singleordertotal,
          'total_amount' => $singleordertotal,
          'commission' => $commission,
          'tax' => 0,
          'shipping_charges' => 0,
          'promo' => $promo,
          'discount' => $discount,
          'grand_total' => $grand_total,
          'title' => $title,
          'isScheduled' => null,
          'schedule_time' => null,
          'items' => $items,
          'shipping_method' => 'pickup',
          'weight' => $weight

        ]);

        $order->orderhistories()->createMany($usercart->toArray());

        $mappedarray = array_map(function ($a) use ($order_no, $order) {
          $a['order_no'] = $order_no;
          $a['order_id'] = $order->id;
          return $a;
        }, $usercart->toArray());
        $user->storeorder()->createMany($mappedarray);
        $this->reducequantity($usercart);


        //update order information

        $orderinfoAddress =  $pickup_location;

        $order->orderinfo()->create([
          'user_id' => $user->id,
          'firstName' =>  $user->firstName,
          'lastName' => $user->lastName,
          'delivery_method' => $delivery_method,
          'shipping_method' => 'pickup',
          'shipping_address' =>  $pickup_location,
          '$pickup_location' => $pickup_location,
          'email' =>  $user->email,
          'city' =>  'lagos',
          'state' => 'lagos',
          'phoneNumber' => $user->phoneNumber,
          'extra_instruction' => $extra_instruction,
          'payment_method' => $payment_method
        ]);
      }




      $myrequest = new Request();
      $myrequest->setMethod('POST');
         $myrequest->request->add([
        'amount' => $grand_total,
        'email' => $user->email,
        'order_id' => $order->id,
        'tx_ref' =>  $tx_ref,
        'mode' => $mode
      ]);

      $payment  = new BankDetailController();
      $payment_data = $payment->makepayment($myrequest);



      // clear cart
      // $cartservice->clearcart($user);


      return response()->json(
        [
          'status' => true,
          'message' => 'order created',
          // 'data' => $payment_data,
          'order' => $order
        ],
        201
      );
    });
  }

  public function reducequantity($cartitems)
  {

    foreach ($cartitems as $item) {
      $product  = Product::find($item->product_id);
      $product->in_stock = $product->in_stock  - $item->quantity;
      $product->save();
    }
  }

  public function remove($order)
  {
    $order->delete();
    return response()->json(
      [
        'status' => true,
        'message' => 'order deleted'
      ],
      200
    );
  }
}
