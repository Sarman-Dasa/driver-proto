<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company|int|string|null $companyParam */
        $companyParam = $this->route('company');
        $companyId = $companyParam instanceof Company ? $companyParam->id : (int) $companyParam;

        /** @var Product|int|string|null $productParam */
        $productParam = $this->route('product');
        $productId = $productParam instanceof Product ? $productParam->id : (int) $productParam;

        return [
            'type' => ['sometimes', 'required', new Enum(ProductType::class)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', new Enum(ProductStatus::class)],
            'company_id' => ['prohibited'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.prohibited' => 'The workspace ownership of a product cannot be altered.',
        ];
    }
}
