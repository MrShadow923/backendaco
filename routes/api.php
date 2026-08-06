<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseRequestController;
use App\Http\Controllers\Api\V1\PurchaseRequestFormatController;
use App\Http\Controllers\Api\V1\RevenueCenterController;
use App\Http\Controllers\Api\V1\StockReleaseController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::prefix('v1')->group(function () {

    // Removed StartSession::class from here
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/login', function () {
            return redirect()->away(env('FRONTEND_URL', 'http://localhost:3000') . '/login');
        });
    });

    // Removed StartSession::class from here
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::apiResource('posts', PostController::class);

        // Purchase Requests
        Route::get('/purchase-requests', [PurchaseRequestController::class, 'index']);
        Route::post('/purchase-requests', [PurchaseRequestController::class, 'store']);
        Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show']);
        Route::patch('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update']);
        Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit']);
        Route::post('/purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel']);

        // Purchase Request Formats
        Route::get('/purchase-request-formats', [PurchaseRequestFormatController::class, 'index']);
        Route::post('/purchase-request-formats', [PurchaseRequestFormatController::class, 'store']);
        Route::get('/purchase-request-formats/{format}', [PurchaseRequestFormatController::class, 'show']);
        Route::patch('/purchase-request-formats/{format}', [PurchaseRequestFormatController::class, 'update']);
        Route::delete('/purchase-request-formats/{format}', [PurchaseRequestFormatController::class, 'destroy']);
        Route::post('/purchase-request-formats/{format}/purchase-request', [PurchaseRequestFormatController::class, 'createPurchaseRequest']);

        // Purchase Orders
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::patch('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('/purchase-orders/{purchaseOrder}/sign', [PurchaseOrderController::class, 'sign']);
        Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
        Route::post('/purchase-orders/{purchaseOrder}/callback', [PurchaseOrderController::class, 'callback']);

        // Dashboard
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Inventory
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/{inventoryItem}', [InventoryController::class, 'show']);
        Route::get('/inventory/{inventoryItem}/transactions', [InventoryController::class, 'transactions']);

        // Departments
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::get('/departments/{department}', [DepartmentController::class, 'show']);
        Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
        Route::get('/departments/{department}/stock-releases', [DepartmentController::class, 'stockReleases']);

        // Revenue Centers
        Route::get('/revenue-centers', [RevenueCenterController::class, 'index']);
        Route::post('/revenue-centers', [RevenueCenterController::class, 'store']);
        Route::get('/revenue-centers/{revenueCenter}', [RevenueCenterController::class, 'show']);
        Route::patch('/revenue-centers/{revenueCenter}', [RevenueCenterController::class, 'update']);
        Route::delete('/revenue-centers/{revenueCenter}', [RevenueCenterController::class, 'destroy']);

        // Stock Releases
        Route::get('/stock-releases', [StockReleaseController::class, 'index']);
        Route::post('/stock-releases', [StockReleaseController::class, 'store']);
        Route::get('/stock-releases/{stockRelease}', [StockReleaseController::class, 'show']);
    });
});