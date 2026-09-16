<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Store;

use Kingletas\CatalogIndex\Model\Store\WriteResult;
use PHPUnit\Framework\TestCase;

class WriteResultTest extends TestCase
{
    public function testMergingKeepsEveryOutcome(): void
    {
        $merged = (new WriteResult(['1'], ['2']))->merge(
            new WriteResult(['3'], [], ['4' => 'mapper_parsing_exception'])
        );

        $this->assertSame(['1', '3'], $merged->written);
        $this->assertSame(['2'], $merged->stale);
        $this->assertFalse($merged->isClean());
    }
}
