<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class ReadGateTest extends TestCase
{
    use ShippedConfig;

    public function testEachPageHasItsOwnSwitch(): void
    {
        $pages = [
            'category_listing' => PageType::CategoryListing,
            'search_listing' => PageType::SearchListing,
            'product_view' => PageType::ProductView,
            'category_view' => PageType::CategoryView,
            'linked_products' => PageType::LinkedProducts,
            'widget' => PageType::Widget,
            'graphql' => PageType::GraphQl,
        ];

        foreach ($pages as $field => $page) {
            $gate = new ReadGate($this->config(['pages/' . $field => '1']), $this->breaker(false));

            foreach ($pages as $other) {
                $this->assertSame(
                    $other === $page ? ReadDecision::Allow : ReadDecision::Disabled,
                    $gate->decide($other, 1),
                    $field . ' should only switch on ' . $page->value
                );
            }
        }
    }

    public function testAnOpenBreakerOrADisabledModuleStopsReads(): void
    {
        $this->assertSame(
            ReadDecision::BreakerOpen,
            (new ReadGate($this->config(['pages/widget' => '1']), $this->breaker(true)))->decide(PageType::Widget, 1)
        );
        $this->assertSame(
            ReadDecision::Disabled,
            (
                new ReadGate($this->config(['pages/widget' => '1', 'general/enabled' => '0']), $this->breaker(false))
            )->decide(PageType::Widget, 1)
        );
    }

    private function breaker(bool $open): CircuitBreaker
    {
        $breaker = $this->createMock(CircuitBreaker::class);
        $breaker->method('isOpen')->willReturn($open);

        return $breaker;
    }
}
