<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ComplementController;
use App\Http\Controllers\DailyMenuController;
use App\Http\Controllers\DeliveryCatalogController;
use App\Http\Controllers\DeliveryIncidentController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\DishImageController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplyAlertController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', PingController::class);

Route::get('/daily-menu', [DailyMenuController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

Route::prefix('2fa')->group(function () {
    Route::post('/verify', [TwoFactorController::class, 'verify']);
    Route::post('/resend', [TwoFactorController::class, 'resend']);
});

Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::match(['put', 'patch'], '/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', ChangePasswordController::class)->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/measurement-units', [MeasurementUnitController::class, 'index']);
    Route::get('/dishes', [DishController::class, 'index']);
    Route::get('/dishes/{dish}', [DishController::class, 'show']);
    Route::get('/complements', [ComplementController::class, 'index']);
    Route::get('/complements/{complement}', [ComplementController::class, 'show']);
    Route::get('/supplies', [SupplyController::class, 'index']);
    Route::get('/supplies/{supply}', [SupplyController::class, 'show']);

    Route::get('/inventory-movement-types', [InventoryMovementController::class, 'types']);
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index']);
    Route::post('/inventory-movements', [InventoryMovementController::class, 'store']);
    Route::get('/inventory-movements/{id}', [InventoryMovementController::class, 'show']);

    Route::get('/alert-statuses', [SupplyAlertController::class, 'statuses']);
    Route::get('/alert-origins', [SupplyAlertController::class, 'origins']);
    Route::get('/supply-alerts', [SupplyAlertController::class, 'index']);
    Route::post('/supply-alerts', [SupplyAlertController::class, 'store']);
    Route::get('/supply-alerts/{id}', [SupplyAlertController::class, 'show']);

    Route::get('/order-statuses', [OrderController::class, 'statuses']);
    Route::get('/order-types', [OrderController::class, 'types']);
    Route::get('/table-statuses', [RestaurantTableController::class, 'statuses']);
    Route::get('/restaurant-tables', [RestaurantTableController::class, 'index']);
    Route::get('/restaurant-tables/{id}', [RestaurantTableController::class, 'show']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/kitchen/orders', [OrderController::class, 'kitchenOrders']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::patch('/orders/{id}', [OrderController::class, 'update']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/start-preparation', [OrderController::class, 'startPreparation']);
    Route::post('/orders/{id}/ready', [OrderController::class, 'markAsReady']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/pay', [SaleController::class, 'store']);

    Route::get('/payment-methods', [SaleController::class, 'paymentMethods']);
    Route::get('/receipt-types', [SaleController::class, 'receiptTypes']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);

    Route::get('/delivery-days', [DeliveryCatalogController::class, 'deliveryDays']);
    Route::get('/delivery-incident-types', [DeliveryCatalogController::class, 'incidentTypes']);
    Route::get('/delivery-incident-statuses', [DeliveryCatalogController::class, 'incidentStatuses']);
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
    Route::get('/suppliers/{id}/history', [SupplierController::class, 'history']);
    Route::post('/suppliers/{id}/deliveries', [SupplierController::class, 'recordDelivery']);
    Route::get('/delivery-incidents', [DeliveryIncidentController::class, 'index']);
    Route::post('/delivery-incidents', [DeliveryIncidentController::class, 'store']);
    Route::get('/delivery-incidents/{id}', [DeliveryIncidentController::class, 'show']);

    Route::middleware('role:Administrador,Mesero/Cajero')->group(function () {
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::get('/purchase-orders/statuses', [PurchaseOrderController::class, 'statuses']);
        Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
        Route::post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
        Route::post('/purchase-orders/{id}/incident', [PurchaseOrderController::class, 'reportIncident']);
        Route::patch('/delivery-incidents/{id}/status', [DeliveryIncidentController::class, 'updateStatus']);
    });

    Route::middleware('role:Administrador')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::apiResource('users', UserController::class);
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
        Route::patch('/suppliers/{id}', [SupplierController::class, 'update']);
        Route::patch('/suppliers/{id}/status', [SupplierController::class, 'toggleStatus']);
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

        Route::post('/uploads/dish-image', [DishImageController::class, 'store'])->middleware('throttle:20,1');
        Route::delete('/uploads/dish-image', [DishImageController::class, 'destroy'])->middleware('throttle:60,1');
        Route::post('/dishes', [DishController::class, 'store']);
        Route::put('/dishes/{dish}', [DishController::class, 'update']);
        Route::delete('/dishes/{dish}', [DishController::class, 'destroy']);
        Route::patch('/dishes/{dish}/status', [DishController::class, 'toggleStatus']);
        Route::patch('/dishes/{dish}/daily-menu', [DailyMenuController::class, 'toggleDish']);

        Route::put('/daily-menu', [DailyMenuController::class, 'updateBatch']);

        Route::post('/complements', [ComplementController::class, 'store']);
        Route::put('/complements/{complement}', [ComplementController::class, 'update']);
        Route::delete('/complements/{complement}', [ComplementController::class, 'destroy']);
        Route::patch('/complements/{complement}/status', [ComplementController::class, 'toggleStatus']);

        Route::post('/supplies', [SupplyController::class, 'store']);
        Route::put('/supplies/{supply}', [SupplyController::class, 'update']);
        Route::delete('/supplies/{supply}', [SupplyController::class, 'destroy']);
        Route::patch('/supplies/{supply}/status', [SupplyController::class, 'toggleStatus']);

        Route::post('/inventory-movements/{id}/approve', [InventoryMovementController::class, 'approveAdjustment']);
        Route::post('/inventory-movements/{id}/reject', [InventoryMovementController::class, 'rejectAdjustment']);

        Route::post('/supply-alerts/{id}/attend', [SupplyAlertController::class, 'attend']);

        Route::get('/purchase-requests', [PurchaseRequestController::class, 'index']);
        Route::get('/purchase-requests/statuses', [PurchaseRequestController::class, 'statuses']);
        Route::get('/purchase-requests/{id}', [PurchaseRequestController::class, 'show']);
        Route::post('/purchase-requests', [PurchaseRequestController::class, 'store']);
        Route::post('/purchase-requests/{id}/approve', [PurchaseRequestController::class, 'approve']);
        Route::post('/purchase-requests/{id}/reject', [PurchaseRequestController::class, 'reject']);

        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::post('/purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::post('/restaurant-tables', [RestaurantTableController::class, 'store']);
        Route::put('/restaurant-tables/{id}', [RestaurantTableController::class, 'update']);
        Route::patch('/restaurant-tables/{id}', [RestaurantTableController::class, 'update']);
        Route::patch('/restaurant-tables/{id}/status', [RestaurantTableController::class, 'toggleStatus']);
        Route::delete('/restaurant-tables/{id}', [RestaurantTableController::class, 'destroy']);

        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/top-dishes', [ReportController::class, 'topDishes']);
        Route::get('/reports/inventory-movements', [ReportController::class, 'inventoryMovements']);
        Route::get('/reports/supply-alerts', [ReportController::class, 'supplyAlerts']);
    });
});
