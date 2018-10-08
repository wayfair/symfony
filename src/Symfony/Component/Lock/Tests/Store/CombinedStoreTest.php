<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Store\ZookeeperStore;
use Symfony\Component\Lock\StoreInterface;
use Symfony\Component\Lock\Strategy\StrategyInterface;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CombinedStoreTest extends AbstractStoreTest
{
    /**
     * {@inheritdoc}
     */
    public function getStore(): StoreInterface
    {
      $zookeeper_server = getenv('ZOOKEEPER_HOST').':2181';

      $zookeeper = new \Zookeeper(implode(',', array($zookeeper_server)));

      return new CombinedStore(array(new ZookeeperStore($zookeeper), new FlockStore(), new SemaphoreStore()), new UnanimousStrategy());
    }

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $strategy;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $store1;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $store2;
    /** @var CombinedStore */
    protected $store;

    protected function setUp()
    {
        $this->strategy = $this->getMockBuilder(StrategyInterface::class)->getMock();
        $this->store1 = $this->getMockBuilder(StoreInterface::class)->getMock();
        $this->store2 = $this->getMockBuilder(StoreInterface::class)->getMock();

        $this->store = new CombinedStore(array($this->store1, $this->store2), $this->strategy);
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function testSaveThrowsExceptionOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->save($key);
    }

    public function testSaveCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());

        $this->store1
            ->expects($this->once())
            ->method('delete');
        $this->store2
            ->expects($this->once())
            ->method('delete');

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testSaveAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('save')
            ->with($key)
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->never())
            ->method('save');

        $this->strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->save($key);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testExistsDontAskToEveryBody()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects($this->never())
            ->method('exists');

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(true);

        $this->assertTrue($this->store->exists($key));
    }

    public function testExistsAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->any())
            ->method('exists')
            ->with($key)
            ->willReturn(false);
        $this->store2
            ->expects($this->never())
            ->method('exists');

        $this->strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects($this->once())
            ->method('isMet')
            ->willReturn(false);

        $this->assertFalse($this->store->exists($key));
    }

    public function testDeleteDontStopOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));

        $this->store1
            ->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willThrowException(new \Exception());
        $this->store2
            ->expects($this->once())
            ->method('delete')
            ->with($key);

        $this->store->delete($key);
    }
}
