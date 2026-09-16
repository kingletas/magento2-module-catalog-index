<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build\Field;

use DateTimeImmutable;
use DateTimeZone;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Magento\Catalog\Model\Product;
use Throwable;

/**
 * Schedules a rebuild for the start of each day a dated value begins or ends, in the store's own timezone.
 */
class ScheduleFieldProvider extends AbstractFieldProvider
{
    /**
     * @param string[] $fromAttributes Codes whose date is the first day a value applies.
     * @param string[] $toAttributes Codes whose date is the last day a value applies.
     */
    public function __construct(
        private readonly array $fromAttributes = [],
        private readonly array $toAttributes = [],
        int $sortOrder = 90
    ) {
        parent::__construct($sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ($draft->isExcluded()) {
            return;
        }

        foreach ($this->fromAttributes as $code) {
            $this->schedule($draft, $product->getData($code), 0, $context);
        }

        foreach ($this->toAttributes as $code) {
            $this->schedule($draft, $product->getData($code), 1, $context);
        }
    }

    private function schedule(DocumentDraft $draft, mixed $value, int $daysAfter, BuildContext $context): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $zone = new DateTimeZone($context->timezone);
            $day = new DateTimeImmutable(substr(trim($value), 0, 10) . ' 00:00:00', $zone);
        } catch (Throwable) {
            return;
        }

        $moment = $day->modify(sprintf('+%d day', $daysAfter))->setTimezone(new DateTimeZone('UTC'));

        if ($moment > $context->now) {
            $draft->refreshAt($moment);
        }
    }
}
