<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Kingletas\CatalogIndex\Api\CachePurgerInterface;

/**
 * Remembers every tag it was asked to purge.
 */
class RecordingPurger implements CachePurgerInterface
{
    /** @var array<int, string[]> */
    public array $purges = [];

    public function purge(array $tags): void
    {
        if ($tags !== []) {
            $this->purges[] = array_values($tags);
        }
    }

    /**
     * @return string[]
     */
    public function tags(): array
    {
        $tags = array_values(array_unique(array_merge([], ...$this->purges)));
        sort($tags);

        return $tags;
    }
}
