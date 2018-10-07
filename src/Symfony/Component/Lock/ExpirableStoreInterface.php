<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Exception\NotSupportedException;

/**
 * ExpirableStoreInterface defines an interface to manipulate an expirable lock store.
 *
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
interface ExpirableStoreInterface extends StoreInterface
{
  /**
   * {@inheritdoc}
   *
   * @throws LockExpiredException
   */
  public function save(Key $key);

  /**
   * Extends the ttl of a resource.
   *
   * If the expirable store does not support this feature it should throw a NotSupportedException.
   *
   * @param float $ttl amount of second to keep the lock in the store
   *
   * @throws LockConflictedException
   * @throws NotSupportedException
   * @throws LockExpiredException
   */
  public function putOffExpiration(Key $key, $ttl);
}