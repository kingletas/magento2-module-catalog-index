<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Stock;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\StockReaderInterface;

/**
 * Picks the first stock reader that applies to this installation.
 */
class StockReaderPool
{
    private ?StockReaderInterface $chosen = null;

    /**
     * @param array<string, StockReaderInterface> $readers In order of preference.
     */
    public function __construct(
        private readonly array $readers = []
    ) {
        foreach ($this->readers as $key => $reader) {
            if (!$reader instanceof StockReaderInterface) {
                throw new InvalidArgumentException(
                    (string) __('Stock reader "%1" must implement %2.', $key, StockReaderInterface::class)
                );
            }
        }
    }

    public function reader(): StockReaderInterface
    {
        if ($this->chosen === null) {
            foreach ($this->readers as $reader) {
                if ($reader->isApplicable()) {
                    $this->chosen = $reader;

                    break;
                }
            }
        }

        return $this->chosen
            ?? throw new InvalidArgumentException((string) __('No stock reader applies to this installation.'));
    }
}
