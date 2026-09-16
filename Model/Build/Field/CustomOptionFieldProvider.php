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
 * Whether the product has custom options, which a document does not carry, so its page is never served from one.
 */
class CustomOptionFieldProvider extends AbstractFieldProvider
{
    /** @var array<int, true> Link ids with at least one option. */
    private array $withOptions = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField,
        int $sortOrder = 80
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContext $context): void
    {
        $this->withOptions = [];
        $link = $this->linkField->product();
        $linkIds = array_values(array_filter(array_map(
            static fn (Product $product): int => (int) $product->getData($link),
            $products
        )));

        if ($linkIds === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $ids = $connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from($this->resourceConnection->getTableName('catalog_product_option'), ['product_id'])
                ->where('product_id IN (?)', $linkIds)
        );

        foreach ($ids as $id) {
            $this->withOptions[(int) $id] = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->withOptions = [];
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $linkId = (int) $product->getData($this->linkField->product());
        $draft->set('has_custom_options', isset($this->withOptions[$linkId]), DocumentDraft::GROUP_INTERNAL);
    }
}
