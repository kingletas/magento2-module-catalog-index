<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Configurable;

use Magento\ConfigurableProduct\Model\AttributeOptionProvider;
use Magento\ConfigurableProduct\Model\AttributeOptionProviderInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Answers a configurable's options from the document a page already read, and asks the database for anything else.
 */
class DocumentAttributeOptionProvider implements AttributeOptionProviderInterface
{
    public function __construct(
        private readonly AttributeOptionProvider $database,
        private readonly ServedOptions $served
    ) {
    }

    /**
     * Magento asks once per super attribute per product, which is the heaviest query a listing of configurables makes.
     *
     * @inheritDoc
     * @param int $productId
     * @return mixed[]
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function getAttributeOptions(AbstractAttribute $superAttribute, $productId): array
    {
        $rows = $this->served->rows((int) $productId, (int) $superAttribute->getAttributeId());

        if ($rows === null) {
            return $this->database->getAttributeOptions($superAttribute, $productId);
        }

        if (!$superAttribute->getSourceModel()) {
            return $rows;
        }

        return $this->titledFromSource($superAttribute, $rows);
    }

    /**
     * An attribute with a source model takes both of its titles from the source rather than from the option tables.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function titledFromSource(AbstractAttribute $superAttribute, array $rows): array
    {
        $labels = [];

        // The empty option a source adds when asked for all of them is keyed by '', so it matches no value index.
        foreach ($superAttribute->getSource()->getAllOptions() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        foreach ($rows as $key => $row) {
            unset($row['default_title'], $row['option_title']);
            $title = $labels[$row['value_index']] ?? false;
            $row['default_title'] = $title;
            $row['option_title'] = $title;
            $rows[$key] = $row;
        }

        return $rows;
    }
}
