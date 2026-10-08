<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Models\Customer;

class RestoreCustomerAction
{
    /**
     * Restore an archived customer.
     */
    public function execute(Customer $customer): Customer
    {
        $customer->restore();
        $customer->update(['status' => CustomerStatus::Active]);

        return $customer;
    }
}
