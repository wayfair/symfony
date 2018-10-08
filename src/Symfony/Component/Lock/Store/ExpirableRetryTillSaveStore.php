<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\ExpirableStoreInterface;
use Symfony\Component\Lock\Key;

/**
 * ExpirableRetryTillSaveStore is a ExpirableStoreInterface implementation which decorate a non blocking ExpirableStoreInterface to provide a
 * blocking storage.
 *
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
class ExpirableRetryTillSaveStore extends RetryTillSaveStore implements ExpirableStoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param ExpirableStoreInterface $decorated  The decorated ExpirableStoreInterface
     * @param int                     $retrySleep Duration in ms between 2 retry
     * @param int                     $retryCount Maximum amount of retry
     */
    public function __construct(ExpirableStoreInterface $decorated, int $retrySleep = 100, int $retryCount = PHP_INT_MAX)
    {
        $this->decorated = $decorated;
        $this->retrySleep = $retrySleep;
        $this->retryCount = $retryCount;

        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $this->decorated->putOffExpiration($key, $ttl);
    }
}
