<?php

namespace App\Http\Requests;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KasirUpdateRequest extends FormRequest
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
        $kasir = $this->route('kasir');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new Kasir)->getTable(), 'username')->ignore($kasir),
                Rule::unique((new Admin)->getTable(), 'username'),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique((new Kasir)->getTable(), 'email')->ignore($kasir),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
