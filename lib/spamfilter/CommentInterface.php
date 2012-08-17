<?php
namespace spamfilter;

interface CommentInterface {

	/**
	 * Checks authors history (based on authors email)
	 * Returns number of ham comments minus number spam comments
	 *
	 * @abstract
	 * @param array|\ArrayAccess $Comment
	 * @return int
	 */
	public function history($Comment);

	/**
	 * Checks if comment with same text exists
	 * Returns true if exists
	 *
	 * @abstract
	 * @param array|\ArrayAccess $Comment
	 * @return bool
	 */
	public function exists($Comment);
}
