<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LgaPriceController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\BankDetailController;
use App\Http\Controllers\LogisticController;
use App\Http\Controllers\StoreOrderController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderHistoryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept,charset,boundary,Content-Length');
header('Access-Control-Allow-Origin: *');

// User Routes
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('user/payviame/token', [UserController::class, 'getpayviametoken']);

Route::middleware('auth:admin_api')->group(function () {
    Route::get('admin/get/orders', [OrderController::class, 'adminindex']);
    Route::get('admin/get/assigned/orders', [OrderController::class, 'adminordersassigned']);
    Route::get('admin/get/pending/orders', [OrderController::class, 'adminorderspending']);
    Route::get('admin/get/completed/orders', [OrderController::class, 'adminorderscompleted']);
    Route::get('admin/get/failed/orders', [OrderController::class, 'adminordersfailed']);
    // Route::put('admin/update/order/status/{order}', [OrderController::class, 'updateorderstatus']);
    Route::put('admin/update/order/status/{order}', [OrderController::class, 'assignlogistic']);
    Route::get('queryorder/{order}', [OrderController::class, 'queryorder']);


    //Admin Notification
    Route::get('admin/notifications', [NotificationController::class, 'admingetnotifications']);
    Route::get('admin/notifications/unread', [NotificationController::class, 'adminunreadnotifications']);
    Route::get('admin/notifications/mark', [NotificationController::class, 'adminmarkreadnotifications']);
    Route::get('admin/notifications/{id}/mark', [NotificationController::class, 'adminmarksinglenotification']);
    Route::delete('admin/notifications/delete', [NotificationController::class, 'admindestroy']);
});

Route::apiResource('categories', CategoryController::class);
Route::apiResource('brands', BrandController::class);


Route::middleware(['auth:api'])->group(function () {


    Route::get('get-coupon/{code}', [CouponController::class, 'show']);


    // Orders
    Route::apiResource('user/order/histories', OrderHistoryController::class);
    Route::apiResource('user/orders', OrderController::class);
    Route::post('web-user/orders', [OrderController::class, 'webstore']);
    Route::get('cancel-order/{ref}', [OrderController::class, 'cancelOrder']);



    // Cart
    Route::apiResource('user/cart', CartController::class);
    Route::get('get/total', [CartController::class, 'gettotal']);
    Route::get('user/clear/cart', [CartController::class, 'destroyall']);

    // Profile
    Route::get('user/profile', [UserController::class, 'viewProfile'])->name('profile.user');
    Route::post('user/profile/update', [UserController::class, 'update']);


    // Notifications
    Route::get('user/notifications', [NotificationController::class, 'getnotifications']);
    Route::get('user/notifications/unread', [NotificationController::class, 'unreadnotifications']);
    Route::get('user/notifications/mark', [NotificationController::class, 'markreadnotifications']);
    Route::get('user/notifications/{id}/mark', [NotificationController::class, 'marksinglenotification']);
    Route::delete('user/notifications/delete', [NotificationController::class, 'destroy']);





    // Pin and password
    Route::post('change/pin', [UserController::class, 'changepin']);
    Route::post('create/pin', [UserController::class, 'createpin']);
    Route::post('change/password', [UserController::class, 'changepassword']);

    // Reports
    Route::apiResource('reports', ReportController::class);

    // Transactions
    Route::apiResource('user/transactions', TransactionController::class);
    Route::post('transaction/initiate', [BankDetailController::class, 'makepayment']);
    Route::post('transaction/verify', [BankDetailController::class, 'transactionevent']);
    Route::post('payviame/payment', [BankDetailController::class, 'paybypayviame']);
    Route::post('handle/payment', [BankDetailController::class, 'paybypayviame1']);
    Route::get('refresh/token/{token}', [BankDetailController::class, 'refreshtoken']);
    Route::get('add-wishlist/{wishlist}', [WishlistController::class, 'addlisttocart']);
    Route::post('wishlist/item', [WishlistController::class, 'storeitem']);
});
Route::get('transaction/verify/{reference}', [BankDetailController::class, 'verifytransaction']);

//Store routes
Route::get('store/categories/{store}', [StoreController::class, 'getstorecategories']);
Route::post('store/login', [StoreController::class, 'login']);
Route::post('get/stores', [StoreController::class, 'getallstores']);
Route::post('search/stores', [StoreController::class, 'searchstores']);
Route::post('search/site', [StoreController::class, 'searchsite']);
Route::middleware('auth:store_api')->post('store/update', [StoreController::class, 'update']);
Route::middleware('auth:store_api')->get('store/report', [StoreOrderController::class, 'gettotals']);
Route::middleware('auth:store_api')->get('store/earnings', [StoreOrderController::class, 'getearnings']);
Route::middleware('auth:store_api')->get('top/earnings', [StoreOrderController::class, 'gettopearner']);
Route::middleware('auth:store_api')->get('store/get/products', [StoreController::class, 'storegetproducts']);
Route::middleware('auth:store_api')->apiResource('storeorders', StoreOrderController::class);

Route::middleware('auth:store_api')->get('store/get/order/{status}', [StoreOrderController::class, 'storegetorderbystatus']);


Route::apiResource('stores', StoreController::class);
Route::get('get-stores', [StoreController::class, 'getstores']);

// Products

Route::post('store/products', [ProductController::class, 'storeproducts']);
Route::post('bulk/upload', [ProductController::class, 'bulkupload']);
Route::post('store/products/all', [ProductController::class, 'allstoreproducts']);
Route::post('search/products', [ProductController::class, 'searchproducts']);
Route::get('similar/products/{id}', [ProductController::class, 'getsimilarproducts']);
Route::middleware('auth:store_api')->post('product/add', [ProductController::class, 'store']);
Route::apiResource('products', ProductController::class);
Route::post('bulkupload', [ProductController::class, 'uploadproducts']);
//Wishlist routes
Route::post('clear/wishlists', [WishlistController::class, 'destroyall']);

Route::delete('wishlist/item/{wishlistitem}', [WishlistController::class, 'destroyitem']);
Route::apiResource('wishlists', WishlistController::class);

//Story routes
Route::apiResource('stories', StoryController::class);
Route::get('removestories', [StoryController::class, 'remove']);


// Auth routes

Route::post('users/register', [UserController::class, 'register']);
Route::post('users/login', [UserController::class, 'login'])->name('login');
Route::post('mobile/login', [UserController::class, 'autologin'])->name('autologin');
Route::get('users/logout', [UserController::class, 'logout'])->name('logout.user');
Route::post('forgot-password', [UserController::class, 'postEmail']);
Route::post('update-password', [UserController::class, 'updatePassword']);
Route::post('user/image', [UserController::class, 'storeUploads']);
Route::delete('user/delete/{user}', [UserController::class, 'destroy']);
Route::apiResource('otp', OtpController::class);


Route::apiResource('users', UserController::class);

Route::post('vendor/register', [VendorController::class, 'register']);
// Route::post('admin/register', [AdminController::class, 'register']);
Route::post('admin/login', [AdminController::class, 'login']);

// Vendor Routes
Route::middleware('auth:vendor')->get('/vendor', function (Request $request) {
    return $request->user();
});


Route::middleware(['auth:vendor'])->group(function () {
});


// Admin routes
Route::middleware('auth:admin')->get('/admin', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:admin'])->group(function () {


    Route::get('get-active', [CouponController::class, 'getactive']);
    Route::get('get-pending', [CouponController::class, 'getpending']);
    Route::get('get-expired', [CouponController::class, 'getexpired']);


    Route::apiResource('coupons', CouponController::class);
});
Route::put('change/store-status/{id}', [StoreController::class, 'changestatus']);
//Admin
Route::get('search/order', [OrderController::class, 'searchorder']);
Route::post('search/order-by-date', [OrderController::class, 'searchbydate']);
Route::get('search/pending/order', [OrderController::class, 'searchpendingorder']);
Route::post('search/pending/order-by-date', [OrderController::class, 'searchpendingbydate']);
Route::get('search/assigned/order', [OrderController::class, 'searchassignedorder']);
Route::post('search/assigned/order-by-date', [OrderController::class, 'searchassignedbydate']);
Route::get('search/failed/order', [OrderController::class, 'searchfailedorder']);
Route::post('search/failed/order-by-date', [OrderController::class, 'searchfailedbydate']);

Route::get('search/completed/order', [OrderController::class, 'searchcompletedorder']);
Route::post('search/completed/order-by-date', [OrderController::class, 'searchcompletedbydate']);




// Social Login routes
Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect']);
Route::post('/auth/{provider}/callback', [SocialLoginController::class, 'callback']);


// Mobile Password
Route::post('generate/otp', [UserController::class, 'createotp']);
Route::post('password/reset', [UserController::class, 'changePasswordByOtp']);


//Bank Details

Route::get('get/banks', [BankDetailController::class, 'getbanks']);
Route::get('get/bank/detail', [BankDetailController::class, 'getbankdetail']);
Route::apiResource('bank/details', BankDetailController::class);
Route::post('get/coordinates', [UserController::class, 'getcoordinates']);


Route::get('get-lgas', [LgaPriceController::class, 'index']);
Route::post('add-lga', [LgaPriceController::class, 'addlga']);
Route::delete('delete-lga/{lga}', [LgaPriceController::class, 'deletelga']);
Route::put('update-lga/{lga}', [LgaPriceController::class, 'updatelga']);


Route::get('get-lga-prices/{id}', [LgaPriceController::class, 'getlgaprices']);
Route::get('get-lga-price/{id}/{storeId}', [LgaPriceController::class, 'getlgaprice']);

Route::apiResource('lga-prices', LgaPriceController::class);
Route::get('mark-order-complete/{id}', [StoreController::class, 'markorder']);
Route::get('mark-order-failed/{id}', [StoreController::class, 'markorderfailed']);

Route::apiResource('logistics', LogisticController::class);
