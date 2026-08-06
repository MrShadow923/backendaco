<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,submitted'],
            'format_data' => ['required', 'array'],
            'format_data.purpose' => ['required', 'string', 'max:500'],
            'format_data.remarks' => ['nullable', 'string', 'max:1000'],
            'format_data.submitted_for' => ['required', 'string', 'max:50'],
            'format_data.items' => ['required', 'array', 'min:1'],
            'format_data.items.*.item_name' => ['required', 'string', 'max:255'],
            'format_data.items.*.description' => ['nullable', 'string', 'max:500'],
            'format_data.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'format_data.items.*.unit' => ['required', 'string', 'max:50'],
            'format_data.items.*.estimated_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
