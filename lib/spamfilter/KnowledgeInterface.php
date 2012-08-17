<?php
namespace spamfilter;

use \spamfilter\entity\Word;

interface KnowledgeInterface {

	/**
	 * Retrieves word from knowledge repository
	 *
	 * @abstract
	 *
	 * @param string $word
	 *
	 * @return Word
	 */
	public function getWord($word);

	/**
	 * Puts word into knowledge repository
	 *
	 * @abstract
	 *
	 * @param array|\ArrayAccess $word
	 *
	 * @return Word
	 */
	public function putWord($Word);

	/**
	 * Retrieves matching words from knowledge repository
	 *
	 * @abstract
	 *
	 * @param string|array $hash
	 *
	 * @return Word|array|Word[]
	 */
	public function getByHash($hash);
}
