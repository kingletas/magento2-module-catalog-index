<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Builds one document per active category under a store view's root.
 */
class CategoryDocumentBuilder
{
    public const string GROUP_PAGE = 'page';

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly FingerprintCalculator $fingerprints,
        private readonly CategoryProductCounts $counts
    ) {
    }

    /**
     * @param int[] $categoryIds
     */
    public function build(array $categoryIds, int $storeId, int $rootCategoryId, int $version): BuildBatch
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if ($categoryIds === []) {
            return new BuildBatch($version);
        }

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addIdFilter($categoryIds);
        $collection->addAttributeToSelect('*');
        $paths = $this->paths($categoryIds, $storeId);
        $this->counts->prepare($categoryIds);
        $documents = [];
        $built = [];

        foreach ($collection->getItems() as $category) {
            if (!$category instanceof Category || !$this->isPublished($category, $rootCategoryId)) {
                continue;
            }

            $categoryId = (int) $category->getId();
            $draft = new DocumentDraft($categoryId, $storeId);
            $draft->set('attributes', $this->attributes($category), self::GROUP_PAGE);
            $draft->set('request_path', $paths[$categoryId] ?? null, self::GROUP_PAGE);
            $draft->set('product_count', $this->counts->productCount($categoryId), self::GROUP_PAGE);
            $documents[] = $this->fingerprints->document($draft, $version);
            $built[] = $categoryId;
        }

        return new BuildBatch($version, $documents, array_values(array_diff($categoryIds, $built)));
    }

    private function isPublished(Category $category, int $rootCategoryId): bool
    {
        $path = explode('/', (string) $category->getPath());

        return (bool) $category->getIsActive() && in_array((string) $rootCategoryId, $path, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Category $category): array
    {
        $attributes = [];

        foreach ($category->getData() as $code => $value) {
            $code = (string) $code;

            if ($code !== '' && $code[0] !== '_' && ($value === null || is_scalar($value))) {
                $attributes[$code] = $value;
            }
        }

        ksort($attributes);

        return $attributes;
    }

    /**
     * @param int[] $categoryIds
     * @return array<int, string>
     */
    private function paths(array $categoryIds, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $pairs = $connection->fetchPairs(
            $connection->select()
                ->from($this->resourceConnection->getTableName('url_rewrite'), ['entity_id', 'request_path'])
                ->where('entity_type = ?', 'category')
                ->where('store_id = ?', $storeId)
                ->where('redirect_type = ?', 0)
                ->where('entity_id IN (?)', $categoryIds)
        );

        return array_map('strval', $pairs);
    }
}
