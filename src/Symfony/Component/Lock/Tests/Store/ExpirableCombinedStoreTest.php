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
use Symfony\Component\Lock\ExpirableStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\ExpirableCombinedStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\StoreInterface;
use Symfony\Component\Lock\Strategy\StrategyInterface;
use Symfony\Component\Lock\Strategy\UnanimousStrategy;

/**
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
class ExpirableCombinedStoreTest extends CombinedStoreTest
{
    use ExpiringStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay(): int
    {
        return 250000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): StoreInterface
    {
        $redis = new \Predis\Client('tcp://'.getenv('REDIS_HOST').':6379');
        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        return new ExpirableCombinedStore(array(new RedisStore($redis)), new UnanimousStrategy());
    }

    protected function setUp()
    {
        $this->strategy = $this->getMockBuilder(StrategyInterface::class)->getMock();
        $this->store1 = $this->getMockBuilder(ExpirableStoreInterface::class)->getMock();
        $this->store2 = $this->getMockBuilder(ExpirableStoreInterface::class)->getMock();

        $this->store = new ExpirableCombinedStore(array($this->store1, $this->store2), $this->strategy);
    }

    /**
     * @expectedException \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function testputOffExpirationThrowsExceptionOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());

        $this->strategy
            ->expects($this->any())
            ->method('canBeMet')
            ->willReturn(true);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        $this->store->putOffExpiration($key, $ttl);
    }

    public function testputOffExpirationCleanupOnFailure()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
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
            $this->store->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }

    public function testputOffExpirationAbortWhenStrategyCantBeMet()
    {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

        $this->store1
            ->expects($this->once())
            ->method('putOffExpiration')
            ->with($key, $this->lessThanOrEqual($ttl))
            ->willThrowException(new LockConflictedException());
        $this->store2
            ->expects($this->never())
            ->method('putOffExpiration');

        $this->strategy
            ->expects($this->once())
            ->method('canBeMet')
            ->willReturn(false);
        $this->strategy
            ->expects($this->any())
            ->method('isMet')
            ->willReturn(false);

        try {
            $this->store->putOffExpiration($key, $ttl);
        } catch (LockConflictedException $e) {
            // Catch the exception given this is not what we want to assert in this tests
        }
    }
}
