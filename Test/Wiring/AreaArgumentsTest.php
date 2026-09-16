<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Wiring;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * An area di.xml must never declare an array argument on another module's class.
 */
class AreaArgumentsTest extends TestCase
{
    /**
     * Magento replaces such an array instead of merging it, which deletes the entries the other module put there.
     */
    public function testNoAreaFileReplacesAnotherModulesList(): void
    {
        $offenders = [];

        foreach (glob(dirname(__DIR__, 2) . '/etc/*/di.xml') ?: [] as $file) {
            foreach ($this->foreignArrayArguments($file) as $offence) {
                $offenders[] = basename(dirname($file)) . '/di.xml: ' . $offence;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Declare these in etc/di.xml, where Magento merges the items instead of replacing the list:\n"
            . implode("\n", $offenders)
        );
    }

    /**
     * @return string[]
     */
    private function foreignArrayArguments(string $file): array
    {
        $config = simplexml_load_file($file);
        $found = [];

        $types = $config === false ? [] : ($config->xpath('//type') ?: []);

        foreach ($types as $type) {
            $class = (string) $type['name'];

            if (str_starts_with($class, 'Kingletas\\')) {
                continue;
            }

            foreach ($this->arrayArgumentNames($type) as $argument) {
                $found[] = $class . '::$' . $argument;
            }
        }

        return $found;
    }

    /**
     * @return string[]
     */
    private function arrayArgumentNames(SimpleXMLElement $type): array
    {
        $names = [];

        foreach ($type->xpath('arguments/argument') ?: [] as $argument) {
            if ((string) $argument->attributes('xsi', true)['type'] === 'array') {
                $names[] = (string) $argument['name'];
            }
        }

        return $names;
    }
}
