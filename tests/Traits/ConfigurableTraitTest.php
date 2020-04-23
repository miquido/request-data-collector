<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector\Tests\Traits;

use Miquido\RequestDataCollector\Traits\ConfigurableTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Miquido\RequestDataCollector\Traits\ConfigurableTrait
 * @coversDefaultClass \Miquido\RequestDataCollector\Traits\ConfigurableTrait
 */
class ConfigurableTraitTest extends TestCase
{
    use ConfigurableTrait;

    private const CONFIG = [
        'foo' => 'bar',
        'bar' => null,
        'baz' => 123,
        'arr' => ['foo', 123, null],
    ];

    public function testSetConfig(): void
    {
        self::assertEmpty($this->config);

        $this->setConfig(self::CONFIG);

        self::assertSame(self::CONFIG, $this->config);
    }

    public function testGetAllConfig(): void
    {
        $this->config = self::CONFIG;

        self::assertSame($this->config, $this->getConfig());
    }

    public function testGetExistingConfigKey(): void
    {
        $this->config = self::CONFIG;

        foreach (self::CONFIG as $key => $value) {
            self::assertSame($this->config[$key], $this->getConfig($key));
        }
    }

    /**
     * Default value should not change the behaviour of existing configurations.
     */
    public function testGetExistingConfigKeyWithDefaultValue(): void
    {
        $this->config = self::CONFIG;

        foreach (self::CONFIG as $key => $value) {
            self::assertSame($this->config[$key], $this->getConfig($key, new \stdClass()));
        }
    }

    /**
     * Default value should be used.
     */
    public function testGetNonExistentConfigKeyWithDefaultValue(): void
    {
        $this->config = [];

        foreach (self::CONFIG as $key => $value) {
            $default = new \stdClass();

            self::assertSame($default, $this->getConfig($key, $default));
        }
    }
}
