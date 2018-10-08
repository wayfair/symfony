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
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\ExpirableStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Strategy\StrategyInterface;

/**
 * ExpirableCombinedStore is a ExpirableStoreInterface implementation able to manage and synchronize several ExpirableStoreInterfaces.
 *
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
class ExpirableCombinedStore extends CombinedStore implements ExpirableStoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param ExpirableStoreInterface[]  $stores   The list of synchronized stores
     * @param StrategyInterface $strategy
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $stores, StrategyInterface $strategy)
    {
        foreach ($stores as $store) {
            if (!$store instanceof ExpirableStoreInterface) {
                throw new InvalidArgumentException(sprintf('The store must implement "%s". Got "%s".', ExpirableStoreInterface::class, \get_class($store)));
            }
        }

        $this->stores = $stores;
        $this->strategy = $strategy;
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $successCount = 0;
        $failureCount = 0;
        $storesCount = \count($this->stores);
        $expireAt = microtime(true) + $ttl;

        foreach ($this->stores as $store) {
            try {
                if (0.0 >= $adjustedTtl = $expireAt - microtime(true)) {
                    $this->logger->warning('Stores took to long to put off the expiration of the "{resource}" lock.', array('resource' => $key, 'store' => $store, 'ttl' => $ttl));
                    $key->reduceLifetime(0);
                    break;
                }

                $store->putOffExpiration($key, $adjustedTtl);
                ++$successCount;
            } catch (\Exception $e) {
                $this->logger->warning('One store failed to put off the expiration of the "{resource}" lock.', array('resource' => $key, 'store' => $store, 'exception' => $e));
                ++$failureCount;
            }

            if (!$this->strategy->canBeMet($failureCount, $storesCount)) {
                break;
            }
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $key));
        }

        if ($this->strategy->isMet($successCount, $storesCount)) {
            return;
        }

        $this->logger->warning('Failed to define the expiration for the "{resource}" lock. Quorum has not been met.', array('resource' => $key, 'success' => $successCount, 'failure' => $failureCount));

        // clean up potential locks
        $this->delete($key);

        throw new LockConflictedException();
    }
}
