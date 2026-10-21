<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ModifyOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.price' => ['required_with:items', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
        ];
    }
}
