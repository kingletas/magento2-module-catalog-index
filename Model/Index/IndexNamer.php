<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Index;

use DateTimeImmutable;
use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Config;

/**
 * Names aliases and the physical indexes behind them.
 */
class IndexNamer
{
    private const string VALID_PREFIX = '/^[a-z0-9][a-z0-9_\-]*$/';

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function alias(IndexFamily $family, int $scopeId): string
    {
        return sprintf('%s_%s_%d', $this->prefix(), $family->value, $scopeId);
    }

    public function buildName(string $alias, DateTimeImmutable $moment): string
    {
        return $alias . '_' . $moment->format('YmdHisu');
    }

    /**
     * Physical indexes of one alias share this prefix and nothing else does.
     */
    public function buildPrefix(string $alias): string
    {
        return $alias . '_';
    }

    public function prefix(): string
    {
        $prefix = strtolower(trim($this->config->getIndexPrefix()));

        if (preg_match(self::VALID_PREFIX, $prefix) !== 1) {
            throw new InvalidArgumentException((string) __(
                'The index prefix "%1" may only hold lowercase letters, digits, "_" and "-".',
                $prefix
            ));
        }

        return $prefix;
    }
}
