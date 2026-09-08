<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'custom_code' => ['nullable', 'string', 'min:3', 'max:32', 'alpha_dash', 'unique:urls,short_code'],
        ];
    }
}
