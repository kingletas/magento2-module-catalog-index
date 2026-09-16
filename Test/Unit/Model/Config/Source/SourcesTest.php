<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Config\Source;

use Kingletas\CatalogIndex\Model\Config\Source\StagingMode;
use Kingletas\CatalogIndex\Model\Config\Source\UpdateMode;
use PHPUnit\Framework\TestCase;

class SourcesTest extends TestCase
{
    public function testTheOptionsAreTheValuesConfigAccepts(): void
    {
        $this->assertSame(['queue', 'inline', 'schedule'], array_column((new UpdateMode())->toOptionArray(), 'value'));
        $this->assertSame(['auto', 'enabled', 'disabled'], array_column((new StagingMode())->toOptionArray(), 'value'));
    }
}
