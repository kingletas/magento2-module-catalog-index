<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read\Configurable;

use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use PHPUnit\Framework\TestCase;

class ServedOptionsTest extends TestCase
{
    public function testNothingIsServedUntilSomethingIsRemembered(): void
    {
        $this->assertNull((new ServedOptions())->rows(5, 93));
    }

    public function testRowsAreFoundByTheLinkFieldValueTheyWereRememberedUnder(): void
    {
        $served = new ServedOptions();
        $served->remember(['93' => [['value_index' => '49']]], 7);

        $this->assertSame([['value_index' => '49']], $served->rows(7, 93));
        $this->assertNull($served->rows(5, 93));
    }

    public function testAnEmptyDocumentRemembersNothingRatherThanAnEmptyAnswer(): void
    {
        $served = new ServedOptions();
        $served->remember([], 5);

        $this->assertNull($served->rows(5, 93));
    }

    public function testAnUnusableIdIsNotRemembered(): void
    {
        $served = new ServedOptions();
        $served->remember(['93' => [['value_index' => '49']]], 0);

        $this->assertNull($served->rows(0, 93));
    }
}
