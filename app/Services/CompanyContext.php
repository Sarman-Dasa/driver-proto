<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;

class CompanyContext
{
    private ?Company $currentCompany = null;

    /**
     * Set the current active company.
     */
    public function setCompany(?Company $company): void
    {
        $this->currentCompany = $company;
    }

    /**
     * Get the current active company.
     */
    public function getCompany(): ?Company
    {
        return $this->currentCompany;
    }

    /**
     * Determine if a company context is currently set.
     */
    public function hasCompany(): bool
    {
        return $this->currentCompany !== null;
    }

    /**
     * Get the current company ID, if set.
     */
    public function id(): ?int
    {
        return $this->currentCompany?->id;
    }

    /**
     * Clear and reset the current company context.
     *
     * Note for Long-Running Workers & Queued Jobs:
     * Queued jobs must NEVER rely on this singleton context because queue
     * workers run persistently across jobs. Any future company-scoped job must
     * explicitly carry $companyId and resolve the company model within the job.
     */
    public function clear(): void
    {
        $this->currentCompany = null;
    }
}
