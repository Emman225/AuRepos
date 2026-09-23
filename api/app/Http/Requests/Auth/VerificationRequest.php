<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'courriel', 'code' => 'code'];
    }
}
