<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Repositories\SettingsRepository;

/**
 * CurrencyService
 *
 * Centralises company-currency resolution used across handlers.
 * Replaces the duplicated getCompanyCurrency() private methods that existed
 * independently in SalesHandler, PurchasesHandler, ReturnsHandler,
 * CashVouchersHandler, CustomersHandler, and SuppliersHandler.
 *
 * Now delegates to SettingsRepository (single source of truth for settings).
 */
class CurrencyService
{
    private SettingsRepository $settingsRepo;

    public function __construct(PDO $db)
    {
        $this->settingsRepo = new SettingsRepository($db);
    }

    /**
     * Returns the ISO currency code configured for the given tenant.
     *
     * @param int $tenantId
     * @return string  e.g. 'EGP', 'USD', 'SAR'
     */
    public function getCompanyCurrency(int $tenantId): string
    {
        return $this->settingsRepo->get($tenantId, 'company.currency', 'EGP') ?: 'EGP';
    }
}
