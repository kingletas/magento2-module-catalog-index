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
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;

/**
 * The product's own storefront path, read from the rewrite table the store actually routes by.
 */
class UrlFieldProvider extends AbstractFieldProvider
{
    /** @var array<int, string> */
    private array $paths = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        int $sortOrder = 40
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContext $context): void
    {
        $this->paths = [];

        if ($products === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('url_rewrite'), ['entity_id', 'request_path'])
                ->where('entity_type = ?', 'product')
                ->where('store_id = ?', $context->storeId)
                ->where('redirect_type = ?', 0)
                ->where('metadata IS NULL')
                ->where('entity_id IN (?)', array_keys($products))
        );

        foreach ($rows as $row) {
            $this->paths[(int) $row['entity_id']] = (string) $row['request_path'];
        }
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->paths = [];
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $path = $this->paths[(int) $product->getId()] ?? null;

        if ($path !== null) {
            $draft->set('request_path', $path, DocumentDraft::GROUP_LISTING);
        }
    }
}
