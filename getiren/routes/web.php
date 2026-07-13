<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\WalletController;
use App\Http\Controllers\Courier\JobController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrders;
use App\Http\Controllers\Admin\CourierController as AdminCouriers;
use App\Http\Controllers\Admin\SettingController as AdminSettings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Misafir (giriş yapmamış) rotaları
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

// Kimliği doğrulanmış rotalar
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/bildirimler/oku', [NotificationController::class, 'markAllRead'])->name('notifications.read');

    // Kök: kullanıcıyı rolüne göre açılış sayfasına yönlendirir
    Route::get('/', fn () => redirect()->route(auth()->user()->role->homeRoute()))->name('home');

    // Müşteri alanı
    Route::middleware('role:customer')->prefix('musteri')->name('customer.')->group(function () {
        Route::get('/', [CustomerDashboard::class, 'index'])->name('dashboard');

        Route::get('/siparis/yeni', [OrderController::class, 'create'])->name('orders.create');
        Route::post('/siparis', [OrderController::class, 'store'])->name('orders.store');

        Route::get('/siparisler/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/siparisler/{order}/iptal', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/siparisler/{order}/ek-odeme', [OrderController::class, 'payExtra'])->name('orders.extra');

        Route::get('/siparisler', [OrderController::class, 'index'])->name('orders.index');

        Route::get('/cuzdan', [WalletController::class, 'index'])->name('wallet');
        Route::post('/cuzdan/yukle', [WalletController::class, 'topup'])->name('wallet.topup');

        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [ProfileController::class, 'updateInfo'])->name('profile.info');
        Route::put('/profil/adres', [ProfileController::class, 'updateAddress'])->name('profile.address');
        Route::put('/profil/sifre', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profil/bildirimler', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    });

    // Kurye alanı
    Route::middleware('role:courier')->prefix('kurye')->name('courier.')->group(function () {
        Route::get('/', [JobController::class, 'index'])->name('dashboard');
        Route::get('/is/{order}', [JobController::class, 'show'])->name('jobs.show');
        Route::post('/is/{order}/ustlen', [JobController::class, 'accept'])->name('jobs.accept');
        Route::post('/is/{order}/durum', [JobController::class, 'advance'])->name('jobs.advance');
        Route::post('/is/{order}/fis', [JobController::class, 'settle'])->name('jobs.settle');
    });

    // Yönetici alanı
    Route::middleware('role:admin')->prefix('yonetici')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

        Route::get('/siparisler', [AdminOrders::class, 'index'])->name('orders');
        Route::post('/siparisler/{order}/ata', [AdminOrders::class, 'assign'])->name('orders.assign');

        Route::get('/kuryeler', [AdminCouriers::class, 'index'])->name('couriers');

        Route::get('/ayarlar', [AdminSettings::class, 'index'])->name('settings');
        Route::post('/ayarlar', [AdminSettings::class, 'update'])->name('settings.update');
    });
});
