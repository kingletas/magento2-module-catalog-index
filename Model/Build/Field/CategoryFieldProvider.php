<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build\Field;

use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;

/**
 * The categories a product sits in and its position in each, which decide which category pages a change purges.
 */
class CategoryFieldProvider extends AbstractFieldProvider
{
    /** @var array<int, array<int, int>> Product id to category id to position. */
    private array $positions = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        int $sortOrder = 30
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContextInterface $context): void
    {
        $this->positions = [];

        if ($products === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('catalog_category_product'),
                    ['product_id', 'category_id', 'position']
                )
                ->where('product_id IN (?)', array_keys($products))
        );

        foreach ($rows as $row) {
            $this->positions[(int) $row['product_id']][(int) $row['category_id']] = (int) $row['position'];
        }
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->positions = [];
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraftInterface $draft, BuildContextInterface $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $positions = $this->positions[(int) $product->getId()] ?? [];
        ksort($positions);

        $draft->set('category_ids', array_keys($positions), DocumentDraftInterface::GROUP_INTERNAL);
        $draft->set('category_positions', $positions, DocumentDraftInterface::GROUP_INTERNAL);
    }
}
