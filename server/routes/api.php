<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\Partner\PartnerAuthController;
use App\Http\Controllers\Api\V1\Partner\PartnerOrderController;
use App\Http\Controllers\Api\V1\Partner\PartnerSearchController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — mounted under /api/v1 (see bootstrap/app.php apiPrefix)
|--------------------------------------------------------------------------
|
| Contract mirrors the previous NestJS API. Middleware:
|   auth:sanctum  → a valid bearer token is required
|   admin         → additionally requires role === 'ADMIN'
|
*/

Route::get('health', [HealthController::class, 'show']);

// --- Auth -------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:otp');
    Route::post('refresh-token', [AuthController::class, 'refreshToken']);
});

// --- Public catalog / storefront ------------------------------------------
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);

Route::get('categories', [CategoryController::class, 'index']);

Route::get('coupons/admin', [CouponController::class, 'adminIndex'])->middleware(['auth:sanctum', 'admin']);
Route::get('coupons', [CouponController::class, 'index']);
Route::get('coupons/{code}', [CouponController::class, 'show']);

Route::get('settings/store', [SettingController::class, 'showStore']);
Route::get('settings/pincodes/lookup/{pincode}', [SettingController::class, 'checkPincode']);
Route::get('serviceable-pincodes', [SettingController::class, 'activePincodes']);

// Placeholder integrations (unguarded in the old backend).
Route::post('payments/create', [PaymentController::class, 'create']);
Route::post('payments/webhook', [PaymentController::class, 'webhook']);
Route::post('notifications/send', [NotificationController::class, 'send']);

// --- Authenticated customer ----------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/me', [UserController::class, 'me']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::match(['put', 'patch'], 'users/{id}', [UserController::class, 'update']);

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::put('addresses/{id}', [AddressController::class, 'update']);
    Route::delete('addresses/{id}', [AddressController::class, 'destroy']);

    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::patch('cart/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('cart/items/{id}', [CartController::class, 'removeItem']);

    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
});

// --- Admin --------------------------------------------------------------
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('products', [ProductController::class, 'store']);
    Route::patch('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    Route::post('categories', [CategoryController::class, 'store']);
    Route::patch('categories/{id}', [CategoryController::class, 'update']);
    Route::patch('categories/{id}/status', [CategoryController::class, 'updateStatus']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    Route::post('coupons', [CouponController::class, 'store']);
    Route::patch('coupons/{id}', [CouponController::class, 'update']);
    Route::patch('coupons/{id}/status', [CouponController::class, 'updateStatus']);
    Route::delete('coupons/{id}', [CouponController::class, 'destroy']);

    Route::put('settings/store', [SettingController::class, 'updateStore']);
    Route::patch('settings/store/maintenance-mode', [SettingController::class, 'setMaintenanceMode']);
    Route::patch('settings/store/min-order-value', [SettingController::class, 'setMinOrderValue']);
    Route::get('settings/pincodes', [SettingController::class, 'listPincodes']);
    Route::post('settings/pincodes', [SettingController::class, 'createPincode']);
    Route::patch('settings/pincodes/{id}', [SettingController::class, 'updatePincode']);
    Route::delete('settings/pincodes/{id}', [SettingController::class, 'deletePincode']);

    Route::get('users', [UserController::class, 'index']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    Route::post('uploads', [UploadController::class, 'store']);

    Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);

    Route::get('delivery/partners', [DeliveryController::class, 'partners']);
    Route::post('delivery/assign', [DeliveryController::class, 'assign']);
    Route::post('delivery/status/{id}', [DeliveryController::class, 'updateStatus']);

    Route::get('admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('admin/orders', [AdminController::class, 'orders']);
});

// ---------------------------------------------------------------------------
// Delivery-partner mobile app.
//
// Namespaced under /partner/* — the spec this was built from reused paths
// like `/auth/login` and `/orders`, which already exist above for the
// customer/admin app with a different payload and response shape. Rather
// than overload one URL with two incompatible contracts, the partner app
// gets its own prefix:
//   POST /auth/login               -> POST /partner/login
//   GET  /orders                   -> GET  /partner/orders
//   PATCH /orders/:id/status       -> PATCH /partner/orders/:id/status
//   POST /orders/:id/comments      -> POST /partner/orders/:id/comments
//   GET  /partners/search          -> GET  /partner/search
//   POST /orders/:id/reassign      -> POST /partner/orders/:id/reassign
// ---------------------------------------------------------------------------
Route::prefix('partner')->group(function () {
    Route::post('login', [PartnerAuthController::class, 'login'])->middleware('throttle:partner-login');

    Route::middleware(['auth:sanctum', 'partner'])->group(function () {
        Route::get('orders', [PartnerOrderController::class, 'index']);
        Route::patch('orders/{orderId}/status', [PartnerOrderController::class, 'updateStatus']);
        Route::post('orders/{orderId}/comments', [PartnerOrderController::class, 'addComment']);
        Route::post('orders/{orderId}/reassign', [PartnerOrderController::class, 'reassign']);
        Route::get('search', [PartnerSearchController::class, 'search']);
    });
});
