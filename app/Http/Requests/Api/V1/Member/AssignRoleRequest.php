<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Member;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
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
        return [
            'role_id' => ['required_without:role', 'nullable', 'integer', 'exists:roles,id'],
            'role' => ['required_without:role_id', 'nullable', 'string', 'exists:roles,slug'],
        ];
    }
}
