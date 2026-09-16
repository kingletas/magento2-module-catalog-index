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
 * Rating summary and review count, always set so the review renderer never looks them up one product at a time.
 */
class ReviewFieldProvider extends AbstractFieldProvider
{
    /** @var array<int, array{rating_summary: int, reviews_count: int}> */
    private array $summaries = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        int $sortOrder = 70
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContext $context): void
    {
        $this->summaries = [];

        if ($products === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();

        if (!$connection->isTableExists($this->resourceConnection->getTableName('review_entity_summary'))) {
            return;
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['s' => $this->resourceConnection->getTableName('review_entity_summary')],
                    ['entity_pk_value', 'rating_summary', 'reviews_count']
                )
                ->join(
                    ['t' => $this->resourceConnection->getTableName('review_entity')],
                    't.entity_id = s.entity_type',
                    []
                )
                ->where('t.entity_code = ?', 'product')
                ->where('s.store_id = ?', $context->storeId)
                ->where('s.entity_pk_value IN (?)', array_keys($products))
        );

        foreach ($rows as $row) {
            $this->summaries[(int) $row['entity_pk_value']] = [
                'rating_summary' => (int) $row['rating_summary'],
                'reviews_count' => (int) $row['reviews_count'],
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
        $this->summaries = [];
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        $summary = $this->summaries[(int) $product->getId()] ?? ['rating_summary' => 0, 'reviews_count' => 0];
        $draft->set('rating_summary', $summary['rating_summary'], DocumentDraft::GROUP_LISTING);
        $draft->set('reviews_count', $summary['reviews_count'], DocumentDraft::GROUP_LISTING);
    }
}
