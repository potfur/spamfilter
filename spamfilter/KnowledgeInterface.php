<?php
namespace spamfilter;

interface KnowledgeInterface {

	/**
	 * Retrieves word from knowledge repository
	 *
	 * @abstract
	 *
	 * @param string $word
	 *
	 * @return array
	 */
	public function getWord($word);

	/**
	 * Puts word into knowledge repository
	 *
	 * @abstract
	 *
	 * @param array|\ArrayAccess $word
	 *
	 * @return array
	 */
	public function putWord($word);

	/**
	 * Returns data from knowledge repository matching passed words
	 *
	 * @abstract
	 *
	 * @param array $words
	 *
	 * @return array
	 */
	public function getWords($words = array());


	/**
	 * Checks authors history (based on authors email)
	 * Returns number of ham comments minus number spam comments
	 *
	 * @abstract
	 * @param array|\ArrayAccess $text
	 * @return int
	 */
	public function history($text);

	/**
	 * Checks if comment with same text exists
	 * Returns true if exists
	 *
	 * @abstract
	 * @param array|\ArrayAccess $text
	 * @return bool
	 */
	public function exists($text);
}
