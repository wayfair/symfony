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

use Symfony\Component\Lock\Store\RetryTillSaveStore;
use Symfony\Component\Lock\Store\ZookeeperStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RetryTillSaveStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;

    public function getStore()
    {
        $zookeeper_server = getenv('ZOOKEEPER_HOST').':2181';

        $zookeeper = new \Zookeeper(implode(',', array($zookeeper_server)));

        return new RetryTillSaveStore(new ZookeeperStore($zookeeper), 100, 100);
    }
}
