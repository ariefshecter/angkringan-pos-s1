<?php

namespace App\Http\Requests;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KasirStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new Kasir)->getTable(), 'username'),
                Rule::unique((new Admin)->getTable(), 'username'),
            ],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique((new Kasir)->getTable(), 'email')],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
