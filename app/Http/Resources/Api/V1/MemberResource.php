<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Company|null $company */
        $company = $request->route('company')
            ?? $request->attributes->get('current_company');

        $role = $company ? $this->roleInCompany($company) : null;
        $isOwner = $company ? $company->isOwner($this->resource) : false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_owner' => $isOwner,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ] : null,
            'joined_at' => $this->pivot?->created_at?->toISOString(),
        ];
    }
}
