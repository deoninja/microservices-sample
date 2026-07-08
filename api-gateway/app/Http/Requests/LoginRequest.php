<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/*
 * app/Http/Requests/LoginRequest.php — Login Input Validation
 *
 * Form Request classes encapsulate validation logic, keeping
 * controllers thin. The validation rules, authorization checks,
 * and error messages live here instead of in the controller.
 */

class LoginRequest extends FormRequest
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
            'username' => 'required|string',
            'password' => 'required|string',
        ];
    }
}
