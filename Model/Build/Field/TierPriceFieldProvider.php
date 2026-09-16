<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Kingletas\CatalogIndex\Model\Build\LinkField;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;

/**
 * Tier prices in the shape the tier price backend loads, so a product page built from a document still shows them.
 */
class TierPriceFieldProvider extends AbstractFieldProvider
{
    private const int ALL_GROUPS = 32000;

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $tiers = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField,
        int $sortOrder = 60
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContext $context): void
    {
        $this->tiers = [];
        $link = $this->linkField->product();
        $linkIds = array_values(array_filter(array_map(
            static fn (Product $product): int => (int) $product->getData($link),
            $products
        )));

        if ($linkIds === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('catalog_product_entity_tier_price'))
                ->where(sprintf('%s IN (?)', $link), $linkIds)
                ->where('website_id IN (?)', [0, $context->websiteId])
                ->order(['qty ASC', 'value_id ASC'])
        );

        foreach ($rows as $row) {
            $allGroups = (int) $row['all_groups'] === 1;
            $this->tiers[(int) $row[$link]][] = [
                'price_id' => (int) $row['value_id'],
                'website_id' => (int) $row['website_id'],
                'all_groups' => (int) $allGroups,
                'cust_group' => $allGroups ? self::ALL_GROUPS : (int) $row['customer_group_id'],
                'price' => (float) $row['value'],
                'price_qty' => (float) $row['qty'],
                'percentage_value' => $row['percentage_value'] === null ? null : (float) $row['percentage_value'],
                'website_price' => (float) $row['value'],
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->tiers = [];
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $tiers = $this->tiers[(int) $product->getData($this->linkField->product())] ?? [];
        $draft->set('tier_price', $tiers, DocumentDraft::GROUP_DETAIL);
    }
}
