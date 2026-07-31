<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $identifier = trim($this->string('email')->toString());
        $mobile = preg_replace('/\D+/', '', $identifier) ?? '';
        if (str_starts_with($mobile, '00')) {
            $mobile = substr($mobile, 2);
        }
        if (strlen($mobile) === 10) {
            $mobile = '91'.$mobile;
        }
        $identifierColumn = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $identifierValue = $identifierColumn === 'email' ? mb_strtolower($identifier) : '+'.$mobile;
        $credentials = [
            $identifierColumn => $identifierValue,
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (in_array(Auth::user()?->account_status, ['pending_invitation', 'suspended', 'disabled'], true)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact a company administrator for help.',
            ]);
        }

        $this->session()->regenerate();
    }
}
