<?php

/**
 * Lenevor Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.md.
 * It is also available through the world-wide-web at this URL:
 * https://lenevor.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@Lenevor.com so we can send you a copy immediately.
 *
 * @package     Lenevor
 * @subpackage  Base
 * @link        https://lenevor.com
 * @copyright   Copyright (c) 2019 - 2023 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Components\Routing\Collections;

use Countable;
use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Syscodes\Components\Contracts\Support\Arrayable;

/**
 * Allows the route collection of base. 
 */
final class AbstractRouteCollection implements Countable, IteratorAggregate
{
    /**
     * Get an iterator for the items.
     * 
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
    
    /**
     * Count the number of items in the collection.
     * 
     * @return int
     */
    public function count(): int
    {
        return count([]);
    }
}