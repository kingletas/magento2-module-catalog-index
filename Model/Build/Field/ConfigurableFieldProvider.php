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
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;

/**
 * A configurable product's super attributes and option rows, loaded once per batch.
 */
class ConfigurableFieldProvider extends AbstractFieldProvider
{
    /** @var array<int, array<int, array<string, mixed>>> Parent link id to attribute id to its super attribute row. */
    private array $superAttributes = [];

    /** @var array<int, array<string, array<int, array<string, mixed>>>> Parent link id to attribute id to rows. */
    private array $options = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField,
        private readonly EavConfig $eavConfig,
        int $sortOrder = 65
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContext $context): void
    {
        $this->resetBatch();
        $link = $this->linkField->product();
        $parentLinkIds = [];

        foreach ($products as $product) {
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                $parentLinkIds[] = (int) $product->getData($link);
            }
        }

        if ($parentLinkIds === []) {
            return;
        }

        $this->loadSuperAttributes($parentLinkIds, $context);
        $this->loadOptions($parentLinkIds, $context);
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->superAttributes = [];
        $this->options = [];
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded() || $product->getTypeId() !== Configurable::TYPE_CODE) {
            return;
        }

        $linkId = (int) $product->getData($this->linkField->product());
        $superAttributes = array_values($this->superAttributes[$linkId] ?? []);
        $draft->set('super_attributes', $superAttributes, DocumentDraft::GROUP_LISTING);
        $draft->set('configurable_options', $this->options[$linkId] ?? [], DocumentDraft::GROUP_LISTING);
    }

    /**
     * The rows Magento's own option query returns, for a whole batch instead of one attribute of one product.
     *
     * @param int[] $parentLinkIds
     */
    private function loadOptions(array $parentLinkIds, BuildContext $context): void
    {
        foreach ($this->backendTables($parentLinkIds) as $table => $attributeIds) {
            $select = $this->optionSelect($parentLinkIds, $attributeIds, (string) $table, $context);

            foreach ($this->resourceConnection->getConnection()->fetchAll($select) as $row) {
                $parent = (int) $row['parent_link_id'];
                $attributeId = (string) $row['attribute_id'];
                unset($row['parent_link_id'], $row['attribute_id']);
                $this->options[$parent][$attributeId][] = $row;
            }
        }
    }

    /**
     * Super attributes are select attributes, so in practice this is one table and one query.
     *
     * @param int[] $parentLinkIds
     * @return array<string, int[]>
     */
    private function backendTables(array $parentLinkIds): array
    {
        $tables = [];

        foreach ($parentLinkIds as $linkId) {
            foreach (array_keys($this->superAttributes[$linkId] ?? []) as $attributeId) {
                $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeId);
                $tables[(string) $attribute->getBackendTable()][$attributeId] = $attributeId;
            }
        }

        return array_map('array_values', $tables);
    }

    /**
     * @param int[] $parentLinkIds
     * @param int[] $attributeIds
     */
    private function optionSelect(
        array $parentLinkIds,
        array $attributeIds,
        string $table,
        BuildContext $context
    ): Select {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['super_attribute' => $this->resourceConnection->getTableName('catalog_product_super_attribute')],
                [
                    'parent_link_id' => 'super_attribute.product_id',
                    'attribute_id' => 'super_attribute.attribute_id',
                    'sku' => 'entity.sku',
                    'product_id' => 'product_entity.entity_id',
                    'attribute_code' => 'attribute.attribute_code',
                    'value_index' => 'entity_value.value',
                    'super_attribute_label' => 'attribute_label.value',
                    'option_title' => $connection->getIfNullSql('option_value.value', 'default_option_value.value'),
                    'default_title' => 'default_option_value.value',
                ]
            )
            // Magento leaves ties to MySQL, and a document has to come out the same on every rebuild.
            ->order(['attribute_option.sort_order ASC', 'entity.entity_id ASC'])
            ->where('super_attribute.product_id IN (?)', $parentLinkIds)
            ->where('super_attribute.attribute_id IN (?)', $attributeIds);

        return $this->joinOptionTables($select, $table, $context);
    }

    private function joinOptionTables(Select $select, string $table, BuildContext $context): Select
    {
        $link = $this->linkField->product();
        $entity = $this->resourceConnection->getTableName('catalog_product_entity');
        $optionValue = $this->resourceConnection->getTableName('eav_attribute_option_value');

        return $select
            ->joinInner(['product_entity' => $entity], "product_entity.{$link} = super_attribute.product_id", [])
            ->joinInner(
                ['product_link' => $this->resourceConnection->getTableName('catalog_product_super_link')],
                'product_link.parent_id = super_attribute.product_id',
                []
            )
            ->joinInner(
                ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                'attribute.attribute_id = super_attribute.attribute_id',
                []
            )
            ->joinInner(['entity' => $entity], 'entity.entity_id = product_link.product_id', [])
            // Magento's own storefront plugin filters these rows to the website, so the document has to as well.
            ->joinInner(
                ['entity_website' => $this->resourceConnection->getTableName('catalog_product_website')],
                'entity_website.product_id = entity.entity_id AND entity_website.website_id = '
                . $context->websiteId,
                []
            )
            ->joinInner(
                ['entity_value' => $table],
                implode(' AND ', [
                    'entity_value.attribute_id = super_attribute.attribute_id',
                    'entity_value.store_id = ' . Store::DEFAULT_STORE_ID,
                    "entity_value.{$link} = entity.{$link}",
                ]),
                []
            )
            ->joinLeft(
                ['attribute_label' => $this->resourceConnection->getTableName(
                    'catalog_product_super_attribute_label'
                )],
                implode(' AND ', [
                    'super_attribute.product_super_attribute_id = attribute_label.product_super_attribute_id',
                    'attribute_label.store_id = ' . Store::DEFAULT_STORE_ID,
                ]),
                []
            )
            ->joinLeft(
                ['attribute_option' => $this->resourceConnection->getTableName('eav_attribute_option')],
                'attribute_option.option_id = entity_value.value',
                []
            )
            ->joinLeft(
                ['option_value' => $optionValue],
                'option_value.option_id = entity_value.value AND option_value.store_id = ' . $context->storeId,
                []
            )
            ->joinLeft(
                ['default_option_value' => $optionValue],
                'default_option_value.option_id = entity_value.value AND default_option_value.store_id = '
                . Store::DEFAULT_STORE_ID,
                []
            );
    }

    /**
     * One query for every parent in the batch, carrying the store label Magento asks for separately per product.
     *
     * @param int[] $parentLinkIds
     */
    private function loadSuperAttributes(array $parentLinkIds, BuildContext $context): void
    {
        $connection = $this->resourceConnection->getConnection();
        $labels = $this->resourceConnection->getTableName('catalog_product_super_attribute_label');
        $select = $connection->select()
            ->from(
                ['main_table' => $this->resourceConnection->getTableName('catalog_product_super_attribute')],
                ['product_id', 'attribute_id', 'position', 'product_super_attribute_id']
            )
            ->joinLeft(
                ['def' => $labels],
                'def.product_super_attribute_id = main_table.product_super_attribute_id AND def.store_id = '
                . Store::DEFAULT_STORE_ID,
                []
            )
            ->joinLeft(
                ['store' => $labels],
                'store.product_super_attribute_id = main_table.product_super_attribute_id AND store.store_id = '
                . $context->storeId,
                [
                    'use_default' => $connection->getCheckSql(
                        'store.use_default IS NULL',
                        'def.use_default',
                        'store.use_default'
                    ),
                    'label' => $connection->getCheckSql('store.value IS NULL', 'def.value', 'store.value'),
                ]
            )
            ->where('main_table.product_id IN (?)', $parentLinkIds)
            ->order(['main_table.position ASC', 'main_table.attribute_id ASC']);

        foreach ($connection->fetchAll($select) as $row) {
            $attributeId = (int) $row['attribute_id'];
            $this->superAttributes[(int) $row['product_id']][$attributeId] = [
                'attribute_id' => $attributeId,
                'code' => (string) $this->eavConfig->getAttribute(Product::ENTITY, $attributeId)->getAttributeCode(),
                'position' => (int) $row['position'],
                'super_attribute_id' => (int) $row['product_super_attribute_id'],
                'label' => $row['label'] === null ? null : (string) $row['label'],
                'use_default' => $row['use_default'] === null ? null : (int) $row['use_default'],
            ];
        }
    }
}
