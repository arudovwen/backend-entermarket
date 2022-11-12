<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Mail;
use Validator;
use Carbon\Carbon;
use App\Models\Otp;
use App\Models\User;
use App\Mail\OtpReset;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Spatie\Geocoder\Geocoder;
use App\Notifications\NewUser;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{


    protected $user;


    public function __construct()
    {

        $this->middleware("auth:api", ["except" => ["getcoordinates", "login", "register", "show", "postEmail", "updatePassword", "changePasswordByOtp", "createotp"]]);
        $this->user = new User;
    }
    public function register(Request $request)
    {

        try {

            $validator = Validator::make($request->all(), [

                'firstName' => 'required| alpha_num',
                'lastName' => 'required| alpha_num',
                'email' => 'bail|required|unique:users|email:rfc,dns',
                'password' => ['required', Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()],
                'phoneNumber' => 'bail|required|unique:users|min:11|numeric'
            ]);


            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                    'error' => $validator->messages()->toArray()
                ], 422);
            }
            $address = [];
            if ($request->has('address') && $request->filled('address')) {
                array_push($address, $request->address);
            }

            $user = User::create([
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'address' => $address,
                'dob' => $request->dob,
                'gender' => $request->gender,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'phoneNumber' => $request->phoneNumber,
                'profileImage' => $request->root() . '/img/profile.jpeg',
                'password' => Hash::make($request->password)

            ]);
            if ($request->has('cart') && $request->filled('cart')) {
                $this->addtocart($request->cart, $user);
            }

            $detail = [
                'message' => 'Welcome to my EnterMarket',
                'url' => 'https://entermarket.net'
            ];
            $user->notify(new NewUser($detail));

            $responseMessage = "registration successful";

            if ($user) {
                return $this->autologin($request);
            }

            return response()->json([
                'success' => true,
                'message' => $responseMessage
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'something went wrong',
                'error' => $th

            ], 400);
        }
    }
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|email:rfc,dns',
            'password' => 'required',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->toArray()
            ], 500);
        }
        $data =  [
            'response' => $request->token,
            'secret' => config('services.captcha.cap'),
        ];

        $url = "https://www.google.com/recaptcha/api/siteverify?secret=6LfOUuciAAAAAGsU942Cx6BqIYSx2eqdD9BkdIQw&response=" . $request->token;
        $response =  Http::post($url, $data);
        if ($response["success"] == false) {

            return response()->json([
                'success' => false,
                'message' => "Captcha verification failed"
            ], 500);
        }
        $credentials = $request->only(["email", "password"]);
        $user = User::where('email', $credentials['email'])->first();
        if ($user) {
            if (!auth()->attempt($credentials)) {
                $responseMessage = "invalid credentials";

                return response()->json([
                    "success" => false,
                    "message" => $responseMessage,
                    "error" => $responseMessage
                ], 422);
            }

            $accessToken = auth()->user()->createToken('authToken')->accessToken;
            $responseMessage = "login successful";
            $data = [
                'email' => 'entermarket2021@gmail.com',
                'password' => 'almond.2',
            ];
            if ($request->has('cart') && $request->filled('cart')) {
                if (count($request->cart)) {
                    $this->addtocart($request->cart, $user);
                }
            }

            //   $response =  Http::post('https://apis.payviame.com/api/auth/login', $data);
            //   if($response->json()['status']== 'error') return response()->json([
            //         "success" => false,
            //         "message" => 'token error',

            //     ], 422);
            // $payviame_token = $response->json()['access_token'];
            $payviame_token = 'token';

            return $this->respondWithToken($accessToken, $payviame_token, $responseMessage, auth()->user());
        } else {
            $responseMessage = "invalid credentials";
            return response()->json([
                "success" => false,
                "message" => $responseMessage,
                "error" => $responseMessage
            ], 422);
        }
    }
    public function autologin(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|email:rfc,dns',
            'password' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->messages()->toArray()
            ], 500);
        }

        $credentials = $request->only(["email", "password"]);
        $user = User::where('email', $credentials['email'])->first();
        if ($user) {
            if (!auth()->attempt($credentials)) {
                $responseMessage = "invalid credentials";

                return response()->json([
                    "success" => false,
                    "message" => $responseMessage,
                    "error" => $responseMessage
                ], 422);
            }

            $accessToken = auth()->user()->createToken('authToken')->accessToken;
            $responseMessage = "login successful";
            $data = [
                'email' => 'entermarket2021@gmail.com',
                'password' => 'almond.2',
            ];
            if ($request->has('cart') && $request->filled('cart')) {
                if (count($request->cart)) {
                    $this->addtocart($request->cart, $user);
                }
            }

            //   $response =  Http::post('https://apis.payviame.com/api/auth/login', $data);
            //   if($response->json()['status']== 'error') return response()->json([
            //         "success" => false,
            //         "message" => 'token error',

            //     ], 422);
            // $payviame_token = $response->json()['access_token'];
            $payviame_token = 'token';

            return $this->respondWithToken($accessToken, $payviame_token, $responseMessage, auth()->user());
        } else {
            $responseMessage = "invalid credentials";
            return response()->json([
                "success" => false,
                "message" => $responseMessage,
                "error" => $responseMessage
            ], 422);
        }
    }

    public function addtocart($carts, $user)
    {

        $user->cart()->delete();
        foreach ($carts as $value) {
            $user->cart()->create([
                'store_name' => $value['store_name'],
                'product_name' => $value['product_name'],
                'brand_name' => $value['store_name'],
                'price' => $value['price'],
                'quantity' => $value['quantity'],
                'description' => $value['description'],
                'image' => $value['image'],
                'store_id' => $value['store_id'],
                'product_id' => $value['product_id'],
                'weight' => $value['weight']
            ]);
        }
    }

    public function getcoordinates(Request $request)
    {

        $client = new \GuzzleHttp\Client();
        $geocoder = new Geocoder($client);
        $geocoder->setApiKey(config('geocoder.key'));
        // $geocoder->setCountry(config('geocoder.country', 'Nigeria'));
        $response = $geocoder->getCoordinatesForAddress($request->address);
        $lat = $response['lat'];
        $long = $response['lng'];
        $formatted_address = $response['formatted_address'];
        $place = $response['address_components'][2]->long_name;


        return [
            'lat' => $lat,
            'long' => $long,
            'address' => $formatted_address
        ];
    }
    public function getpayviametoken()
    {
        $data = [
            'email' => 'entermarket2021@gmail.com',
            'password' => 'almond.2',
        ];

        $response =  Http::post('https://apis.payviame.com/api/auth/login', $data);
        $payviame_token = $response->json()['access_token'];
        return $payviame_token;
    }

    public function show(User $user)
    {
        return $user;
    }

    public function postEmail(Request $request)
    {
        $request->validate([
            'email' => 'bail|required|email:rfc,dns',

        ]);



        $token = Str::random(40);

        DB::table('password_resets')->insert(
            ['email' => $request->email, 'token' => $token, 'created_at' => Carbon::now()]
        );


        $credentials = $request->only(["email"]);
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {

            $responseMessage = "email error";

            return response()->json([
                "success" => false,
                "message" => $responseMessage,
                "error" => $responseMessage
            ], 422);
        }


        $maildata = [
            'title' => 'Password Reset',
            'url' => 'https://entermarket.net/reset-password/?token=' . $token . '&action=password_reset'
        ];

        Mail::to($credentials['email'])->send(new PasswordResetMail($maildata));
        return response()->json([
            "success" => true,
            "message" => 'email sent',

        ], 200);
    }
    public function updatePassword(Request $request)
    {


        $request->validate([
            'password' => ['required', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()],
            'confirmPassword' =>  'required',

        ]);

        $updatePassword = DB::table('password_resets')
            ->where(['token' => $request->token])
            ->first();

        if (!$updatePassword) {
            return response()->json([
                "success" => false,
                "message" => 'Invalid request'

            ], 200);
        }


        $user = User::where('email', $updatePassword->email)
            ->update(['password' => Hash::make($request->password)]);


        DB::table('password_resets')->where(['token' => $request->token])->delete();

        return response()->json([
            "success" => true,
            "message" => 'Your password has been changed'

        ], 200);
    }

    public function viewProfile()
    {
        $responseMessage = "user profile";
        $data = Auth::guard("api")->user();
        return response()->json([
            "success" => true,
            "message" => $responseMessage,
            "data" => $data
        ], 200);
    }

    public function createotp(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
        ]);

        $user =  User::where('email', $request->email)->first();

        if (is_null($user)) {

            return response([
                'status' => false,
                'message' => 'Email not found'
            ], 500);
        }
        $code = mt_rand(100000, 999999);

        $otp = Otp::updateOrCreate(
            ['user_id' => $user->id],
            ['code' => $code]
        );
        $otp->save();
        $maildata = [
            'code' => $code
        ];


        Mail::to($user)->send(new OtpReset($maildata));
        return response()->json([
            "success" => true,
            "message" => 'otp sent to email'

        ], 200);
    }

    public function changePasswordByOtp(Request $request)
    {
        $request->validate([
            'code' => 'required|min:6|max:6',
            'password' => 'required|string|min:6',
            'confirmpassword' => 'required',
        ]);
        $user_id  = Otp::where('code', $request->code)->value('user_id');

        if (!$user_id) {
            return response()->json([
                "success" => false,
                "message" => 'Invalid code'

            ], 200);
        }

        $user = User::find($user_id);
        $oldpassword = $user->password;
        $checkpassword = Hash::check($request->password, $oldpassword);
        if ($checkpassword) {
            return response()->json([
                "success" => false,
                "message" => 'identical password'

            ], 200);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        Otp::where('code', $request->code)->first()->delete();

        return response()->json('Password changed');
    }

    public function update(User $user, Request $request)
    {
        $user = auth('api')->user();

        if ($request->has('firstName') && $request->filled('firstName') && !is_null($request->input('firstName'))) {
            $user->firstName = $request->firstName;
        }
        if ($request->has('surname') && $request->filled('surname') && !is_null($request->input('surname'))) {
            $user->lastName = $request->surname;
        }
        if ($request->has('email') && $request->filled('email') && !is_null($request->input('email'))) {
            $user->email = $request->email;
        }

        if ($request->has('address') && $request->filled('address') && !is_null($request->input('address'))) {
            $user->address  = $request->address;
        }
        if ($request->has('dob') && $request->filled('dob') && !is_null($request->input('dob'))) {
            $user->dob =  $request->dob;
        }
        if ($request->has('gender') && $request->filled('gender') && !is_null($request->input('gender'))) {
            $user->gender = $request->gender;
        }
        if ($request->has('city') && $request->filled('city') && !is_null($request->input('city'))) {
            $user->city = $request->city;
        }
        if ($request->has('state') && $request->filled('state') && !is_null($request->input('state'))) {
            $user->state = $request->state;
        }

        if ($request->has('country') && $request->filled('country') && !is_null($request->input('country'))) {

            $user->country  = $request->country;
        }
        if ($request->has('phone') && $request->filled('phone') && !is_null($request->input('phone'))) {
            $user->phoneNumber = $request->phone;
        }
        if ($request->has('profileImage') && $request->filled('profileImage') && !is_null($request->input('profileImage'))) {
            $user->profileImage = $request->profileImage;
        }


        $user->save();
        return $user;
    }
    public function logout()
    {

        //auth()->user()->logout();

        $user = Auth::guard("api")->user()->token();
        $user->revoke();
        $responseMessage = "successfully logged out";
        return response()->json([
            'success' => true,
            'message' => $responseMessage
        ], 200);
    }

    public function changepassword(Request $request)
    {
        $request->validate([
            'oldpassword' => 'required',
            'newpassword' => 'required',
        ]);

        $user = auth('api')->user();
        $oldpassword = $user->password;
        $checkpassword = Hash::check($request->oldpassword, $oldpassword);
        if (!$checkpassword) {
            return response()->json([
                "success" => false,
                "message" => 'incorrect old password'

            ], 401);
        }

        if (Hash::check($request->newpassword, $oldpassword)) {
            return response()->json([
                "success" => false,
                "message" => 'New password cannot be old password'

            ], 401);
        }


        $user->password = Hash::make($request->newpassword);
        $user->save();
        return response()->json([
            "success" => true,
            "message" => 'password changed'

        ], 200);
    }

    public function changepin(Request $request)
    {
        $request->validate([
            'oldpin' => 'required|max:4|min:4',
            'newpin' => 'required|max:4|min:4',
        ]);

        $user = auth('api')->user();
        $oldpin = $user->pin;
        $checkpin = Hash::check($request->oldpin, $oldpin);
        if (!$checkpin) {
            return response()->json([
                "success" => false,
                "message" => 'incorrect old pin'

            ], 401);
        }

        if (Hash::check($request->newpin, $oldpin)) {
            return response()->json([
                "success" => false,
                "message" => 'New pin cannot be old pin'

            ], 401);
        }

        $user->pin = Hash::make($request->newpin);
        $user->save();
        return response()->json([
            "success" => true,
            "message" => 'pin changed'

        ], 200);
    }

    public function createpin(Request $request)
    {
        $request->validate([
            'newpin' => 'required|max:4|min:4',

        ]);
        $user = auth('api')->user();



        $user->pin = Hash::make($request->newpin);
        $user->save();
        return response()->json([
            "success" => true,
            "message" => 'pin created'

        ], 200);
    }

    public function storeUploads(Request $request)
    {

        $user = auth('api')->user();
        $user->profileImage = $request->profileImage;
        $user->save();
        return $user;
    }

    public function destroy(User $user)
    {

        $user->delete();
        return response('user deleted');
    }
}
