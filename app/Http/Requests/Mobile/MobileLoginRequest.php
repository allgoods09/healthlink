<?php

namespace App\Http\Requests\Mobile;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\RateLimitState;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileLoginRequest extends FormRequest
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
            'device_name' => ['required', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'device_platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Ensure the request is not rate limited.
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
     * Register a failed mobile login attempt.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), $this->decaySeconds());
        AuditLog::logFailedLogin(
            $this->string('email')->toString(),
            $this->ip(),
            $this->userAgent()
        );
    }

    /**
     * Clear the authentication throttle for a successful login.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Get the throttle key for the request.
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
