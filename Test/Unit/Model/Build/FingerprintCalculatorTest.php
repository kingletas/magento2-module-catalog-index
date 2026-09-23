<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Kingletas\CatalogIndex\Model\Store\DocumentSchema;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use PHPUnit\Framework\TestCase;

class FingerprintCalculatorTest extends TestCase
{
    /**
     * A provider that builds the same map in another order must not look like a change and purge pages.
     */
    public function testKeyOrderDoesNotChangeAFingerprint(): void
    {
        $calculator = new FingerprintCalculator();

        $this->assertSame(
            $calculator->fingerprint(['a' => 1, 'b' => ['y' => 2, 'x' => 1]]),
            $calculator->fingerprint(['b' => ['x' => 1, 'y' => 2], 'a' => 1])
        );
        $this->assertNotSame(
            $calculator->fingerprint(['list' => [1, 2]]),
            $calculator->fingerprint(['list' => [2, 1]])
        );
    }

    public function testADocumentCarriesAFingerprintPerGroup(): void
    {
        $draft = new DocumentDraft(7, 1);
        $draft->set('name', 'Trail Jacket', DocumentDraftInterface::GROUP_LISTING);
        $draft->set('updated_at', 'now', DocumentDraftInterface::GROUP_INTERNAL);

        $document = (new FingerprintCalculator())->document($draft, 99);

        $this->assertSame('7', $document->getId());
        $this->assertSame(99, $document->getVersion());
        $this->assertSame('Trail Jacket', $document->get('name'));
        $this->assertNotNull($document->getFingerprint('listing'));
        $this->assertNotNull($document->getFingerprint('internal'));
        $this->assertNull($document->getFingerprint('detail'));
    }

    /**
     * Every document is written in the current schema, and the schema field is not part of any fingerprint.
     */
    public function testADocumentIsStampedWithTheSchemaItWasWrittenIn(): void
    {
        $draft = new DocumentDraft(7, 1);
        $draft->set('name', 'Trail Jacket', DocumentDraft::GROUP_LISTING);
        $before = (new FingerprintCalculator())->fingerprint(['name' => 'Trail Jacket']);

        $document = (new FingerprintCalculator())->document($draft, 99);

        $this->assertSame(DocumentSchema::VERSION, $document->get(DocumentSchema::FIELD));
        $this->assertSame($before, $document->getFingerprint('listing'));
    }
}
