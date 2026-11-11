<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\EventRegistry;

/**
 * EventRegistry keys must equal platformEvents.magento in the canonical registry copy
 * (tests/fixtures/event-registry.json, synced by tools/event-registry.mjs; do not edit by hand).
 */
final class EventRegistryFixtureParityTest extends TestCase
{
    public function testRegistryKeysMatchCanonicalMagentoEvents(): void
    {
        $path = dirname(__DIR__) . '/fixtures/event-registry.json';
        $this->assertFileExists($path);
        $fixture = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($fixture);
        $canonical = $fixture['platformEvents']['magento'] ?? null;
        $this->assertIsArray($canonical);

        $expected = array_values(array_unique($canonical));
        $actual = array_keys(EventRegistry::getRegistry());
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }
}
