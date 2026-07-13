<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    /** "E-postanı doğrula" bilgi ekranı. */
    public function notice(Request $request): RedirectResponse|Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->role->homeRoute());
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $request->user()->email,
            'status' => $request->session()->get('status'),
        ]);
    }

    /** İmzalı doğrulama linkine tıklayınca e-postayı doğrular. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill(); // markEmailAsVerified + Verified event
        }

        return redirect()->route($request->user()->role->homeRoute())
            ->with('success', 'E-postan doğrulandı.');
    }

    /** Doğrulama linkini yeniden gönderir. */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->role->homeRoute());
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Doğrulama linki tekrar gönderildi.');
    }
}
