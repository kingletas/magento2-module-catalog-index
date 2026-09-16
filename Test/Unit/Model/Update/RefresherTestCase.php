<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Model\Update\DocumentWriter;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\RecordingPurger;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The shared world a refresher runs in: two store views on one website, an in-memory store and a purge recorder.
 */
abstract class RefresherTestCase extends TestCase
{
    use ShippedConfig;

    protected InMemoryDocumentStore $store;

    protected RecordingPurger $purger;

    protected FakeClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryDocumentStore();
        $this->purger = new RecordingPurger();
        $this->clock = new FakeClock();
    }

    protected function scopes(): ScopeResolver
    {
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('storeIds')->willReturn([1, 2]);
        $scopes->method('websiteIds')->willReturn([1]);
        $scopes->method('websiteIdOf')->willReturn(1);
        $scopes->method('rootCategoryOf')->willReturn(2);

        return $scopes;
    }

    protected function writer(): DocumentWriter
    {
        return new DocumentWriter($this->store, new NullLogger());
    }

    protected function namer(): IndexNamer
    {
        return new IndexNamer($this->config());
    }

    protected function versions(): VersionSource
    {
        return new VersionSource($this->clock);
    }

    /**
     * @param array<int, int[]> $parents
     * @param array<int, int[]> $categories
     */
    protected function relations(array $parents = [], array $categories = []): AffectedProductResolver
    {
        $relations = $this->createMock(AffectedProductResolver::class);
        $relations->method('withParents')->willReturnCallback(static function (array $ids) use ($parents): array {
            $groups = [$ids];

            foreach ($ids as $id) {
                $groups[] = $parents[$id] ?? [];
            }

            $all = array_values(array_unique(array_map('intval', array_merge(...$groups))));
            sort($all);

            return $all;
        });
        $relations->method('categoriesOf')->willReturnCallback(
            static fn (array $ids): array => array_intersect_key($categories, array_flip($ids))
        );

        return $relations;
    }
}
