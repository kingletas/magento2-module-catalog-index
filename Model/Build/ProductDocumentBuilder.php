<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;
use Kingletas\CatalogIndex\Api\FieldProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Builds product documents for one store view by loading the batch once and running every field provider over it.
 */
class ProductDocumentBuilder
{
    /** @var FieldProviderInterface[]|null */
    private ?array $sorted = null;

    /**
     * @param array<string, FieldProviderInterface> $providers
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly FingerprintCalculator $fingerprints,
        private readonly LoggerInterface $logger,
        private readonly array $providers = []
    ) {
        foreach ($this->providers as $key => $provider) {
            if (!$provider instanceof FieldProviderInterface) {
                throw new InvalidArgumentException((string) __(
                    'Field provider "%1" must implement %2, got %3.',
                    $key,
                    FieldProviderInterface::class,
                    get_debug_type($provider)
                ));
            }
        }
    }

    /**
     * @param int[] $productIds
     */
    public function build(array $productIds, BuildContextInterface $context): BuildBatch
    {
        $productIds = array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0);
        $productIds = array_values(array_unique($productIds));

        if ($productIds === []) {
            return new BuildBatch($context->getVersion());
        }

        $products = $this->load($productIds, $context);
        $providers = $this->providers();

        foreach ($providers as $provider) {
            $provider->prepareBatch($products, $context);
        }

        try {
            return $this->assemble($productIds, $products, $providers, $context);
        } finally {
            foreach ($providers as $provider) {
                $this->reset($provider);
            }
        }
    }

    /**
     * @param int[] $productIds
     * @param array<int, Product> $products
     * @param FieldProviderInterface[] $providers
     */
    private function assemble(
        array $productIds,
        array $products,
        array $providers,
        BuildContextInterface $context
    ): BuildBatch {
        $documents = [];
        $removed = array_values(array_diff($productIds, array_keys($products)));
        $moments = [];

        foreach ($products as $productId => $product) {
            $draft = new DocumentDraft($productId, $context->getStoreId());

            foreach ($providers as $provider) {
                $provider->contribute($product, $draft, $context);
            }

            if ($draft->isExcluded()) {
                $removed[] = $productId;

                continue;
            }

            $documents[] = $this->fingerprints->document($draft, $context->getVersion());

            if ($draft->refreshMoments() !== []) {
                $moments[$productId] = $draft->refreshMoments();
            }
        }

        sort($removed);

        return new BuildBatch($context->getVersion(), $documents, $removed, $moments);
    }

    /**
     * @param int[] $productIds
     * @return array<int, Product>
     */
    private function load(array $productIds, BuildContextInterface $context): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($context->getStoreId());
        $collection->addStoreFilter($context->getStoreId());
        $collection->addIdFilter($productIds);
        $collection->addAttributeToSelect('*');
        $collection->setFlag('has_stock_status_filter', true);

        $products = [];

        foreach ($collection->getItems() as $product) {
            if ($product instanceof Product) {
                $products[(int) $product->getId()] = $product;
            }
        }

        ksort($products);

        return $products;
    }

    /**
     * @return FieldProviderInterface[]
     */
    private function providers(): array
    {
        if ($this->sorted === null) {
            $sorted = array_values($this->providers);
            usort(
                $sorted,
                static fn (FieldProviderInterface $a, FieldProviderInterface $b): int
                    => $a->getSortOrder() <=> $b->getSortOrder()
            );
            $this->sorted = $sorted;
        }

        return $this->sorted;
    }

    private function reset(FieldProviderInterface $provider): void
    {
        try {
            $provider->resetBatch();
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Catalog index: %s could not release its batch.', get_debug_type($provider)),
                ['exception' => $e]
            );
        }
    }
}
