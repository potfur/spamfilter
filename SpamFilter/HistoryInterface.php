<?php

/*
* This file is part of the spamfilter package
*
* (c) Michal Wachowski <wachowski.michal@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace SpamFilter;

/**
 * Interface for history repositories
 *
 * @package SpamFilter
 */
interface HistoryInterface
{
    /**
     * Checks authors history (based on user identifier)
     * Returns number of ham entries minus number spam entries
     *
     * @param int|string $identifier
     *
     * @return int
     */
    public function history($identifier);

    /**
     * Checks if entry with same text exists
     * Returns true if exists
     *
     * @param array $text
     *
     * @return bool
     */
    public function exists($text);
} 