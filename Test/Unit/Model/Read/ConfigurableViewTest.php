<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\ConfigurableView;
use PHPUnit\Framework\TestCase;

class ConfigurableViewTest extends TestCase
{
    public function testOnlyWellFormedRowsAreReturned(): void
    {
        $view = new ConfigurableView([
            'configurable_options' => ['93' => [['value_index' => '49']], '141' => 'junk'],
            'super_attributes' => ['junk', ['attribute_id' => '93']],
        ]);

        $this->assertSame(['93' => [['value_index' => '49']]], $view->options());
        $this->assertSame([['attribute_id' => '93']], $view->superAttributes());
    }

    public function testADocumentWithoutConfigurableFieldsHasNoRows(): void
    {
        $view = new ConfigurableView([]);

        $this->assertSame([], $view->options());
        $this->assertSame([], $view->superAttributes());
    }
}
