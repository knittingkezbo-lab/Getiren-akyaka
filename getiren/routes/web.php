<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Courier\JobController;
use App\Http\Controllers\Courier\SettingsController as CourierSettings;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLog;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrders;
use App\Http\Controllers\Admin\CourierController as AdminCouriers;
use App\Http\Controllers\Admin\SettingController as AdminSettings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Hukuki sayfalar — herkese açık (auth/guest fark etmez)
Route::get('/hukuki/{page}', [LegalController::class, 'show'])->name('legal.show');

// PayTR ödeme callback'i (sunucudan sunucuya POST) — CSRF muaf (bootstrap/app.php'de)
Route::post('/odeme/paytr/geri-bildirim', [PaymentCallbackController::class, 'paytr'])->name('payment.paytr.callback');

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

    // E-posta doğrulama (özellik AUTH_EMAIL_VERIFICATION ile açılır)
    Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/dogrulama-gonder', [VerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Kök: kullanıcıyı rolüne göre açılış sayfasına yönlendirir
    Route::get('/', fn () => redirect()->route(auth()->user()->role->homeRoute()))->name('home');

    // Müşteri alanı
    Route::middleware(['role:customer', 'verified'])->prefix('musteri')->name('customer.')->group(function () {
        Route::get('/', [CustomerDashboard::class, 'index'])->name('dashboard');

        Route::get('/siparis/yeni', [OrderController::class, 'create'])->name('orders.create');
        Route::post('/siparis/tahmin', [OrderController::class, 'estimate'])->name('orders.estimate');
        Route::post('/siparis', [OrderController::class, 'store'])->name('orders.store');

        Route::get('/siparisler/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/siparisler/{order}/iptal', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/siparisler/{order}/ek-odeme', [OrderController::class, 'payExtra'])->name('orders.extra');

        Route::get('/siparisler', [OrderController::class, 'index'])->name('orders.index');

        Route::get('/odemeler', [PaymentController::class, 'index'])->name('payments');

        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [ProfileController::class, 'updateInfo'])->name('profile.info');
        Route::put('/profil/adres', [ProfileController::class, 'updateAddress'])->name('profile.address');
        Route::put('/profil/sifre', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profil/bildirimler', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
        Route::put('/profil/banka', [ProfileController::class, 'updateBank'])->name('profile.bank');
    });

    // Kurye onay-bekleme ekranı (onay gate'inin DIŞINDA — pending kurye buraya düşer)
    Route::middleware('role:courier')->get('/kurye/onay-bekleniyor', function () {
        return auth()->user()->isApproved()
            ? redirect()->route('courier.dashboard')
            : Inertia::render('Courier/PendingApproval');
    })->name('courier.pending');

    // Kurye alanı (onaylı kurye gerekir)
    Route::middleware(['role:courier', 'verified', 'courier.approved'])->prefix('kurye')->name('courier.')->group(function () {
        Route::get('/', [JobController::class, 'index'])->name('dashboard');

        Route::get('/tercihler', [CourierSettings::class, 'edit'])->name('settings');
        Route::put('/tercihler', [CourierSettings::class, 'updateInfo'])->name('settings.info');
        Route::put('/tercihler/sifre', [CourierSettings::class, 'updatePassword'])->name('settings.password');
        Route::put('/tercihler/bildirimler', [CourierSettings::class, 'updateNotifications'])->name('settings.notifications');
        Route::put('/tercihler/banka', [CourierSettings::class, 'updateBank'])->name('settings.bank');

        Route::get('/is/{order}', [JobController::class, 'show'])->name('jobs.show');
        Route::post('/is/{order}/ustlen', [JobController::class, 'accept'])->name('jobs.accept');
        Route::post('/is/{order}/durum', [JobController::class, 'advance'])->name('jobs.advance');
        Route::post('/is/{order}/fis', [JobController::class, 'settle'])->name('jobs.settle');
    });

    // Yönetici alanı
    Route::middleware(['role:admin', 'verified'])->prefix('yonetici')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

        Route::get('/siparisler', [AdminOrders::class, 'index'])->name('orders');
        Route::post('/siparisler/{order}/ata', [AdminOrders::class, 'assign'])->name('orders.assign');

        Route::get('/kuryeler', [AdminCouriers::class, 'index'])->name('couriers');
        Route::post('/kuryeler/{user}/onayla', [AdminCouriers::class, 'approve'])->name('couriers.approve');
        Route::post('/kuryeler/{user}/reddet', [AdminCouriers::class, 'reject'])->name('couriers.reject');

        Route::get('/ayarlar', [AdminSettings::class, 'index'])->name('settings');
        Route::post('/ayarlar', [AdminSettings::class, 'update'])->name('settings.update');
        Route::post('/ayarlar/fiyat-ice-aktar', [AdminSettings::class, 'importPrices'])->name('settings.prices.import');

        Route::get('/denetim', [AdminAuditLog::class, 'index'])->name('audit');
    });
});
