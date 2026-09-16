<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Staging;

use Kingletas\CatalogIndex\Model\Staging\ScheduledVersionScanner;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class ScheduledVersionScannerTest extends TestCase
{
    use StubbedDatabase;

    public function testVersionsStartingOrEndingInTheWindowAreFound(): void
    {
        $this->answers[''] = ['5', '5', '6'];

        $this->assertSame([5, 6], (new ScheduledVersionScanner($this->resourceConnection()))->products(100, 160));
        $this->assertContains(100, $this->whereValues('created_in > ?'));
        $this->assertContains(160, $this->whereValues('updated_in <= ?'));
    }

    public function testAnInstallWithoutStagedColumnsOrAnEmptyWindowScansNothing(): void
    {
        $scanner = new ScheduledVersionScanner($this->resourceConnection());

        $this->assertSame([], $scanner->categories(160, 160));
        $this->tablesExist = false;
        $this->assertSame([], $scanner->categories(100, 160));
        $this->assertSame([], $this->queries);
    }
}
