<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Source contract for Controller/Tracker/Event.php: the controller extends Magento's Action
 * class, which the unit stubs do not provide, so its allowlist is checked as text.
 */
final class TrackerEventControllerContractTest extends TestCase
{
    public function testPageEngagementIsAllowedAndSanitized(): void
    {
        $source = $this->controllerSource();
        $this->assertMatchesRegularExpression(
            '/\$allowed = \[[^\]]*PageEngagementFields::EVENT_NAME,[^\]]*\];/s',
            $source
        );
        $this->assertStringContainsString('$data = PageEngagementFields::sanitize($data);', $source);
    }

    private function controllerSource(): string
    {
        $path = dirname(__DIR__, 2) . '/Controller/Tracker/Event.php';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse($source);
        return $source;
    }
}
