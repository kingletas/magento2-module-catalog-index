<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Store;

use Kingletas\CatalogIndex\Model\Store\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function testFieldsAndFingerprintsReadFromTheSource(): void
    {
        $document = new Document('7', 42, ['sku' => 'SKU-7', '_fp' => ['listing' => 'abc']]);

        $this->assertSame('7', $document->getId());
        $this->assertSame(42, $document->getVersion());
        $this->assertSame('SKU-7', $document->get('sku'));
        $this->assertSame('fallback', $document->get('missing', 'fallback'));
        $this->assertSame('abc', $document->getFingerprint('listing'));
        $this->assertNull($document->getFingerprint('detail'));
    }
}
