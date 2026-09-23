<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Index;

use DateTimeImmutable;
use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class IndexNamerTest extends TestCase
{
    use ShippedConfig;

    public function testAnAliasNamesThePrefixFamilyAndScope(): void
    {
        $namer = new IndexNamer($this->config(['connection/index_prefix' => 'Shop_A']));

        $this->assertSame('shop_a_product_3', $namer->alias(IndexFamily::Product, 3));
    }

    public function testABuildNameSortsAfterEarlierBuildsOfTheSameAlias(): void
    {
        $namer = new IndexNamer($this->config());
        $alias = $namer->alias(IndexFamily::Stock, 1);
        $first = $namer->buildName($alias, new DateTimeImmutable('2026-09-15 10:00:00'));
        $second = $namer->buildName($alias, new DateTimeImmutable('2026-09-15 10:00:01'));

        $this->assertStringStartsWith($namer->buildPrefix($alias), $first);
        $this->assertLessThan(0, strcmp($first, $second));
    }

    /**
     * The prefix becomes part of a URL path, so anything but a plain name is refused.
     */
    public function testAPrefixThatIsNotAPlainNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new IndexNamer($this->config(['connection/index_prefix' => '../_all'])))->prefix();
    }
}
