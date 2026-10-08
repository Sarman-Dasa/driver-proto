<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo' => $this->logo,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'tax_number' => $this->tax_number,
            'currency' => $this->currency ?? 'USD',
            'timezone' => $this->timezone ?? 'UTC',
            'status' => $this->status->value,
            'is_active' => $this->isActive(),
            'owner_id' => $this->owner_id,
            'is_owner' => $user !== null && $this->isOwner($user),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
