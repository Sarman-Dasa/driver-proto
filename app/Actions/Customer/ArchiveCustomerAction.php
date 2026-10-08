<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Enums\CustomerStatus;
use App\Models\Customer;

class ArchiveCustomerAction
{
    /**
     * Archive the customer via soft delete and status change.
     */
    public function execute(Customer $customer): Customer
    {
        $customer->update(['status' => CustomerStatus::Archived]);
        $customer->delete();

        return $customer;
    }
}
