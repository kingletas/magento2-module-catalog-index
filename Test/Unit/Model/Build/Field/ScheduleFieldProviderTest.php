<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\ScheduleFieldProvider;

class ScheduleFieldProviderTest extends FieldProviderTestCase
{
    /**
     * A special price ending on the 20th still applies all that day, so the rebuild is due at the start of the 21st.
     */
    public function testAnEndDateSchedulesTheStartOfTheNextDayInTheStoresTimezone(): void
    {
        $provider = new ScheduleFieldProvider(['special_from_date'], ['special_to_date']);

        $draft = $this->runProvider($provider, [$this->product([
            'entity_id' => 5,
            'special_from_date' => '2026-09-18 00:00:00',
            'special_to_date' => '2026-09-20 00:00:00',
        ])], $this->context('America/New_York'))[5];

        $this->assertSame(
            ['2026-09-18 04:00', '2026-09-21 04:00'],
            array_values(
                array_map(static fn ($moment): string => $moment->format('Y-m-d H:i'), $draft->refreshMoments())
            )
        );
    }

    public function testPastDatesAndGarbageScheduleNothing(): void
    {
        $provider = new ScheduleFieldProvider(['news_from_date'], ['news_to_date']);

        $draft = $this->runProvider($provider, [$this->product([
            'entity_id' => 5,
            'news_from_date' => '2020-01-01',
            'news_to_date' => 'not a date',
        ])])[5];

        $this->assertSame([], $draft->refreshMoments());
    }
}
