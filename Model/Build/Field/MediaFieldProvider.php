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
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Zend_Db_Expr;

/**
 * The image gallery in the shape the product's gallery loader produces, resolved for the store view.
 */
class MediaFieldProvider extends AbstractFieldProvider
{
    /** @var array<int, array<int, array<string, mixed>>> Link id to value id to image. */
    private array $images = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField,
        private readonly EavConfig $eavConfig,
        int $sortOrder = 50
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContext $context): void
    {
        $this->images = [];
        $link = $this->linkField->product();
        $linkIds = array_values(array_filter(array_map(
            static fn (Product $product): int => (int) $product->getData($link),
            $products
        )));

        if ($linkIds === []) {
            return;
        }

        $attributeId = (int) $this->eavConfig->getAttribute(Product::ENTITY, 'media_gallery')->getId();

        foreach ($this->rows($linkIds, $link, $attributeId, $context->storeId) as $row) {
            $this->images[(int) $row['link_id']][(int) $row['value_id']] = [
                'value_id' => (string) $row['value_id'],
                'file' => (string) $row['file'],
                'media_type' => (string) $row['media_type'],
                'label' => $row['label'] === null ? null : (string) $row['label'],
                'position' => $row['position'] === null ? null : (string) $row['position'],
                'disabled' => (string) $row['disabled'],
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->images = [];
    }

    /**
     * @inheritDoc
     */
    /**
     * Magento renders these values into the gallery's own JSON, so they keep the string shape the database gives.
     *
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $images = $this->images[(int) $product->getData($this->linkField->product())] ?? [];
        uasort(
            $images,
            static fn (array $a, array $b): int => [(int) $a['position'], (int) $a['value_id']]
                <=> [(int) $b['position'], (int) $b['value_id']]
        );

        $draft->set('media_gallery', ['images' => $images, 'values' => []], DocumentDraft::GROUP_DETAIL);
    }

    /**
     * @param int[] $linkIds
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $linkIds, string $link, int $attributeId, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $gallery = $this->resourceConnection->getTableName('catalog_product_entity_media_gallery');
        $toEntity = $this->resourceConnection->getTableName('catalog_product_entity_media_gallery_value_to_entity');
        $values = $this->resourceConnection->getTableName('catalog_product_entity_media_gallery_value');
        $matchDefault = sprintf('d.value_id = g.value_id AND d.store_id = 0 AND d.%1$s = e.%1$s', $link);
        $matchStore = sprintf('s.value_id = g.value_id AND s.store_id = %2$d AND s.%1$s = e.%1$s', $link, $storeId);

        return $connection->fetchAll(
            $connection->select()
                ->from(['g' => $gallery], ['value_id', 'file' => 'value', 'media_type'])
                ->join(['e' => $toEntity], 'e.value_id = g.value_id', ['link_id' => $link])
                ->joinLeft(['d' => $values], $matchDefault, [])
                ->joinLeft(['s' => $values], $matchStore, [])
                ->columns([
                    'label' => new Zend_Db_Expr('COALESCE(s.label, d.label)'),
                    'position' => new Zend_Db_Expr('COALESCE(s.position, d.position)'),
                    'disabled' => new Zend_Db_Expr('COALESCE(s.disabled, d.disabled, 0)'),
                ])
                ->where('g.attribute_id = ?', $attributeId)
                ->where(sprintf('e.%s IN (?)', $link), $linkIds)
        );
    }
}
