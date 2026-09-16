<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;

/**
 * Decides once per request whether the product or category a page loads is served from its document.
 */
class DetailDocuments
{
    /** @var array<string, ProductView|false> */
    private array $products = [];

    /** @var array<string, CategoryView|false> */
    private array $categories = [];

    public function __construct(
        private readonly PageScope $scope,
        private readonly ReadGate $gate,
        private readonly DocumentReaderInterface $reader,
        private readonly ReadContextResolver $contexts,
        private readonly FallbackRecorder $recorder
    ) {
    }

    /**
     * Null means the page loads this product from the database, and the reason has already been counted.
     */
    public function product(int $productId, int $storeId): ?ProductView
    {
        if (!$this->scope->targets(PageType::ProductView, $productId)) {
            return null;
        }

        $context = $this->contexts->resolve(PageType::ProductView, $storeId);
        $key = $context->storeId . ':' . $productId;

        if (!array_key_exists($key, $this->products)) {
            $this->products[$key] = $this->readProduct($productId, $context) ?? false;
        }

        return $this->products[$key] ?: null;
    }

    public function category(int $categoryId, int $storeId): ?CategoryView
    {
        if (!$this->scope->targets(PageType::CategoryView, $categoryId)) {
            return null;
        }

        $context = $this->contexts->resolve(PageType::CategoryView, $storeId);
        $key = $context->storeId . ':' . $categoryId;

        if (!array_key_exists($key, $this->categories)) {
            $this->categories[$key] = $this->readCategory($categoryId, $context) ?? false;
        }

        return $this->categories[$key] ?: null;
    }

    private function readProduct(int $productId, ReadContext $context): ?ProductView
    {
        if (!$this->isAllowed($context)) {
            return null;
        }

        try {
            $view = $this->reader->products([$productId], $context)[$productId] ?? null;
        } catch (DocumentStoreException) {
            $this->recorder->fellBack($context->page, FallbackRecorder::REASON_STORE_ERROR);

            return null;
        }

        if ($view === null) {
            $this->recorder->fellBack($context->page, FallbackRecorder::REASON_MISSING);

            return null;
        }

        if (!$view->isDetailServable()) {
            $this->recorder->fellBack($context->page, FallbackRecorder::REASON_UNSUPPORTED);

            return null;
        }

        $this->recorder->served($context->page);

        return $view;
    }

    private function readCategory(int $categoryId, ReadContext $context): ?CategoryView
    {
        if (!$this->isAllowed($context)) {
            return null;
        }

        try {
            $view = $this->reader->categories([$categoryId], $context)[$categoryId] ?? null;
        } catch (DocumentStoreException) {
            $this->recorder->fellBack($context->page, FallbackRecorder::REASON_STORE_ERROR);

            return null;
        }

        if ($view === null) {
            $this->recorder->fellBack($context->page, FallbackRecorder::REASON_MISSING);

            return null;
        }

        $this->recorder->served($context->page);

        return $view;
    }

    private function isAllowed(ReadContext $context): bool
    {
        $decision = $this->gate->decide($context->page, $context->storeId);

        if ($decision === ReadDecision::BreakerOpen) {
            $this->recorder->fellBack($context->page, FallbackRecorder::REASON_BREAKER_OPEN);
        }

        return $decision === ReadDecision::Allow;
    }
}
