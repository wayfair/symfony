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

use Symfony\Component\Lock\ExpirableStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\ExpirableRetryTillSaveStore;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\RetryTillSaveStore;

/**
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
class ExpirableRetryTillSaveStoreTest extends RetryTillSaveStoreTest
{
    use BlockingStoreTestTrait;

    private $store;
    private $store1;

    public function getStore()
    {
      $redis = new \Predis\Client('tcp://'.getenv('REDIS_HOST').':6379');
      try {
        $redis->connect();
      } catch (\Exception $e) {
        self::markTestSkipped($e->getMessage());
      }

        return new RetryTillSaveStore(new RedisStore($redis), 100, 100);
    }

    protected function setUp()
    {
    $this->store1 = $this->getMockBuilder(ExpirableStoreInterface::class)->getMock();

    $this->store = new ExpirableRetryTillSaveStore($this->store1, 100, 100);
    }

    public function testPutOffExpiration() {
        $key = new Key(uniqid(__METHOD__, true));
        $ttl = random_int(1, 10);

      $this->store1
          ->expects($this->once())
          ->method('putOffExpiration')
          ->with($key, $this->lessThanOrEqual($ttl));

      $this->store->putOffExpiration($key, $ttl);
    }
}
