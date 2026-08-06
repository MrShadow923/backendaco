<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequestFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,submitted'],
            'format_data' => ['sometimes', 'array'],
            'format_data.purpose' => ['nullable', 'string', 'max:500'],
            'format_data.remarks' => ['nullable', 'string', 'max:1000'],
            'format_data.submitted_for' => ['nullable', 'string', 'max:50'],
            'format_data.items' => ['nullable', 'array'],
            'format_data.items.*.item_name' => ['nullable', 'string', 'max:255'],
            'format_data.items.*.description' => ['nullable', 'string', 'max:500'],
            'format_data.items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'format_data.items.*.unit' => ['nullable', 'string', 'max:50'],
            'format_data.items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
