<?php

namespace LaminasTest\Cache\Storage\Plugin;

use ArrayObject;
use Laminas\Cache;
use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\PostEvent;
use Laminas\EventManager\Test\EventListenerIntrospectionTrait;
use LaminasTest\Cache\Storage\TestAsset\OptimizableMockAdapter;

use function array_shift;
use function count;
use function get_class;

/**
 * @covers \Laminas\Cache\Storage\Plugin\OptimizeByFactor<extended>
 */
class OptimizeByFactorTestAbstract extends AbstractCommonPluginTest
{
    use EventListenerIntrospectionTrait;

    /**
     * The storage adapter
     *
     * @var AbstractAdapter
     */
    protected $adapter;

    /** @var Cache\Storage\Plugin\PluginOptions */
    private $options;

    public function setUp(): void
    {
        $this->adapter = new OptimizableMockAdapter();
        $this->options = new Cache\Storage\Plugin\PluginOptions([
            'optimizing_factor' => 1,
        ]);
        $this->plugin  = new Cache\Storage\Plugin\OptimizeByFactor();
        $this->plugin->setOptions($this->options);
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
     */
    public function getCommonPluginNamesProvider()
    {
        return [
            'lowercase with underscore' => ['optimize_by_factor'],
            'lowercase'                 => ['optimizebyfactor'],
            'UpperCamelCase'            => ['OptimizeByFactor'],
            'camelCase'                 => ['optimizeByFactor'],
        ];
    }

    public function testAddPlugin(): void
    {
        $this->adapter->addPlugin($this->plugin);

        // check attached callbacks
        $expectedListeners = [
            'removeItem.post'  => 'optimizeByFactor',
            'removeItems.post' => 'optimizeByFactor',
        ];
        foreach ($expectedListeners as $eventName => $expectedCallbackMethod) {
            $listeners = $this->getArrayOfListenersForEvent($eventName, $this->adapter->getEventManager());

            // event should attached only once
            self::assertSame(1, count($listeners));

            // check expected callback method
            $cb = array_shift($listeners);
            self::assertArrayHasKey(0, $cb);
            self::assertSame($this->plugin, $cb[0]);
            self::assertArrayHasKey(1, $cb);
            self::assertSame($expectedCallbackMethod, $cb[1]);
        }
    }

    public function testRemovePlugin(): void
    {
        $this->adapter->addPlugin($this->plugin);
        $this->adapter->removePlugin($this->plugin);

        // no events should be attached
        self::assertEquals(0, count($this->getEventsFromEventManager($this->adapter->getEventManager())));
    }

    public function testOptimizeByFactor(): void
    {
        $adapter = $this->getMockBuilder(get_class($this->adapter))
            ->setMethods(['optimize'])
            ->getMock();

        // test optimize will be called
        $adapter
            ->expects($this->once())
            ->method('optimize');

        // call event callback
        $result = true;
        $event  = new PostEvent('removeItem.post', $adapter, new ArrayObject([
            'options' => [],
        ]), $result);

        $this->plugin->optimizeByFactor($event);

        self::assertTrue($event->getResult());
    }
}
