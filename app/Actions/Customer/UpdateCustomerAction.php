<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\Customer;

class UpdateCustomerAction
{
    /**
     * Update an existing customer record safely.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Customer $customer, array $data): Customer
    {
        // Prevent modifying company_id under all circumstances
        unset($data['company_id']);

        $sameAsBilling = isset($data['shipping_same_as_billing'])
            ? (bool) $data['shipping_same_as_billing']
            : (bool) $customer->shipping_same_as_billing;

        if ($sameAsBilling) {
            $data['shipping_address'] = $data['billing_address'] ?? $customer->billing_address;
            $data['shipping_city'] = $data['billing_city'] ?? $customer->billing_city;
            $data['shipping_state'] = $data['billing_state'] ?? $customer->billing_state;
            $data['shipping_postal_code'] = $data['billing_postal_code'] ?? $customer->billing_postal_code;
            $data['shipping_country'] = $data['billing_country'] ?? $customer->billing_country;
        }

        $customer->update($data);

        return $customer->fresh();
    }
}
