<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\CategoryViewInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Api\Data\ReadContextInterface;
use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Api\DocumentStoreInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Store\DocumentSchema;

/**
 * Reads product, price and stock documents for a page in a single round trip.
 */
class DocumentReader implements DocumentReaderInterface
{
    /** @var array<string, array<int, CategoryViewInterface|null>> Index alias to category id to what was read, or null. */
    private array $categoriesRead = [];

    public function __construct(
        private readonly DocumentStoreInterface $store,
        private readonly IndexNamer $namer,
        private readonly CircuitBreaker $breaker,
        private readonly DocumentSchema $schema
    ) {
    }

    /**
     * @inheritDoc
     */
    public function products(array $productIds, ReadContextInterface $context): array
    {
        $ids = array_values(array_unique(array_map('strval', array_map('intval', $productIds))));

        if ($ids === []) {
            return [];
        }

        $products = $this->namer->alias(IndexFamily::Product, $context->getStoreId());
        $prices = $this->namer->alias(IndexFamily::Price, $context->getWebsiteId());
        $stock = $this->namer->alias(IndexFamily::Stock, $context->getWebsiteId());
        $found = $this->fetch([$products => $ids, $prices => $ids, $stock => $ids]);
        $views = [];

        foreach ($ids as $id) {
            $document = $found[$products][$id] ?? null;

            if ($document === null) {
                continue;
            }

            $groups = isset($found[$prices][$id]) ? (array) $found[$prices][$id]->get('groups', []) : [];
            $price = $groups[(string) $context->getCustomerGroupId()] ?? null;
            $views[(int) $id] = new ProductView(
                (int) $id,
                $document->getSource(),
                is_array($price) ? $price : null,
                isset($found[$stock][$id]) ? $found[$stock][$id]->getSource() : null
            );
        }

        return $views;
    }

    /**
     * @inheritDoc
     */
    public function categories(array $categoryIds, ReadContextInterface $context): array
    {
        $ids = array_values(array_unique(array_map('strval', array_map('intval', $categoryIds))));

        if ($ids === []) {
            return [];
        }

        $index = $this->namer->alias(IndexFamily::Category, $context->getStoreId());
        $this->readCategories($index, $ids);
        $views = [];

        foreach ($ids as $id) {
            $view = $this->categoriesRead[$index][(int) $id] ?? null;

            if ($view !== null) {
                $views[(int) $id] = $view;
            }
        }

        return $views;
    }

    /**
     * A page walks the same categories again for its menu, its breadcrumb and each node's children.
     *
     * @param string[] $ids
     * @throws DocumentStoreException
     */
    private function readCategories(string $index, array $ids): void
    {
        $wanted = array_values(array_filter(
            $ids,
            fn (string $id): bool => !array_key_exists((int) $id, $this->categoriesRead[$index] ?? [])
        ));

        if ($wanted === []) {
            return;
        }

        $found = $this->fetch([$index => $wanted])[$index] ?? [];

        foreach ($wanted as $id) {
            $document = $found[$id] ?? null;
            $this->categoriesRead[$index][(int) $id] = $document === null
                ? null
                : new CategoryView((int) $id, $document->getSource());
        }
    }

    /**
     * @param array<string, string[]> $idsByIndex
     * @return array<string, array<string, DocumentInterface>>
     * @throws DocumentStoreException
     */
    private function fetch(array $idsByIndex): array
    {
        try {
            $found = $this->store->fetch($idsByIndex);
        } catch (DocumentStoreException $e) {
            $this->breaker->recordFailure();

            throw $e;
        }

        $this->breaker->recordSuccess();

        return $this->currentOnly($found);
    }

    /**
     * A document written in an older shape is left out, so the page reads the database until a rebuild.
     *
     * @param array<string, array<string, DocumentInterface>> $found
     * @return array<string, array<string, DocumentInterface>>
     */
    private function currentOnly(array $found): array
    {
        foreach ($found as $index => $documents) {
            $found[$index] = array_filter(
                $documents,
                fn (DocumentInterface $document): bool => $this->schema->isCurrent($document)
            );
        }

        return $found;
    }
}
