<?php

namespace App\Http\Controllers\Auth;

use App\Actions\ResetUserPassword;
use App\Actions\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordResetController extends Controller
{
    public function requestForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function send(ForgotPasswordRequest $request, SendPasswordResetLink $send): RedirectResponse
    {
        $status = $send->handle($request->string('email')->toString());

        if ($status === PasswordBroker::RESET_THROTTLED) {
            return back()->withErrors(['email' => 'Please wait before requesting another reset link.']);
        }

        return back()->with('success', 'If an account matches that address, a reset link is on its way.');
    }

    public function resetForm(string $token, Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(ResetPasswordRequest $request, ResetUserPassword $reset): RedirectResponse
    {
        $status = $reset->handle(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password has been reset. You can sign in now.');
        }

        $message = $status === PasswordBroker::RESET_THROTTLED
            ? 'Please wait before trying again.'
            : 'This password reset link is invalid or has expired.';

        return back()->withErrors(['email' => $message]);
    }
}
