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
 * Interface for dictionaries storing word
 *
 * @package SpamFilter
 */
interface DictionaryInterface
{
    /**
     * Adds words to dictionary
     * If words already exist in dictionary, increases their number of occurrences
     * If category is missing, creates it
     *
     * @param string $category
     * @param array  $words
     *
     * @return $this
     */
    public function addWordsToCategory($category, array $words);

    /**
     * Adds word to dictionary
     * If word already exists in dictionary, increases its number of occurrences
     * If category is missing, creates it
     *
     * @param string $category
     * @param string $word
     *
     * @return $this
     */
    public function addWordToCategory($category, $word);

    /**
     * Returns all not empty categories as array with category - count pairs
     * If $categories is specified, only those words will be returned
     *
     * @param array $categories
     *
     * @return array
     */
    public function getCategories($categories = array());

    /**
     * Returns words from segment, as array with word - count pairs
     * If $words is specified, only those words will be returned
     *
     * @param string $category
     * @param array  $words
     *
     * @return array
     */
    public function getWordsFromCategory($category, $words = array());

    /**
     * Returns single words count from segment
     *
     * @param string $category
     * @param string $word
     *
     * @return int
     */
    public function getWordFromCategory($category, $word);
}