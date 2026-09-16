<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use PHPUnit\Framework\TestCase;

class LinkFieldTest extends TestCase
{
    public function testAnInstallWithoutStagingJoinsOnTheEntityId(): void
    {
        $link = new LinkField($this->pool('entity_id'));

        $this->assertSame('entity_id', $link->product());
        $this->assertFalse($link->isStaged());
    }

    public function testAStagedInstallJoinsOnTheRowId(): void
    {
        $link = new LinkField($this->pool('row_id'));

        $this->assertSame('row_id', $link->category());
        $this->assertTrue($link->isStaged());
    }

    private function pool(string $linkField): MetadataPool
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn($linkField);
        $metadata->method('getIdentifierField')->willReturn('entity_id');
        $pool = $this->createMock(MetadataPool::class);
        $pool->method('getMetadata')->willReturn($metadata);

        return $pool;
    }
}
