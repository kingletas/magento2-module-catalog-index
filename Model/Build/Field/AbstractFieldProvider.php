<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build\Field;

use Kingletas\CatalogIndex\Api\FieldProviderInterface;
use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;

/**
 * A provider with nothing to prepare or release.
 */
abstract class AbstractFieldProvider implements FieldProviderInterface
{
    public function __construct(
        private readonly int $sortOrder = 100
    ) {
    }

    /**
     * @inheritDoc
     */
    public function prepareBatch(array $products, BuildContextInterface $context): void
    {
    }

    /**
     * @inheritDoc
     */
    public function resetBatch(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
