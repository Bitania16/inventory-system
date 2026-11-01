<?php
use App\Http\Controllers\Api\CrudController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\CashierShiftController;
use App\Http\Controllers\Api\PosCheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PaymentController;



// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (Sanctum token required)
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return response()->json([
            'user'  => $request->user(),
            'roles' => $request->user()->getRoleNames(),
        ]);
    });

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('products', ProductController::class);

    // Product variants
    Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::get('variants/{variant}', [ProductVariantController::class, 'show']);
    Route::put('variants/{variant}', [ProductVariantController::class, 'update']);
    Route::delete('variants/{variant}', [ProductVariantController::class, 'destroy']);

    // Purchasing
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
Route::get('shifts', [CashierShiftController::class, 'index']);
Route::post('shifts/open', [CashierShiftController::class, 'open']);
Route::get('shifts/{shift}', [CashierShiftController::class, 'show']);
Route::post('shifts/{shift}/cash-movements', [CashierShiftController::class, 'cashMovement']);
Route::post('shifts/{shift}/close', [CashierShiftController::class, 'close']);
// POS
Route::post('pos/checkout', [PosCheckoutController::class, 'store']);
Route::get('sales/{sale}', [PosCheckoutController::class, 'show']);
// Customers
Route::get('customers', [CustomerController::class, 'index']);
Route::post('customers', [CustomerController::class, 'store']);
Route::get('customers/{customer}', [CustomerController::class, 'show']);
Route::put('customers/{customer}', [CustomerController::class, 'update']);
Route::get('customers/{customer}/statement', [CustomerController::class, 'statement']);

// Customer payments
Route::post('payments', [PaymentController::class, 'store']);
// Simple CRUD tables (see config/crud.php)
foreach (array_keys(config('crud')) as $slug) {
    Route::get($slug, [CrudController::class, 'index'])->defaults('resource', $slug);
    Route::post($slug, [CrudController::class, 'store'])->defaults('resource', $slug);
    Route::get("$slug/{id}", [CrudController::class, 'show'])->defaults('resource', $slug);
    Route::put("$slug/{id}", [CrudController::class, 'update'])->defaults('resource', $slug);
    Route::delete("$slug/{id}", [CrudController::class, 'destroy'])->defaults('resource', $slug);
}
});