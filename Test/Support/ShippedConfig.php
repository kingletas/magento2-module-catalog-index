<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Kingletas\CatalogIndex\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * A real `Config` over the values `etc/config.xml` ships, with the module switched on unless a test says otherwise.
 */
trait ShippedConfig
{
    /**
     * @param array<string, string|int|float|null> $overrides Section-relative paths.
     */
    protected function config(array $overrides = []): Config
    {
        $values = [];

        foreach (array_merge($this->shippedDefaults(), ['general/enabled' => '1'], $overrides) as $path => $value) {
            $values['kingletas_catalog_index/' . $path] = $value;
        }

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => !in_array($values[$path] ?? null, [null, '', '0', 0, false], true)
        );
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $value): string => 'decrypted:' . $value);

        return new Config($scopeConfig, 'kingletas_catalog_index', $encryptor);
    }

    /**
     * @return array<string, string>
     */
    protected function shippedDefaults(): array
    {
        $xml = simplexml_load_file(dirname(__DIR__, 2) . '/etc/config.xml');
        $defaults = [];

        foreach ($xml->default->kingletas_catalog_index->children() as $group) {
            foreach ($group->children() as $field) {
                $defaults[$group->getName() . '/' . $field->getName()] = (string) $field;
            }
        }

        return $defaults;
    }
}
