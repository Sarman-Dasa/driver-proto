<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Models\Company;
use App\Models\StripeConnectAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Stripe\Account as StripeAccount;
use Stripe\StripeClient;
use Throwable;

class StripeConnectService
{
    private ?StripeClient $client = null;

    /**
     * Get or instantiate the Stripe Client.
     */
    public function getClient(): StripeClient
    {
        if ($this->client === null) {
            $secret = (string) config('stripe.secret');
            if (empty($secret)) {
                throw new InvalidArgumentException('Stripe secret key is not configured.');
            }

            if (config('stripe.suppress_notices', true)) {
                putenv('STRIPE_SUPPRESS_NOTICES=true');
                $_ENV['STRIPE_SUPPRESS_NOTICES'] = 'true';
            }

            $this->client = new StripeClient($secret);
        }

        return $this->client;
    }

    /**
     * Set a custom StripeClient instance (useful for testing and mocking).
     */
    public function setClient(StripeClient $client): void
    {
        $this->client = $client;
    }

    /**
     * Get or create a Stripe Connect Express account for the company.
     * Uses Accounts v2 with graceful fallback to ensure compatibility.
     */
    public function getOrCreateAccount(Company $company, ?User $user = null): StripeConnectAccount
    {
        $existing = $company->stripeConnectAccount;
        if ($existing && ! empty($existing->stripe_account_id)) {
            return $existing;
        }

        $countryIso = strtolower((string) ($company->country ?: 'US'));
        if (strlen($countryIso) !== 2) {
            $countryIso = 'us';
        }

        $email = $company->email ?: ($user?->email ?: 'billing@billflow.test');
        $currency = strtolower((string) ($company->currency ?: 'USD'));

        // Accounts v2 configuration parameters
        $v2Params = [
            'contact_email' => $email,
            'display_name' => $company->name,
            'identity' => [
                'country' => $countryIso,
                'entity_type' => 'company',
                'business_details' => [
                    'registered_name' => $company->name,
                ],
            ],
            'dashboard' => 'express',
            'configuration' => [
                'merchant' => [
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                    ],
                ],
                'recipient' => [
                    'capabilities' => [
                        'stripe_balance' => [
                            'stripe_transfers' => ['requested' => true],
                        ],
                    ],
                ],
            ],
            'defaults' => [
                'currency' => $currency,
                'responsibilities' => [
                    'fees_collector' => 'application',
                    'losses_collector' => 'application',
                ],
            ],
            'metadata' => [
                'company_id' => (string) $company->id,
                'company_name' => $company->name,
                'platform' => 'BillFlow',
            ],
        ];

        try {
            // Attempt creation via Accounts v2
            $stripeAccount = $this->getClient()->v2->core->accounts->create($v2Params);
            $accountId = $stripeAccount->id;
            $accountType = $stripeAccount->dashboard ?? 'express';
            $resolvedCountry = strtoupper((string) ($stripeAccount->identity->country ?? $countryIso));
            $resolvedCurrency = strtoupper((string) ($stripeAccount->defaults->currency ?? $currency));
        } catch (Throwable $v2Exception) {
            // Fallback to v1 accounts creation if v2 core accounts is unavailable or unsupported
            Log::warning('Accounts v2 creation failed, falling back to v1: '.$v2Exception->getMessage());

            $stripeAccount = $this->getClient()->accounts->create([
                'type' => 'express',
                'country' => strtoupper($countryIso),
                'email' => $email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_type' => 'company',
                'company' => [
                    'name' => $company->name,
                ],
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'company_name' => $company->name,
                    'platform' => 'BillFlow',
                ],
            ]);

            $accountId = $stripeAccount->id;
            $accountType = (string) ($stripeAccount->type ?? 'express');
            $resolvedCountry = strtoupper((string) ($stripeAccount->country ?? $countryIso));
            $resolvedCurrency = strtoupper((string) ($stripeAccount->default_currency ?? $currency));
        }

        return StripeConnectAccount::updateOrCreate(
            ['company_id' => $company->id],
            [
                'stripe_account_id' => $accountId,
                'account_type' => $accountType,
                'country' => $resolvedCountry,
                'currency' => $resolvedCurrency,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'details_submitted' => false,
                'status' => StripeConnectAccount::STATUS_PENDING,
                'capabilities' => [
                    'card_payments' => 'inactive',
                    'transfers' => 'inactive',
                ],
                'requirements' => [],
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Create an onboarding Account Link redirect URL for the company owner.
     * Uses Accounts v2 account links or Connect account onboarding links.
     */
    public function createOnboardingLink(Company $company, string $returnUrl, string $refreshUrl, ?User $user = null): string
    {
        $account = $this->getOrCreateAccount($company, $user);

        // Try v2 core account links first if supported
        try {
            $accountLink = $this->getClient()->v2->core->accountLinks->create([
                'account' => $account->stripe_account_id,
                'use_case' => [
                    'type' => 'account_onboarding',
                    'account_onboarding' => [
                        'refresh_url' => $refreshUrl,
                        'return_url' => $returnUrl,
                    ],
                ],
            ]);

            return $accountLink->url;
        } catch (Throwable) {
            // Standard Connect onboarding account link fallback
            $accountLink = $this->getClient()->accountLinks->create([
                'account' => $account->stripe_account_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;
        }
    }

    /**
     * Create a Stripe Express Dashboard login link for the connected account.
     */
    public function createDashboardLoginLink(Company $company): string
    {
        $account = $company->stripeConnectAccount;
        if (! $account || empty($account->stripe_account_id)) {
            throw new InvalidArgumentException('Workspace does not have a connected Stripe account.');
        }

        if (! $account->details_submitted) {
            throw new InvalidArgumentException('Stripe Connect onboarding must be completed before accessing the dashboard.');
        }

        $link = $this->getClient()->accounts->createLoginLink($account->stripe_account_id, []);

        return $link->url;
    }

    /**
     * Synchronize the connected account details directly from Stripe API.
     */
    public function syncAccount(Company $company, ?string $stripeAccountId = null): StripeConnectAccount
    {
        $targetAccountId = $stripeAccountId;
        $localAccount = $company->stripeConnectAccount;

        if (empty($targetAccountId)) {
            if (! $localAccount || empty($localAccount->stripe_account_id)) {
                throw new InvalidArgumentException('Workspace does not have a connected Stripe account to sync.');
            }
            $targetAccountId = $localAccount->stripe_account_id;
        }

        /** @var StripeAccount $stripeAccount */
        $stripeAccount = $this->getClient()->accounts->retrieve($targetAccountId, []);

        if (! $localAccount) {
            $localAccount = StripeConnectAccount::updateOrCreate(
                ['company_id' => $company->id],
                [
                    'stripe_account_id' => $stripeAccount->id,
                    'account_type' => (string) ($stripeAccount->type ?? 'express'),
                    'country' => (string) ($stripeAccount->country ?? 'US'),
                ]
            );
        }

        return $this->syncFromStripeAccountData($localAccount, $stripeAccount->toArray());
    }

    /**
     * Reconcile local account record with Stripe account data payload.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncFromStripeAccountData(StripeConnectAccount $account, array $data): StripeConnectAccount
    {
        $chargesEnabled = (bool) ($data['charges_enabled'] ?? false);
        $payoutsEnabled = (bool) ($data['payouts_enabled'] ?? false);
        $detailsSubmitted = (bool) ($data['details_submitted'] ?? false);
        $capabilities = $data['capabilities'] ?? [];
        $requirements = $data['requirements'] ?? [];
        $currency = strtoupper((string) ($data['default_currency'] ?? $account->currency ?? 'USD'));
        $country = strtoupper((string) ($data['country'] ?? $account->country ?? 'US'));

        // Determine normalized status
        $cardActive = ($capabilities['card_payments'] ?? null) === 'active';
        $disabledReason = $requirements['disabled_reason'] ?? null;
        $pastDue = ! empty($requirements['past_due']);

        if ($chargesEnabled && $detailsSubmitted && $cardActive && empty($disabledReason)) {
            $status = StripeConnectAccount::STATUS_ACTIVE;
        } elseif (! empty($disabledReason) || $pastDue) {
            $status = StripeConnectAccount::STATUS_RESTRICTED;
        } elseif ($detailsSubmitted) {
            $status = StripeConnectAccount::STATUS_PENDING;
        } else {
            $status = StripeConnectAccount::STATUS_PENDING;
        }

        $account->charges_enabled = $chargesEnabled;
        $account->payouts_enabled = $payoutsEnabled;
        $account->details_submitted = $detailsSubmitted;
        $account->capabilities = $capabilities;
        $account->requirements = $requirements;
        $account->currency = $currency;
        $account->country = $country;
        $account->status = $status;
        $account->last_synced_at = now();
        $account->save();

        return $account;
    }

    /**
     * Handle incoming account.updated webhook event.
     *
     * @param  array<string, mixed>  $accountData
     */
    public function handleAccountUpdated(array $accountData): ?StripeConnectAccount
    {
        $stripeAccountId = $accountData['id'] ?? null;
        if (! $stripeAccountId) {
            return null;
        }

        $account = StripeConnectAccount::query()->where('stripe_account_id', $stripeAccountId)->first();

        // If not found by stripe_account_id, try metadata.company_id
        if (! $account && ! empty($accountData['metadata']['company_id'])) {
            $company = Company::query()->find((int) $accountData['metadata']['company_id']);
            if ($company) {
                $account = StripeConnectAccount::updateOrCreate(
                    ['company_id' => $company->id],
                    [
                        'stripe_account_id' => $stripeAccountId,
                        'account_type' => (string) ($accountData['type'] ?? 'express'),
                        'country' => (string) ($accountData['country'] ?? 'US'),
                    ]
                );
            }
        }

        if (! $account) {
            return null;
        }

        return $this->syncFromStripeAccountData($account, $accountData);
    }

    /**
     * Handle incoming account.application.deauthorized webhook event.
     *
     * @param  array<string, mixed>  $accountData
     */
    public function handleAccountDeauthorized(array $accountData): ?StripeConnectAccount
    {
        $stripeAccountId = $accountData['id'] ?? ($accountData['account'] ?? null);
        if (! $stripeAccountId) {
            return null;
        }

        $account = StripeConnectAccount::query()->where('stripe_account_id', $stripeAccountId)->first();
        if (! $account) {
            return null;
        }

        $account->status = StripeConnectAccount::STATUS_DISABLED;
        $account->charges_enabled = false;
        $account->payouts_enabled = false;
        $account->details_submitted = false;
        $account->last_synced_at = now();
        $account->save();

        return $account;
    }

    /**
     * Safely disconnect or disable the Stripe Connect account.
     */
    public function disconnectAccount(Company $company): void
    {
        $account = $company->stripeConnectAccount;
        if (! $account) {
            return;
        }

        // Try deauthorizing / deleting in Stripe if possible
        if (! empty($account->stripe_account_id)) {
            try {
                $this->getClient()->accounts->delete($account->stripe_account_id, []);
            } catch (Throwable) {
                // If deletion is restricted in test/live mode, proceed to clear local association
            }
        }

        $account->status = StripeConnectAccount::STATUS_DISABLED;
        $account->charges_enabled = false;
        $account->payouts_enabled = false;
        $account->details_submitted = false;
        $account->last_synced_at = now();
        $account->save();
    }

    /**
     * Determine if a company is ready to accept invoice payments.
     */
    public function isReadyForPayments(Company $company): bool
    {
        return $company->stripeConnectAccount?->isReadyForPayments() ?? false;
    }

    /**
     * Get a comprehensive summary payload for API & frontend display.
     *
     * @return array<string, mixed>
     */
    public function getAccountSummary(Company $company): array
    {
        $account = $company->stripeConnectAccount;

        if (! $account || empty($account->stripe_account_id)) {
            return [
                'is_connected' => false,
                'status' => StripeConnectAccount::STATUS_NOT_CONNECTED,
                'status_label' => 'Not Connected',
                'is_ready_for_payments' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'details_submitted' => false,
                'account_id' => null,
                'country' => $company->country ?: 'US',
                'currency' => $company->currency ?: 'USD',
                'capabilities' => [],
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'eventually_due' => [],
                    'disabled_reason' => null,
                ],
                'last_synced_at' => null,
            ];
        }

        return [
            'is_connected' => true,
            'status' => $account->status,
            'status_label' => $account->status_label,
            'is_ready_for_payments' => $account->isReadyForPayments(),
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
            'details_submitted' => $account->details_submitted,
            'account_id' => $account->stripe_account_id,
            'country' => $account->country,
            'currency' => $account->currency,
            'capabilities' => $account->capabilities ?? [],
            'requirements' => [
                'currently_due' => $account->requirements['currently_due'] ?? [],
                'past_due' => $account->requirements['past_due'] ?? [],
                'eventually_due' => $account->requirements['eventually_due'] ?? [],
                'disabled_reason' => $account->requirements['disabled_reason'] ?? null,
            ],
            'last_synced_at' => $account->last_synced_at?->toIso8601String(),
        ];
    }
}
