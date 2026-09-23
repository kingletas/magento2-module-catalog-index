<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read\Configurable;

use Kingletas\CatalogIndex\Api\Data\ConfigurableViewInterface;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\ConfigurableView;
use PHPUnit\Framework\TestCase;

class ServedAttributesTest extends TestCase
{
    public function testTheEntityIdFindsTheView(): void
    {
        $served = new ServedAttributes();
        $view = $this->view();
        $served->remember($view, 5);

        $this->assertSame($view, $served->forProduct(5));
    }

    public function testAnUnusableIdIsNotRemembered(): void
    {
        $served = new ServedAttributes();
        $served->remember($this->view(), 0);

        $this->assertNull($served->forProduct(0));
    }

    public function testAProductNothingWasServedForFindsNothing(): void
    {
        $this->assertNull((new ServedAttributes())->forProduct(5));
    }

    /**
     * A document with no super attribute rows cannot build a collection, so it is not worth remembering.
     */
    public function testAViewWithNoSuperAttributesIsNotRemembered(): void
    {
        $served = new ServedAttributes();
        $served->remember(new ConfigurableView([]), 5);

        $this->assertNull($served->forProduct(5));
    }

    /**
     * A product that arrives already answered, and that this module was never asked about, went to another plugin.
     */
    public function testAnAnswerThisModuleNeverGaveIsAPreemptionOnce(): void
    {
        $served = new ServedAttributes();
        $served->remember($this->view(), 5);

        $this->assertTrue($served->notePreempted(5));
        $this->assertFalse($served->notePreempted(5));
    }

    /**
     * Magento keeps the collection this module built, so asking again finds it there and is not a pre-emption.
     */
    public function testAProductThisModuleWasAskedAboutIsNeverAPreemption(): void
    {
        $served = new ServedAttributes();
        $served->remember($this->view(), 5);
        $served->forProduct(5);

        $this->assertFalse($served->notePreempted(5));
    }

    public function testAProductWithNoDocumentIsNeverAPreemption(): void
    {
        $this->assertFalse((new ServedAttributes())->notePreempted(5));
    }

    private function view(): ConfigurableViewInterface
    {
        return new ConfigurableView(['super_attributes' => [['attribute_id' => 93, 'super_attribute_id' => 11]]]);
    }
}
