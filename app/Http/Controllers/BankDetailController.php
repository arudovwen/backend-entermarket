<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreOrder;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use App\Notifications\OrderCreated;
use App\Notifications\NewOrderAlert;
use Illuminate\Support\Facades\Http;
use App\Events\TransactionSuccessful;

class BankDetailController extends Controller
{
    public $user;
    public $api_key;
    public $pin;
    public function __construct()
    {
        $this->user = auth('api')->user();
        $this->api_key = config('services.paystack.sk');
        $this->pin = 4160;
    }


    public function store($request)
    {
        $request->validate(
            [
                'name' => 'required',
                'bank_name' => 'required',
                'account_no' => 'required',
                'bank_code' => 'required'

            ]
        );

        return   DB::transaction(function () use ($request) {
            $accountverification =  $this->verifyaccountnumber($request->account_no, $request->bank_code);
            if (!$accountverification) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot verify account details'
                ]);
            }

            $bank_name = $request->bank_name;
            $account_no = $request->account_no;
            $bank_code = $request->bank_code;

            $accountdetail = $this->user->accountdetail()->create([
                'account_no' => $account_no,
                'bank_name' => $bank_name,
                'bank_code' => $bank_code,

            ]);
        });
    }

    public function verifyaccountnumber($account_no, $bank_code)
    {

        $verify = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->api_key,

        ])->get(
            'https://api.paystack.co/bank/resolve',
            [
                'account_number' => $account_no,
                'bank_code' => $bank_code,

            ]
        );
        return $verify->json()['status'];
    }

    public function getbanks()
    {

        $response = Http::get('https://api.paystack.co/bank?coutry=nigeria');
        return  $bankdata = $response->json()['data'];
    }
    public function getbankdetail()
    {
        return $this->user->accountdetail()->first();
    }
    public function makepayment(Request $request)
    {


        return DB::transaction(function () use ($request) {


            $email = $this->user->email;
            $amount = $request['amount'] * 100;
            $order_id = $request['order_id'];
            $order_no = Order::find($order_id)->order_no;


            $body = [
                'email' => $email,
                'amount' => $amount,

            ];
            if ($request->mode == 'paystack') {
                $response =  Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->api_key,
                ])->post(
                    'https://api.paystack.co/transaction/initialize',
                    $body
                );
                $responsedata = $response->json()['data'];

                $result =   $this->user->transactions()->create([
                    'reference' => $responsedata['reference'],
                    'message' => 'pending',
                    'status' => 'pending',
                    'trxref' =>  $responsedata['access_code'],
                    'redirecturl' =>  $responsedata['authorization_url'],
                    'order_id' => $order_no,
                    'amount' => $amount,
                    'mode' => $request->mode,
                    'type' => 'online'

                ]);

                return $responsedata;
            }
            if ($request->mode == 'flutterwave') {
                return   $result =   $this->user->transactions()->create([
                    'reference' => $request['tx_ref'],
                    'message' => 'pending',
                    'status' => 'pending',
                    'trxref' =>  $request['tx_ref'],
                    'redirecturl' =>  'url',
                    'order_id' => $order_no,
                    'amount' => $amount,
                    'mode' => $request->mode,
                    'type' => 'online'

                ]);
            }
        });
    }

    public function verifytransaction($reference)
    {

        return  DB::transaction(function () use ($reference) {

            $transaction = Transaction::where('reference', $reference)->first();
            if ($transaction->mode == 'flutterwave') {
                $response =  Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('services.flutter.sk'),
                ])->get(
                    'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . $reference
                );

                if ($response['data']['status'] !== 'successful') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid reference'
                    ]);
                }
            }

            $payment = Payment::where('reference', $reference)->first();
            $initialorder = Order::where('order_no', $transaction->order_id)->first();


            if ($transaction) {
                if (strtolower($transaction->message) === 'verification successful') {
                    return response()->json([
                        'status' => true,
                        'message' => 'Verification successful',
                        'data' =>  Order::with('orderinfo', 'orderhistories')->where('name', $initialorder->name)->get(),
                        'type' => 'order'
                    ]);
                }
                $transaction->message = 'verification successful';
                $transaction->status = 'success';
                $transaction->save();

                //   if ($response->json()['status'] == 'success') {
                $orders = Order::with('orderinfo', 'orderhistories')->where('name', $initialorder->name)->get();
                $firstorder = $orders[0];
                foreach ($orders as $order) {
                    $order->payment_status = 'paid';
                    $order->save();
                }

                $StoreOrders = StoreOrder::where('name', $firstorder->name)->get();
                foreach ($StoreOrders as $StoreOrder) {
                    $StoreOrder->payment_status = 'paid';
                    $StoreOrder->save();
                }


                $cartservice = new CartService;
                $user = User::find($firstorder->user_id);
                $cartservice->clearcart($user);
                $detail = [
                    'message' => 'Your order with order number #' . $firstorder->order_no . ' has been created and is being processed',
                    'url' => 'https://entermarket.net/profile?showing=4'
                ];

                $details = [
                    'message' => 'There is a new pending order with order number #' . $firstorder->order_no,
                    'url' => 'https://admin12xx.entermarket.net/orders/pending'
                ];
                $admin = Admin::find(1);

                $user->notify(new OrderCreated($detail));
                $admin->notify(new NewOrderAlert($details));
                // } else {
                //     $order = Order::find($transaction->order_id);
                //     $order->payment_status = 'failed';
                //     $order->save();
                //     StoreOrder::where('order_no', $order->order_no)->update(['payment_status' => 'failed']);
                // }

                return response()->json([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => $orders,
                    'type' => 'order'
                ]);
            } else if ($payment) {


                if ($payment->status === 'pending') {


                    if ($payment->type === 'airtime') {
                        $body = [
                            'network' => strtoupper($payment->network),
                            'amount' => $payment->amount * 100,
                            'mobile_number' => $payment->number,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-airtime',
                            $body
                        );

                        if ($response->status() !== 200 && $response->status() !== 201 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'data') {
                        $body = [
                            'paymentCode' => $payment->paymentCode,
                            'amount' => $payment->amount * 100,
                            'mobile_number' => $payment->number,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-data-2',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'electricity') {
                        $body = [
                            'customerid' => $payment->customerid,
                            'amount' => $payment->amount * 100,
                            'paymentCode' => $payment->paymentCode,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-electricity',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'internet') {
                        $body = [
                            'customerid' => $payment->customerid,
                            'amount' => $payment->amount * 100,
                            'paymentCode' => $payment->paymentCode,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-internet',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'cable') {

                        $body = [
                            'customerid' => $payment->customerid,
                            'amount' => $payment->amount * 100,
                            'paymentCode' => $payment->paymentCode,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-tv',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'betting') {
                        $body = [
                            'customerid' => $payment->customerid,
                            'amount' => $payment->amount * 100,
                            'paymentCode' => $payment->paymentCode,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/bet',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'software') {
                        $body = [
                            'network' => strtoupper($payment->network),
                            'amount' => $payment->amount * 100,
                            'mobile_number' => $payment->number,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-data',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                    if ($payment->type === 'education') {
                        $body = [
                            'network' => strtoupper($payment->network),
                            'amount' => $payment->amount * 100,
                            'mobile_number' => $payment->number,
                            'pin' => $this->pin
                        ];

                        $response =  Http::withHeaders([
                            'Authorization' => 'Bearer ' . $payment->token,
                        ])->post(
                            'https://apis.payviame.com/api/buy-data',
                            $body
                        );
                        if ($response->status() !== 200 && $response->status() !== 201) {
                            return response(['message' => $response['message']], 500);
                        }
                        $responsedata = $response->json();
                        if ($responsedata && $responsedata['status'] === 'success') {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        } else {
                            $payment->status = $response->json()['status'];
                            $payment->message = $response->json()['message'];
                            $payment->transactionRef = $responsedata['transactionRef'];
                            $payment->save();
                        }
                    }
                } else {
                    return response()->json([
                        'status' => true,
                        'message' => $payment->status,
                        'data' => $payment,
                        'type' => 'payment',

                    ]);
                }
                $newToken = $this->refreshtoken($payment->token);
                return response()->json([
                    'status' => true,
                    'message' => 'Verification successful',
                    'new_token' => $newToken,
                    'data' => $payment,
                    'type' => 'payment',
                    'response' => $responsedata
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid reference'
                ]);
            }
        });
    }

    public function refreshtoken($token)
    {

        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->post('https://apis.payviame.com/api/auth/refresh');
        if ($response->status() !== 200 && $response->status() !== 201) {
            return response(['message' => $response['message']], 500);
        }
        return  $responsedata = $response->json()['access_token'];
    }

    public function transactionevent(Request $request)
    {
        return  DB::transaction(function () use ($request) {

            if ($request->event == 'charge.success') {

                $transaction = Transaction::where('reference', $request->reference)->first();
                if (!$transaction) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid reference'
                    ]);
                }
                $transaction->message = $request->gateway_response;
                $transaction->status = $request->status;
                $transaction->save();

                if ($request->status == 'success') {
                    $order = Order::find($transaction->order_id);
                    $order->payment_status = 'paid';
                    $order->save();
                } else {
                    $order = Order::find($transaction->order_id);
                    $order->payment_status = 'failed';
                    $order->save();
                }



                $result = [
                    'status' => true,
                    'message' => 'Transaction successful',

                ];
                broadcast(new TransactionSuccessful($result));
                return $result;
            }

            $result = [
                'status' => false,
                'message' => 'Transaction failed'
            ];
            broadcast(new TransactionSuccessful($result));
            return $result;
        });
    }

    public function paybypayviame(Request $request)
    {


        return DB::transaction(function () use ($request) {
            $payment = $this->user->payments()->create([
                'type' => $request->type,
                'amount' => $request->amount,
                'service' => $request->service,
                'network' => $request->network,
                'number' => $request->number,
                'service_id' => $request->service_id,
                'status' => 'pending',
                'token' => $request->token,
                'paymentCode' => $request->paymentCode,
                'customerid' => $request->customerid

            ]);

            $email = $this->user->email;
            $amount = $request['amount'] * 100;

            $body = [
                'email' => $email,
                'amount' => $amount,

            ];
            $response =  Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->api_key,
            ])->post(
                'https://api.paystack.co/transaction/initialize',
                $body
            );
            $responsedata = $response->json()['data'];
            $payment->reference =  $responsedata['reference'];
            $payment->save();

            return $responsedata;
        });
    }
    public function paybypayviame1(Request $request)
    {


        return DB::transaction(function () use ($request) {
            $payment = $this->user->payments()->create([
                'type' => $request->type,
                'amount' => $request->amount,
                'service' => $request->service,
                'network' => $request->network,
                'number' => $request->number,
                'service_id' => $request->service_id,
                'status' => 'pending',
                'token' => $request->token,
                'paymentCode' => $request->paymentCode,
                'customerid' => $request->customerid

            ]);

            $payment->reference = $request->reference;
            $payment->save();
            return   $this->verifytransaction($request->reference);
        });
    }
}
