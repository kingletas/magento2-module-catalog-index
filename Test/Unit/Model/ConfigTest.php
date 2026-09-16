<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model;

use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    use ShippedConfig;

    /**
     * Nothing a shopper sees changes on install: every page starts on the database.
     */
    public function testAFreshInstallReadsNothingFromDocuments(): void
    {
        $config = $this->config(['general/enabled' => '0']);

        $this->assertFalse($config->isEnabled());
        $this->assertFalse($config->isCategoryListingEnabled(1));
        $this->assertFalse($config->isSearchListingEnabled(1));
        $this->assertFalse($config->isProductViewEnabled(1));
        $this->assertFalse($config->isCategoryViewEnabled(1));
        $this->assertFalse($config->isLinkedProductsEnabled(1));
        $this->assertFalse($config->isWidgetEnabled(1));
        $this->assertFalse($config->isGraphQlEnabled(1));
    }

    public function testTheShippedDefaultsAreTheDocumentedOnes(): void
    {
        $config = $this->config();

        $this->assertSame(Config::MODE_QUEUE, $config->getUpdateMode());
        $this->assertSame(Config::STAGING_AUTO, $config->getStagingMode());
        $this->assertSame(400, $config->getReadTimeoutMs());
        $this->assertSame(30, $config->getWriteTimeoutSeconds());
        $this->assertSame(200, $config->getBatchSize());
        $this->assertSame(50, $config->getInlineLimit());
        $this->assertSame(5, $config->getBreakerFailures());
        $this->assertSame(30, $config->getBreakerCooldownSeconds());
        $this->assertSame(1, $config->getShards());
        $this->assertSame(0, $config->getReplicas());
        $this->assertSame(1, $config->getKeepPreviousBuilds());
        $this->assertTrue($config->isPurgeEnabled());
        $this->assertSame(0.0, $config->getLowStockThreshold());
        $this->assertTrue($config->isScheduleEnabled());
        $this->assertSame(200, $config->getDriftSampleSize());
        $this->assertSame(0.02, $config->getDriftAlertRatio());
        $this->assertSame(0.05, $config->getFallbackBudget());
        $this->assertSame('kingletas_catalog', $config->getIndexPrefix());
        $this->assertTrue($config->usesCatalogSearchConnection());
    }

    public function testAnUnknownModeFallsBackToTheSafeOne(): void
    {
        $config = $this->config(
            ['updates/mode' => 'whenever', 'staging/mode' => 'sometimes', 'drift/alert_ratio' => '7']
        );

        $this->assertSame(Config::MODE_QUEUE, $config->getUpdateMode());
        $this->assertSame(Config::STAGING_AUTO, $config->getStagingMode());
        $this->assertSame(0.02, $config->getDriftAlertRatio());
    }

    public function testTheOwnConnectionFieldsAreReadAndThePasswordDecrypted(): void
    {
        $config = $this->config([
            'connection/hostname' => 'search.example.test',
            'connection/port' => '9443',
            'connection/https' => '1',
            'connection/enable_auth' => '1',
            'connection/username' => 'reader',
            'connection/password' => 'cipher',
            'metrics/fallback_budget' => '0.1',
        ]);

        $this->assertSame('search.example.test', $config->getHostname());
        $this->assertSame(9443, $config->getPort());
        $this->assertTrue($config->isHttps());
        $this->assertTrue($config->isAuthEnabled());
        $this->assertSame('reader', $config->getUsername());
        $this->assertSame('decrypted:cipher', $config->getPassword());
        $this->assertSame(0.1, $config->getFallbackBudget());
    }
}
