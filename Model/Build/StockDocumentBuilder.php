<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Stock\StockLevel;
use Kingletas\CatalogIndex\Model\Stock\StockReaderPool;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;

/**
 * Builds one stock document per product and website, including whether each variant can be bought.
 */
class StockDocumentBuilder
{
    public const string GROUP_SALABLE = 'salable';
    public const string GROUP_LEVEL = 'level';
    public const string GROUP_VARIANTS = 'variants';

    public function __construct(
        private readonly StockReaderPool $readers,
        private readonly AffectedProductResolver $relations,
        private readonly FingerprintCalculator $fingerprints,
        private readonly Config $config
    ) {
    }

    /**
     * @param int[] $productIds
     */
    public function build(array $productIds, int $websiteId, int $version): BuildBatch
    {
        if ($productIds === []) {
            return new BuildBatch($version);
        }

        $children = $this->relations->childrenOf($productIds);
        $allIds = array_values(array_unique(array_merge($productIds, ...array_values($children))));
        $levels = $this->readers->reader()->read($allIds, $websiteId);
        $threshold = $this->config->getLowStockThreshold();
        $documents = [];
        $removed = [];

        foreach ($productIds as $productId) {
            $level = $levels[$productId] ?? null;

            if ($level === null) {
                $removed[] = $productId;

                continue;
            }

            $draft = new DocumentDraft($productId, $websiteId);
            $draft->set('is_salable', $level->isSalable, self::GROUP_SALABLE);
            $draft->set('qty', $level->quantity, DocumentDraft::GROUP_INTERNAL);
            $draft->set('salable_qty', $level->salableQuantity, DocumentDraft::GROUP_INTERNAL);
            $draft->set('stock_id', $level->stockId, DocumentDraft::GROUP_INTERNAL);
            $draft->set('low_stock', $threshold > 0 && $level->salableQuantity <= $threshold, self::GROUP_LEVEL);
            $draft->set('children', $this->childSalability($children[$productId] ?? [], $levels), self::GROUP_VARIANTS);
            $documents[] = $this->fingerprints->document($draft, $version);
        }

        return new BuildBatch($version, $documents, $removed);
    }

    /**
     * @param int[] $childIds
     * @param array<int, StockLevel> $levels
     * @return array<int, bool>
     */
    private function childSalability(array $childIds, array $levels): array
    {
        $salability = [];
        sort($childIds);

        foreach ($childIds as $childId) {
            $salability[$childId] = isset($levels[$childId]) && $levels[$childId]->isSalable;
        }

        return $salability;
    }
}
