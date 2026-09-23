<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
use WeakMap;

/**
 * Remembers which page a collection or entity load belongs to, so reads outside a marked page stay untouched.
 */
class PageScope
{
    /** @var WeakMap<object, PageType> */
    private WeakMap $marked;

    /** @var WeakMap<object, string[]> */
    private WeakMap $skipped;

    /** @var array<string, int> Page type value to the entity id that page is loading. */
    private array $targets = [];

    public function __construct()
    {
        $this->marked = new WeakMap();
        $this->skipped = new WeakMap();
    }

    /**
     * The first mark wins, so a collection reused by a second block keeps the page that created it.
     */
    public function mark(object $collection, PageType $page): void
    {
        if (!isset($this->marked[$collection])) {
            $this->marked[$collection] = $page;
        }
    }

    public function pageOf(object $collection): ?PageType
    {
        return $this->marked[$collection] ?? null;
    }

    /**
     * @param string[] $codes Attribute codes removed from the collection's select, to be filled from documents.
     */
    public function skip(object $collection, array $codes): void
    {
        $this->skipped[$collection] = $codes;
    }

    /**
     * Returns and forgets the codes skipped on a collection, so they are filled exactly once.
     *
     * @return string[]|null
     */
    public function takeSkipped(object $collection): ?array
    {
        $codes = $this->skipped[$collection] ?? null;
        unset($this->skipped[$collection]);

        return $codes;
    }

    /**
     * Only the entity the page asked for is served, never another one the same request happens to load.
     */
    public function enter(PageType $page, int $entityId): void
    {
        if ($entityId > 0) {
            $this->targets[$page->value] = $entityId;
        }
    }

    public function leave(PageType $page): void
    {
        unset($this->targets[$page->value]);
    }

    public function targets(PageType $page, int $entityId): bool
    {
        return $entityId > 0 && ($this->targets[$page->value] ?? null) === $entityId;
    }
}
