<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\CategoryView;
use PHPUnit\Framework\TestCase;

class CategoryViewTest extends TestCase
{
    public function testTheDocumentIsReadBackAsAttributesCountAndPath(): void
    {
        $view = new CategoryView(12, [
            'attributes' => ['name' => 'Outerwear'],
            'product_count' => '9',
            'request_path' => 'outerwear.html',
        ]);

        $this->assertSame(12, $view->getId());
        $this->assertSame(['name' => 'Outerwear'], $view->attributes());
        $this->assertSame(9, $view->productCount());
        $this->assertSame('outerwear.html', $view->requestPath());
    }

    public function testAMissingCountOrPathIsNullRatherThanZero(): void
    {
        $view = new CategoryView(12, ['attributes' => 'junk', 'request_path' => '']);

        $this->assertSame([], $view->attributes());
        $this->assertNull($view->productCount());
        $this->assertNull($view->requestPath());
    }
}
