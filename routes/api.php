<?php

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
use App\Http\Controllers\Api\CrudController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\SaleReturnController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\OnlineOrderController;
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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

    // Cashier shifts
    Route::get('shifts', [CashierShiftController::class, 'index']);
    Route::post('shifts/open', [CashierShiftController::class, 'open']);
    Route::get('shifts/{shift}', [CashierShiftController::class, 'show']);
    Route::post('shifts/{shift}/cash-movements', [CashierShiftController::class, 'cashMovement']);
    Route::post('shifts/{shift}/close', [CashierShiftController::class, 'close']);

    // POS
    Route::post('pos/checkout', [PosCheckoutController::class, 'store']);
    Route::get('sales/{sale}', [PosCheckoutController::class, 'show']);

    // Customers and payments
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::put('customers/{customer}', [CustomerController::class, 'update']);
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement']);
    Route::post('payments', [PaymentController::class, 'store']);

    // Stock
    Route::get('stock/balances', [StockController::class, 'balances']);
    Route::get('stock/transactions', [StockController::class, 'transactions']);

    Route::get('stock/adjustments', [StockAdjustmentController::class, 'adjustments']);
    Route::post('stock/adjustments', [StockAdjustmentController::class, 'createAdjustment']);
    Route::post('stock/adjustments/{adjustment}/post', [StockAdjustmentController::class, 'postAdjustment']);

    Route::get('stock/transfers', [StockAdjustmentController::class, 'transfers']);
    Route::post('stock/transfers', [StockAdjustmentController::class, 'createTransfer']);
    Route::post('stock/transfers/{transfer}/ship', [StockAdjustmentController::class, 'shipTransfer']);
    Route::post('stock/transfers/{transfer}/receive', [StockAdjustmentController::class, 'receiveTransfer']);

    // Simple CRUD tables (see config/crud.php)
    foreach (array_keys(config('crud')) as $slug) {
        Route::get($slug, [CrudController::class, 'index'])->defaults('resource', $slug);
        Route::post($slug, [CrudController::class, 'store'])->defaults('resource', $slug);
        Route::get("$slug/{id}", [CrudController::class, 'show'])->defaults('resource', $slug);
        Route::put("$slug/{id}", [CrudController::class, 'update'])->defaults('resource', $slug);
        Route::delete("$slug/{id}", [CrudController::class, 'destroy'])->defaults('resource', $slug);
    }
        // Returns and refunds
    Route::get('returns', [SaleReturnController::class, 'index']);
    Route::post('returns', [SaleReturnController::class, 'store']);
    Route::get('returns/{return}', [SaleReturnController::class, 'show']);
    Route::post('returns/{return}/approve', [SaleReturnController::class, 'approve']);
    Route::post('returns/{return}/reject', [SaleReturnController::class, 'reject']);
    Route::post('returns/{return}/receive', [SaleReturnController::class, 'receive']);
    Route::post('returns/{return}/refund', [SaleReturnController::class, 'refund']);
    // Supplier payments
    Route::get('supplier-payments', [SupplierPaymentController::class, 'index']);
    Route::post('supplier-payments', [SupplierPaymentController::class, 'store']);
    Route::get('supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show']);
   
       // Online orders and shipments
    Route::get('orders', [OnlineOrderController::class, 'index']);
    Route::post('orders', [OnlineOrderController::class, 'store']);
    Route::get('orders/{order}', [OnlineOrderController::class, 'show']);
    Route::post('orders/{order}/confirm', [OnlineOrderController::class, 'confirm']);
    Route::post('orders/{order}/ship', [OnlineOrderController::class, 'ship']);
    Route::post('orders/{order}/cancel', [OnlineOrderController::class, 'cancel']);
    Route::post('shipments/{shipment}/deliver', [OnlineOrderController::class, 'deliver']);
     });