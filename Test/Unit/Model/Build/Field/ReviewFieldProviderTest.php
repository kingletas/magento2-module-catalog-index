<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\ReviewFieldProvider;

class ReviewFieldProviderTest extends FieldProviderTestCase
{
    /**
     * A product without reviews still gets zeros, so the review renderer never looks it up by itself.
     */
    public function testEveryProductGetsASummaryEvenWithoutReviews(): void
    {
        $this->answers['review_entity_summary'] = [
            ['entity_pk_value' => '5', 'rating_summary' => '80', 'reviews_count' => '3'],
        ];

        $drafts = $this->runProvider(new ReviewFieldProvider($this->resourceConnection()), [
            $this->product(['entity_id' => 5]),
            $this->product(['entity_id' => 6]),
        ]);

        $this->assertSame(80, $drafts[5]->get('rating_summary'));
        $this->assertSame(3, $drafts[5]->get('reviews_count'));
        $this->assertSame(0, $drafts[6]->get('rating_summary'));
    }

    public function testAStoreWithoutTheReviewModuleStillGetsZeros(): void
    {
        $this->tablesExist = false;

        $draft = $this->runProvider(
            new ReviewFieldProvider($this->resourceConnection()),
            [$this->product(['entity_id' => 5])]
        )[5];

        $this->assertSame(0, $draft->get('reviews_count'));
        $this->assertSame([], $this->queries);
    }
}
