<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\QuickLinkController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Публичные роуты ───────────────────────────────────────────────

Route::get('/quick-links', [QuickLinkController::class, 'index']);
Route::get('/sliders', [\App\Http\Controllers\Api\Admin\SliderController::class, 'index']);
Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/categories/{slug}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);
Route::get('/categories/{slug}/filters', [\App\Http\Controllers\Api\CategoryController::class, 'filters']);
Route::get('/popular-categories', [\App\Http\Controllers\Api\Admin\PopularCategoryController::class, 'index']);

// Товары (публичные)
Route::get('/products/by-barcode/{barcode}', [\App\Http\Controllers\Api\ProductController::class, 'findByBarcode']);
Route::get('/products/{slug}', [\App\Http\Controllers\Api\ProductController::class, 'show']);

// Корзина (публичная, работает и для гостей и для авторизованных)
// Используем StartSession для гостей и опциональный Sanctum для авторизованных
Route::prefix('cart')->middleware(['api', \Illuminate\Session\Middleware\StartSession::class])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CartController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\CartController::class, 'store']);
    Route::patch('/{itemId}', [\App\Http\Controllers\Api\CartController::class, 'update']);
    Route::delete('/{itemId}', [\App\Http\Controllers\Api\CartController::class, 'destroy']);
    Route::delete('/', [\App\Http\Controllers\Api\CartController::class, 'clear']);
});

// Избранное (публичное, работает и для гостей и для авторизованных)
Route::prefix('wishlist')->middleware(['api', \Illuminate\Session\Middleware\StartSession::class])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
    Route::delete('/{itemId}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
    Route::delete('/product/{productId}', [\App\Http\Controllers\Api\WishlistController::class, 'removeByProduct']);
    Route::delete('/parent/{parentId}', [\App\Http\Controllers\Api\WishlistController::class, 'removeByParent']);
    Route::delete('/', [\App\Http\Controllers\Api\WishlistController::class, 'clear']);
    Route::get('/check/{productId}', [\App\Http\Controllers\Api\WishlistController::class, 'check']);
});

// Поиск (публичный)
Route::get('/search', [\App\Http\Controllers\Api\SearchController::class, 'search']);
Route::get('/search/filters', [\App\Http\Controllers\Api\SearchController::class, 'filters']);
Route::get('/search/suggestions', [\App\Http\Controllers\Api\SearchController::class, 'suggestions']);

// Коллекции товаров (публичные)
Route::get('/product-collections', [\App\Http\Controllers\Api\ProductCollectionController::class, 'index']);

// Блоги (публичные)
Route::get('/blog-categories', [\App\Http\Controllers\Api\BlogCategoryController::class, 'index']);
Route::get('/blogs', [\App\Http\Controllers\Api\BlogController::class, 'index']);
Route::get('/blogs/latest', [\App\Http\Controllers\Api\BlogController::class, 'latest']);
Route::get('/blogs/{blog}', [\App\Http\Controllers\Api\BlogController::class, 'show']);

// Size Tables (публичные)
Route::get('/size-tables/{sizeTable}', [\App\Http\Controllers\Api\Admin\SizeTableController::class, 'show']);


// Магазины (публично — список активных, для самовывоза)
Route::get('/shops', [\App\Http\Controllers\Api\ShopController::class, 'index']);
Route::get('/shops/{shop}/pickup-slot-bookings', [\App\Http\Controllers\Api\ShopController::class, 'pickupSlotBookings']);

// СДЭК (поиск города + список ПВЗ)
Route::get('/cdek/cities',        [\App\Http\Controllers\Api\CdekController::class, 'cities']);
Route::get('/cdek/pickup-points', [\App\Http\Controllers\Api\CdekController::class, 'pickupPoints']);

// Почта России (поиск города + список отделений)
Route::get('/russian-post/cities',        [\App\Http\Controllers\Api\RussianPostController::class, 'cities']);
Route::get('/russian-post/pickup-points', [\App\Http\Controllers\Api\RussianPostController::class, 'pickupPoints']);

// Геокодер (Яндекс proxy)
Route::get('/geocoder/suggest', [\App\Http\Controllers\Api\GeocoderController::class, 'suggest']);
Route::get('/geocoder/geocode',  [\App\Http\Controllers\Api\GeocoderController::class, 'geocode']);

// Капча
Route::get('/captcha', [\App\Http\Controllers\Api\CaptchaController::class, 'generate']);

// Клиент — вход по SMS (OTP)
// StartSession нужен чтобы verifyCode мог прочитать session_id и слить
// гостевую корзину в пользовательскую при логине.
Route::prefix('auth')->middleware([\Illuminate\Session\Middleware\StartSession::class])->group(function () {
    Route::post('/send-code',   [OtpController::class, 'sendCode']);
    Route::post('/verify-code', [OtpController::class, 'verifyCode']);
});

// Админ — вход по email + пароль
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});


// ── Защищённые роуты ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Общие ──

    // Текущий пользователь (с ролями)
    Route::get('/me', fn(Request $request) =>
    $request->user()->load('roles')
    );

    // Выход (клиент)
    Route::post('/auth/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return ['message' => 'Вышли'];
    });

    // Выход (админ)
    Route::post('/admin/auth/logout', [AdminAuthController::class, 'logout']);

    // ── Заказы (клиент) ──
    Route::prefix('orders')->group(function () {
        Route::get('/',                  [\App\Http\Controllers\Api\OrderController::class, 'index']);
        Route::post('/',                 [\App\Http\Controllers\Api\OrderController::class, 'store']);
        Route::get('/{order}',           [\App\Http\Controllers\Api\OrderController::class, 'show']);
        Route::post('/{order}/cancel',   [\App\Http\Controllers\Api\OrderController::class, 'cancel']);
    });

    // ── Адреса доставки ──
    Route::prefix('addresses')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Api\AddressController::class, 'index']);
        Route::post('/',                     [\App\Http\Controllers\Api\AddressController::class, 'store']);
        Route::patch('/{address}',            [\App\Http\Controllers\Api\AddressController::class, 'update']);
        Route::delete('/{address}',          [\App\Http\Controllers\Api\AddressController::class, 'destroy']);
        Route::patch('/{address}/default',   [\App\Http\Controllers\Api\AddressController::class, 'setDefault']);
    });

    // ── Профиль ──
    Route::prefix('profile')->group(function () {
        Route::get('/',                      [ProfileController::class, 'show']);
        Route::patch('/',                    [ProfileController::class, 'update']);
        Route::post('/request-email-change', [ProfileController::class, 'requestEmailChange']);
        Route::post('/confirm-email',        [ProfileController::class, 'confirmEmail']);

        // Управление сессиями
        Route::get('/sessions',              [ProfileController::class, 'sessions']);
        Route::delete('/sessions/{id}',      [ProfileController::class, 'revokeSession']);
        Route::delete('/sessions',           [ProfileController::class, 'revokeAllSessions']);
    });


    // ── ADMIN API ────────────────────────────────────────────────
    Route::prefix('admin')->middleware('admin')->group(function () {

            Route::get('dashboard', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'index']);

            Route::apiResource('quick-links', QuickLinkController::class);

            Route::get('params/create', [\App\Http\Controllers\Api\Admin\ParamController::class, 'create']);
            Route::get('params/{param}/edit', [\App\Http\Controllers\Api\Admin\ParamController::class, 'edit']);
            Route::apiResource('params', \App\Http\Controllers\Api\Admin\ParamController::class);

            Route::post('categories/reorder', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'reorder']);
            Route::get('categories/{category}/params', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'resolvedParams']);

            // потом resource
            Route::get('categories/create', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'create']);
            Route::get('categories/{category}/edit', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'edit']);

            Route::apiResource('categories', \App\Http\Controllers\Api\Admin\CategoryController::class);

            Route::apiResource('sliders', \App\Http\Controllers\Api\Admin\SliderController::class)->except(['show', 'create', 'edit']);
            Route::post('sliders/reorder', [\App\Http\Controllers\Api\Admin\SliderController::class, 'reorder']);

            Route::get('popular-categories/available', [\App\Http\Controllers\Api\Admin\PopularCategoryController::class, 'available']);
            Route::post('popular-categories/reorder',  [\App\Http\Controllers\Api\Admin\PopularCategoryController::class, 'reorder']);
            Route::apiResource('popular-categories',   \App\Http\Controllers\Api\Admin\PopularCategoryController::class)->only(['index', 'store', 'destroy']);

            // Users (клиенты)
            Route::get('users/{user}/edit', [\App\Http\Controllers\Api\Admin\UserController::class, 'edit']);
            Route::apiResource('users', \App\Http\Controllers\Api\Admin\UserController::class);

            // Product groups (WB-style merge)
            Route::get('products/{product}/group-members', [\App\Http\Controllers\Api\Admin\ProductGroupController::class, 'members']);
            Route::post('product-groups/merge', [\App\Http\Controllers\Api\Admin\ProductGroupController::class, 'merge']);
            Route::post('product-groups/{productGroup}/detach', [\App\Http\Controllers\Api\Admin\ProductGroupController::class, 'detach']);

            // Products
            Route::get('products/create',        [\App\Http\Controllers\Api\Admin\ProductController::class, 'create']);
            Route::get('products/{product}/edit', [\App\Http\Controllers\Api\Admin\ProductController::class, 'edit']);
            Route::apiResource('products',        \App\Http\Controllers\Api\Admin\ProductController::class);

            // Product images (standalone deletion)
            Route::delete('images/{image}', [\App\Http\Controllers\Api\Admin\ProductImageController::class, 'destroy']);

            // Product barcodes
            Route::get('products/{product}/barcodes', [\App\Http\Controllers\Api\Admin\ProductBarcodeController::class, 'index']);
            Route::post('products/{product}/barcodes', [\App\Http\Controllers\Api\Admin\ProductBarcodeController::class, 'store']);
            Route::patch('products/{product}/barcodes/{barcode}', [\App\Http\Controllers\Api\Admin\ProductBarcodeController::class, 'update']);
            Route::delete('products/{product}/barcodes/{barcode}', [\App\Http\Controllers\Api\Admin\ProductBarcodeController::class, 'destroy']);

            // Blog Categories
            Route::post('blog-categories/reorder', [\App\Http\Controllers\Api\Admin\BlogCategoryController::class, 'reorder']);
            Route::apiResource('blog-categories', \App\Http\Controllers\Api\Admin\BlogCategoryController::class)
                ->except(['show', 'create', 'edit']);

            // Blogs
            // Blog media uploads (должны быть ДО apiResource)
            Route::post('blogs/upload-image', [\App\Http\Controllers\Api\Admin\BlogMediaController::class, 'uploadImage']);
            Route::post('blogs/upload-video', [\App\Http\Controllers\Api\Admin\BlogMediaController::class, 'uploadVideo']);
            Route::post('blogs/fetch-link-preview', [\App\Http\Controllers\Api\Admin\LinkPreviewController::class, 'fetch']);

            Route::get('blogs/{blog}/edit', [\App\Http\Controllers\Api\Admin\BlogController::class, 'edit']);
            Route::apiResource('blogs', \App\Http\Controllers\Api\Admin\BlogController::class);

            // Brands
            Route::apiResource('brands', \App\Http\Controllers\Api\Admin\BrandController::class);

            // Brand Origins
            Route::apiResource('brand-origins', \App\Http\Controllers\Api\Admin\BrandOriginController::class);

            // Manufacturing Countries
            Route::apiResource('manufacturing-countries', \App\Http\Controllers\Api\Admin\ManufacturingCountryController::class);

            // Warehouses
            Route::post('warehouses/{warehouse}/toggle', [\App\Http\Controllers\Api\WarehouseController::class, 'toggle']);
            Route::apiResource('warehouses', \App\Http\Controllers\Api\WarehouseController::class);

            // Shops (магазины)
            Route::post('shops/reorder', [\App\Http\Controllers\Api\Admin\ShopController::class, 'reorder']);
            Route::post('shops/{shop}/toggle', [\App\Http\Controllers\Api\Admin\ShopController::class, 'toggle']);
            Route::apiResource('shops', \App\Http\Controllers\Api\Admin\ShopController::class);

            // Orders (admin)
            Route::get('orders',                                  [\App\Http\Controllers\Api\Admin\OrderController::class, 'index']);
            Route::get('orders/products/search',                  [\App\Http\Controllers\Api\Admin\OrderController::class, 'searchProducts']);
            Route::get('orders/{order}',                          [\App\Http\Controllers\Api\Admin\OrderController::class, 'show']);
            Route::patch('orders/{order}/status',                 [\App\Http\Controllers\Api\Admin\OrderController::class, 'updateStatus']);
            Route::patch('orders/{order}/admin-comment',          [\App\Http\Controllers\Api\Admin\OrderController::class, 'updateAdminComment']);
            Route::patch('orders/{order}/tracking',               [\App\Http\Controllers\Api\Admin\OrderController::class, 'updateTrackingNumber']);
            Route::post('orders/{order}/items',                   [\App\Http\Controllers\Api\Admin\OrderController::class, 'addItem']);
            Route::post('orders/{order}/items/restore',           [\App\Http\Controllers\Api\Admin\OrderController::class, 'restoreItem']);
            Route::patch('orders/{order}/items/{item}/warehouse', [\App\Http\Controllers\Api\Admin\OrderController::class, 'updateItemWarehouse']);
            Route::patch('orders/{order}/items/{item}',           [\App\Http\Controllers\Api\Admin\OrderController::class, 'updateItem']);
            Route::delete('orders/{order}/items/{item}',          [\App\Http\Controllers\Api\Admin\OrderController::class, 'removeItem']);

            // Stock Management
            Route::prefix('stocks')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Admin\StockController::class, 'index']);
                Route::get('/{product}', [\App\Http\Controllers\Api\Admin\StockController::class, 'show']);
                Route::post('/add', [\App\Http\Controllers\Api\Admin\StockController::class, 'addStock']);
                Route::post('/remove', [\App\Http\Controllers\Api\Admin\StockController::class, 'removeStock']);
                Route::post('/transfer', [\App\Http\Controllers\Api\Admin\StockController::class, 'transferStock']);
                Route::post('/adjust', [\App\Http\Controllers\Api\Admin\StockController::class, 'adjustStock']);
                Route::post('/reserve', [\App\Http\Controllers\Api\Admin\StockController::class, 'reserve']);
                Route::post('/unreserve', [\App\Http\Controllers\Api\Admin\StockController::class, 'unreserve']);
                Route::get('/{product}/movements', [\App\Http\Controllers\Api\Admin\StockController::class, 'movements']);
            });

            // Stock Documents
            Route::prefix('stock-documents')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'store']);
                Route::get('/{document}', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'show']);
                Route::patch('/{document}', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'update']);
                Route::delete('/{document}', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'destroy']);
                Route::post('/{document}/complete', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'complete']);
                Route::post('/{document}/cancel', [\App\Http\Controllers\Api\Admin\StockDocumentController::class, 'cancel']);
            });

            // Size Tables
            Route::get('size-tables/list', [\App\Http\Controllers\Api\Admin\SizeTableController::class, 'list']);
            Route::apiResource('size-tables', \App\Http\Controllers\Api\Admin\SizeTableController::class);

            // Product Collections
            Route::get('product-collections/available-products', [\App\Http\Controllers\Api\Admin\ProductCollectionController::class, 'availableProducts']);
            Route::get('product-collections/filters', [\App\Http\Controllers\Api\Admin\ProductCollectionController::class, 'filters']);
            Route::post('product-collections/reorder', [\App\Http\Controllers\Api\Admin\ProductCollectionController::class, 'reorder']);
            Route::post('product-collections/{id}/products', [\App\Http\Controllers\Api\Admin\ProductCollectionController::class, 'addProduct']);
            Route::delete('product-collections/{id}/products/{itemId}', [\App\Http\Controllers\Api\Admin\ProductCollectionController::class, 'removeProduct']);
            Route::post('product-collections/{id}/products/reorder', [\App\Http\Controllers\Api\Admin\ProductCollectionController::class, 'reorderProducts']);
            Route::apiResource('product-collections', \App\Http\Controllers\Api\Admin\ProductCollectionController::class);
    });


});
