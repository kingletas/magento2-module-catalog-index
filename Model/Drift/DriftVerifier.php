<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Drift;

use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Api\DocumentStoreInterface;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Update\ProductRefresher;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Zend_Db_Expr;

/**
 * Rebuilds a sample of product documents in memory and compares them with what is stored, repairing any that differ.
 */
class DriftVerifier
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeResolver $scopes,
        private readonly IndexNamer $namer,
        private readonly DocumentStoreInterface $store,
        private readonly ProductDocumentBuilder $builder,
        private readonly ProductRefresher $refresher,
        private readonly ResourceConnection $resourceConnection,
        private readonly VersionSource $versions,
        private readonly ClockInterface $clock,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return DriftReport[]
     */
    public function verify(?int $sampleSize = null, bool $repair = true): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $reports = [];

        foreach ($this->scopes->storeIds() as $storeId) {
            $size = $sampleSize ?? $this->config->getDriftSampleSize();
            $ids = $this->sample($this->scopes->websiteIdOf($storeId), $size);
            $drifted = $this->compare($ids, $storeId);

            if ($repair && $drifted !== []) {
                $this->refresher->refresh($drifted);
            }

            $reports[] = new DriftReport($storeId, count($ids), $drifted, $repair && $drifted !== []);
        }

        return $reports;
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function compare(array $ids, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }

        $context = new BuildContext(
            $storeId,
            $this->scopes->websiteIdOf($storeId),
            $this->versions->next(),
            $this->clock->now(),
            (string) $this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, (string) $storeId)
        );
        $batch = $this->builder->build($ids, $context);
        $alias = $this->namer->alias(IndexFamily::Product, $storeId);
        $fetched = $this->store->fetch([$alias => array_map('strval', $ids)], [DocumentInterface::FINGERPRINTS]);
        $stored = $fetched[$alias] ?? [];
        $drifted = array_map('intval', array_intersect(array_map('strval', $batch->removedIds), array_keys($stored)));

        foreach ($batch->documents as $document) {
            if ($this->differs($document, $stored[$document->getId()] ?? null)) {
                $drifted[] = (int) $document->getId();
            }
        }

        sort($drifted);

        return array_values(array_unique($drifted));
    }

    private function differs(DocumentInterface $built, ?DocumentInterface $stored): bool
    {
        if ($stored === null) {
            return true;
        }

        $groups = array_keys((array) $built->get(DocumentInterface::FINGERPRINTS, []));

        foreach ($groups as $group) {
            if ($built->getFingerprint((string) $group) !== $stored->getFingerprint((string) $group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int[] A contiguous run of products starting at a random point, wrapping to the start.
     */
    private function sample(int $websiteId, int $size): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_website');
        $bounds = $connection->fetchRow(
            $connection->select()
                ->from($table, [
                    'low' => new Zend_Db_Expr('MIN(product_id)'),
                    'high' => new Zend_Db_Expr('MAX(product_id)'),
                ])
                ->where('website_id = ?', $websiteId)
        );

        if (!is_array($bounds) || $bounds['high'] === null) {
            return [];
        }

        $start = random_int((int) $bounds['low'], (int) $bounds['high']);
        $ids = $this->run($table, $websiteId, $start, $size);

        if (count($ids) < $size) {
            $ids = array_merge($ids, $this->run($table, $websiteId, 0, $size - count($ids)));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return int[]
     */
    private function run(string $table, int $websiteId, int $from, int $size): array
    {
        $connection = $this->resourceConnection->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($table, ['product_id'])
                ->where('website_id = ?', $websiteId)
                ->where('product_id >= ?', $from)
                ->order('product_id ASC')
                ->limit($size)
        ));
    }
}
