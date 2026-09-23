<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use PHPUnit\Framework\TestCase;

class DocumentDraftTest extends TestCase
{
    public function testFieldsAreGroupedByWhatAChangeWouldPurge(): void
    {
        $draft = new DocumentDraft(7, 1);
        $draft->set('name', 'Trail Jacket', DocumentDraftInterface::GROUP_LISTING);
        $draft->set('description', 'Warm.', DocumentDraftInterface::GROUP_DETAIL);
        $draft->set('updated_at', '2026-09-15', DocumentDraftInterface::GROUP_INTERNAL);

        $this->assertSame(
            [
                'detail' => ['description' => 'Warm.'],
                'internal' => ['updated_at' => '2026-09-15'],
                'listing' => ['name' => 'Trail Jacket'],
            ],
            $draft->fieldsByGroup()
        );
        $this->assertTrue($draft->has('name'));
        $this->assertNull($draft->get('sku'));
        $this->assertSame([7, 1], [$draft->getId(), $draft->getScopeId()]);
    }

    public function testTheFirstExclusionReasonIsKept(): void
    {
        $draft = new DocumentDraft(7, 1);
        $draft->exclude('disabled');
        $draft->exclude('not in website');

        $this->assertTrue($draft->isExcluded());
        $this->assertSame('disabled', $draft->exclusionReason());
    }

    public function testRefreshMomentsAreUniqueAndInOrder(): void
    {
        $draft = new DocumentDraft(7, 1);
        $draft->refreshAt(new DateTimeImmutable('@200'));
        $draft->refreshAt(new DateTimeImmutable('@100'));
        $draft->refreshAt(new DateTimeImmutable('@200'));

        $this->assertSame([100, 200], array_keys($draft->refreshMoments()));
    }
}
