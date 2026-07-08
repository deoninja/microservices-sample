<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/*
 * app/Http/Requests/RegisterRequest.php — Registration Input Validation
 */

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3',
            'password' => 'required|string|min:6',
            'name'     => 'required|string',
            'email'    => 'required|email',
        ];
    }
}
