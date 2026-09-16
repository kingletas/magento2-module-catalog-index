<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model;

use Kingletas\Foundation\Model\Config\ModuleConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Every setting the catalog index reads.
 */
class Config extends ModuleConfig
{
    public const string MODE_QUEUE = 'queue';
    public const string MODE_INLINE = 'inline';
    public const string MODE_SCHEDULE = 'schedule';

    public const string STAGING_AUTO = 'auto';
    public const string STAGING_ENABLED = 'enabled';
    public const string STAGING_DISABLED = 'disabled';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        string $section,
        private readonly EncryptorInterface $encryptor
    ) {
        parent::__construct($scopeConfig, $section);
    }

    public function isEnabled(): bool
    {
        return $this->isSetFlag('general/enabled');
    }

    public function getIndexPrefix(): string
    {
        return $this->getString('connection/index_prefix', 'kingletas_catalog');
    }

    public function usesCatalogSearchConnection(): bool
    {
        return $this->isSetFlag('connection/use_catalog_search');
    }

    public function getHostname(): string
    {
        return $this->getString('connection/hostname', 'localhost');
    }

    public function getPort(): int
    {
        return $this->getPositiveInt('connection/port', 9200);
    }

    public function isHttps(): bool
    {
        return $this->isSetFlag('connection/https');
    }

    public function isAuthEnabled(): bool
    {
        return $this->isSetFlag('connection/enable_auth');
    }

    public function getUsername(): string
    {
        return $this->getString('connection/username');
    }

    public function getPassword(): string
    {
        $raw = $this->getString('connection/password');

        return $raw === '' ? '' : $this->encryptor->decrypt($raw);
    }

    public function getReadTimeoutMs(): int
    {
        return $this->getPositiveInt('connection/read_timeout_ms', 400);
    }

    public function getWriteTimeoutSeconds(): int
    {
        return $this->getPositiveInt('connection/write_timeout', 30);
    }

    public function getShards(): int
    {
        return $this->getPositiveInt('index/shards', 1);
    }

    public function getReplicas(): int
    {
        return max(0, $this->getInt('index/replicas', 0));
    }

    public function getKeepPreviousBuilds(): int
    {
        return max(0, $this->getInt('index/keep_previous', 1));
    }

    public function isCategoryListingEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/category_listing', $storeId);
    }

    public function isSearchListingEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/search_listing', $storeId);
    }

    public function isProductViewEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/product_view', $storeId);
    }

    public function isCategoryViewEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/category_view', $storeId);
    }

    public function isCategoryTreeEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/category_tree', $storeId);
    }

    public function isLinkedProductsEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/linked_products', $storeId);
    }

    public function isWidgetEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/widget', $storeId);
    }

    public function isGraphQlEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/graphql', $storeId);
    }

    public function isConfigurableOptionsEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/configurable_options', $storeId);
    }

    public function isConfigurableAttributesEnabled(int $storeId): bool
    {
        return $this->isSetFlag('read/configurable_attributes', $storeId);
    }

    public function getBreakerFailures(): int
    {
        return $this->getPositiveInt('read/breaker_failures', 5);
    }

    public function getBreakerCooldownSeconds(): int
    {
        return $this->getPositiveInt('read/breaker_cooldown', 30);
    }

    public function getUpdateMode(): string
    {
        $mode = $this->getString('updates/mode', self::MODE_QUEUE);

        return in_array($mode, [self::MODE_QUEUE, self::MODE_INLINE, self::MODE_SCHEDULE], true)
            ? $mode
            : self::MODE_QUEUE;
    }

    public function getInlineLimit(): int
    {
        return $this->getPositiveInt('updates/inline_limit', 50);
    }

    public function getBatchSize(): int
    {
        return $this->getPositiveInt('updates/batch_size', 200);
    }

    public function isPurgeEnabled(): bool
    {
        return $this->isSetFlag('purge/enabled');
    }

    public function getLowStockThreshold(): float
    {
        return max(0.0, $this->getFloat('purge/low_stock_threshold', 0.0));
    }

    public function getStagingMode(): string
    {
        $mode = $this->getString('staging/mode', self::STAGING_AUTO);

        return in_array($mode, [self::STAGING_AUTO, self::STAGING_ENABLED, self::STAGING_DISABLED], true)
            ? $mode
            : self::STAGING_AUTO;
    }

    public function isScheduleEnabled(): bool
    {
        return $this->isSetFlag('schedule/enabled');
    }

    public function getDriftSampleSize(): int
    {
        return $this->getPositiveInt('drift/sample_size', 200);
    }

    public function getDriftAlertRatio(): float
    {
        $ratio = $this->getFloat('drift/alert_ratio', 0.02);

        return $ratio > 0.0 && $ratio <= 1.0 ? $ratio : 0.02;
    }

    public function getFallbackBudget(): float
    {
        $ratio = $this->getFloat('metrics/fallback_budget', 0.05);

        return $ratio > 0.0 && $ratio <= 1.0 ? $ratio : 0.05;
    }
}
