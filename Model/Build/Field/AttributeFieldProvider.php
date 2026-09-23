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
use Kingletas\CatalogIndex\Model\Build\StoredAttributes;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;

/**
 * Every attribute value the store view resolves, split into what listings show and what only the detail page shows.
 */
class AttributeFieldProvider extends AbstractFieldProvider
{
    /** @var array<string, true>|null */
    private ?array $listingCodes = null;

    /**
     * @param string[] $alwaysListing Codes listings render whether or not the admin marks them.
     */
    public function __construct(
        private readonly CatalogConfig $catalogConfig,
        private readonly StoredAttributes $storedAttributes,
        private readonly array $alwaysListing = [],
        int $sortOrder = 20
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraftInterface $draft, BuildContextInterface $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $split = [DocumentDraftInterface::GROUP_LISTING => [], DocumentDraftInterface::GROUP_DETAIL => []];
        $listingCodes = $this->listingCodes();

        foreach ($product->getData() as $code => $value) {
            $code = (string) $code;

            if ($this->isStorable($code, $value)) {
                $group = isset($listingCodes[$code])
                    ? DocumentDraftInterface::GROUP_LISTING
                    : DocumentDraftInterface::GROUP_DETAIL;
                $split[$group][$code] = $value;
            }
        }

        ksort($split[DocumentDraftInterface::GROUP_LISTING]);
        ksort($split[DocumentDraftInterface::GROUP_DETAIL]);
        $listing = DocumentDraftInterface::GROUP_LISTING;
        $detail = DocumentDraftInterface::GROUP_DETAIL;
        $draft->set('listing_attributes', $split[$listing], $listing);
        $draft->set('detail_attributes', $split[$detail], $detail);
    }

    private function isStorable(string $code, mixed $value): bool
    {
        if (!$this->storedAttributes->isStored($code)) {
            return false;
        }

        if (is_array($value)) {
            return array_is_list($value) && array_filter($value, 'is_scalar') === $value;
        }

        return $value === null || is_scalar($value);
    }

    /**
     * @return array<string, true>
     */
    private function listingCodes(): array
    {
        if ($this->listingCodes === null) {
            $codes = array_merge($this->catalogConfig->getProductAttributes(), $this->alwaysListing);
            $this->listingCodes = array_fill_keys(array_map('strval', $codes), true);
        }

        return $this->listingCodes;
    }
}
