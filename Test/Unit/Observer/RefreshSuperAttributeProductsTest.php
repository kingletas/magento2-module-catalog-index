<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Observer;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Kingletas\CatalogIndex\Observer\RefreshSuperAttributeProducts;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

class RefreshSuperAttributeProductsTest extends TestCase
{
    use LinkFieldDouble;
    use StubbedDatabase;

    /** @var array<int, array{0: string, 1: int[], 2: string}> */
    private array $published = [];

    /**
     * An option label lives in the attribute, so no product row changes and the change log never reports it.
     */
    public function testSavingASwatchAttributeQueuesEveryConfigurableUsingIt(): void
    {
        $this->answers['catalog_product_super_attribute'] = ['5', '9'];

        $this->observer()->execute($this->event(['attribute_id' => '93']));

        $this->assertSame(
            [[IndexFamily::Product->value, [5, 9], RefreshRequest::REASON_ATTRIBUTE_SAVED]],
            $this->published
        );
    }

    public function testAnAttributeNoConfigurableUsesQueuesNothing(): void
    {
        $this->observer()->execute($this->event(['attribute_id' => '93']));

        $this->assertSame([], $this->published);
    }

    public function testAnEventWithoutAnAttributeAsksTheDatabaseNothing(): void
    {
        $this->observer()->execute($this->event([]));

        $this->assertSame([], $this->queries);
        $this->assertSame([], $this->published);
    }

    /**
     * @param array<string, string> $data
     */
    private function event(array $data): Observer
    {
        return new Observer(['event' => new Event(['attribute' => new DataObject($data)])]);
    }

    private function observer(): RefreshSuperAttributeProducts
    {
        $publisher = $this->createMock(RefreshPublisher::class);
        $publisher->method('publish')->willReturnCallback(
            function (IndexFamily $family, array $ids, string $reason): void {
                $this->published[] = [$family->value, $ids, $reason];
            }
        );

        return new RefreshSuperAttributeProducts($publisher, $this->resourceConnection(), $this->linkField());
    }
}
