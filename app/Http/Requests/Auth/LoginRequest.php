<?php

namespace App\Http\Requests\Auth;

use App\Models\Setting;
use App\Support\RateLimitState;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user && $user->approval_status === \App\Models\User::APPROVAL_REJECTED) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Your registration has been rejected. Please contact the administrator for assistance.',
            ]);
        }

        if ($user && $user->approval_status !== \App\Models\User::APPROVAL_PENDING && ! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Your account is inactive. Please contact the administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        RateLimitState::trackAuthKey(
            $this->throttleKey(),
            $this->string('email')->toString(),
            $this->ip()
        );

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts())) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * Get the configured maximum authentication attempts.
     */
    private function maxAttempts(): int
    {
        return max((int) Setting::getValue('api_rate_limit_auth', 10), 1);
    }

    /**
     * Get the configured throttle decay period in seconds.
     */
    private function decaySeconds(): int
    {
        return max((int) Setting::getValue('rate_limit_decay_minutes', 1), 1) * 60;
    }
}
