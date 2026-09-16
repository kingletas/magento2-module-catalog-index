<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Api\FieldProviderInterface;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

/**
 * Runs a provider over a batch the way the builder does.
 */
abstract class FieldProviderTestCase extends TestCase
{
    use LinkFieldDouble;
    use ProductDoubles;
    use StubbedDatabase;

    /**
     * @param Product[] $products
     * @return array<int, DocumentDraft>
     */
    protected function runProvider(
        FieldProviderInterface $provider,
        array $products,
        ?BuildContext $context = null
    ): array {
        $context ??= $this->context();
        $keyed = [];

        foreach ($products as $product) {
            $keyed[(int) $product->getId()] = $product;
        }

        $provider->prepareBatch($keyed, $context);
        $drafts = [];

        foreach ($keyed as $id => $product) {
            $drafts[$id] = new DocumentDraft($id, $context->storeId);
            $provider->contribute($product, $drafts[$id], $context);
        }

        $provider->resetBatch();

        return $drafts;
    }

    protected function context(string $timezone = 'UTC'): BuildContext
    {
        return new BuildContext(1, 1, 10, new DateTimeImmutable('2026-09-15 12:00:00'), $timezone);
    }
}
