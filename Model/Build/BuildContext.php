<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;

/**
 * The scope and moment one batch of documents is built for.
 */
class BuildContext implements BuildContextInterface
{
    public function __construct(
        private readonly int $storeId,
        private readonly int $websiteId,
        private readonly int $version,
        private readonly DateTimeImmutable $now,
        private readonly string $timezone = 'UTC'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function getWebsiteId(): int
    {
        return $this->websiteId;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @inheritDoc
     */
    public function getNow(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * @inheritDoc
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
