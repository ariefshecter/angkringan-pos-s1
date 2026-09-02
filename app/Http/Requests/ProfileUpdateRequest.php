<?php

namespace App\Http\Requests;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $table = $this->user()->getTable();
        $otherTable = $this->user() instanceof Admin ? (new Kasir)->getTable() : (new Admin)->getTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique($table, 'username')->ignore($this->user()->id),
                Rule::unique($otherTable, 'username'),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique($table, 'email')->ignore($this->user()->id),
            ],
        ];
    }
}
