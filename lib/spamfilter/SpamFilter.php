<?php
namespace spamfilter;

use
\lib\Request,
\spamfilter\entity\Word,
\spamfilter\CommentInterface,
\spamfilter\KnowledgeInterface;

class SpamFilter {

	/** @var \lib\container\Container */
	protected $Container;

	/** @var CommentInterface */
	protected $Comment;

	/** @var KnowledgeInterface */
	protected $Knowledge;

	/** @var int */
	protected $tolerance;

	/** @var \spamfilter\entity\Word[] */
	protected $knowledge = array();

	/** @var array */
	protected $strip = array('\n', '\r', '\t', "\n", "\r", "\t", '`', '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '+', '-', '=', '{', '}', '[', ']', ':', '"', ';', '\'', '<', '>', '?', ',', '.', '/', '|', '\\');

	/**
	 * Creates SpamFilter instance
	 *
	 * @param KnowledgeInterface    $Knowledge
	 * @param CommentInterface|null $Comment
	 * @param int                             $tolerance
	 */
	public function __construct(KnowledgeInterface $Knowledge, CommentInterface $Comment = null, $tolerance = 4) {
		$this->Knowledge = $Knowledge;
		$this->Comment = &$Comment;
		$this->tolerance = (int) $tolerance;
	}

	/**
	 * Learns filtering from string
	 *
	 * @param string $text text to learn from
	 * @param bool   $spam flag defining if string is ham or spam
	 *
	 * @return array
	 */
	public function learn($text, $spam = false) {
		$text = $this->splitText($text);

		foreach($text as $hash => $word) {
			try {
				$Word = $this->Knowledge->getWord($word);
			}
			catch(\OutOfRangeException $e) {
				$Word = new Word(array('hash' => $hash, 'word' => $word, 'ham' => 0, 'spam' => 0));
			}

			$Word['ham'] = $Word['ham'] + (!$spam ? 1 : 0);
			$Word['spam'] = $Word['spam'] + ($spam ? 1 : 0);

			$this->Knowledge->putWord($Word);
		}

		return $text;
	}

	/**
	 * Rates comment according to passed weights
	 * Returns comments status (integer)
	 * +1 - is ham
	 * 0  - possible spam
	 * -1 - is spam
	 *
	 * @param array|\ArrayAccess $Comment
	 * @param \lib\Request|null  $Request
	 * @param bool               $verbose
	 *
	 * @return array|int
	 */
	public function rate($Comment, Request $Request = null, $verbose = true) {
		$pts = array();

		$pts['links'] = $this->links($Comment['text']);
		$pts['body'] = $this->body($Comment['text']);
		$pts['prop'] = $this->prop($Comment['text']);

		if($Request) {
			$pts['history'] = $this->history($this->Comment->history($Comment));
			$pts['exists'] = $this->existing($this->Comment->exists($Comment));
			$pts['honeypot'] = $this->honeypot(isset($Request->post['entity']['body']) && empty($Request->post['entity']['body']));
			$pts['referer'] = $this->referer($Request->referer);
			$pts['elapsed'] = $this->elapsed(isset($Request->post['entity']['stamp']) ? time() - $Request->post['entity']['stamp'] : 0);
		}

		$pts['total'] = array_sum($pts);

		if($pts['total'] >= 0) {
			$pts['rate'] = 1;
		}
		elseif($pts['total'] + $this->tolerance >= 0) {
			$pts['rate'] = 0;
		}
		else {
			$pts['rate'] = -1;
		}

		if($verbose) {
			return $pts;
		}

		return $pts['rate'];
	}

	/**
	 * Finds links in text and checks their count and length.
	 * Grades text according to set limits
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param string $text        text to be checked
	 * @param int    $countLimit  number of allowed links in text, all subsequent links are graded negatively
	 * @param int    $lengthLimit maximal link length, all links longer, are graded negatively
	 * @param int    $weight      grade weight
	 *
	 * @return int
	 */
	public function links($text, $countLimit = 2, $lengthLimit = 30, $weight = 1) {
		preg_match_all('/\b(?:(?:https?|ftp|file)?:\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $text, $matches);

		$pts = $countLimit * $weight;
		$count = count($matches[0]);

		for($i = 0; $i < $count; $i++) {
			$pts -= $weight;
			$pts += strlen($matches[0][$i]) > $lengthLimit ? -$weight / 2 : 0;
		}

		return $pts;
	}

	/**
	 * Checks text length
	 * If length is greater than limit - is graded positively.
	 * Short text are graded negatively
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param string $text   text to be checked
	 * @param int    $limit  minimal text length to be graded positively
	 * @param int    $weight grade weight
	 *
	 * @return int
	 */
	public function body($text, $limit = 60, $weight = 2) {
		return strlen(strip_tags($text)) >= $limit ? $weight : -$weight;
	}

	/**
	 * Calculates the probability of being spam based on wordlist
	 * Only words with 3 or more chars are used
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param string $text   text to be checked
	 * @param int    $weight grade weight
	 *
	 * @return float
	 */
	public function prop($text, $weight = 10) {
		$text = $this->splitText($text);

		$condition = array();
		foreach($text as $hash => $word) {
			if(!isset($this->knowledge[$hash])) {
				$condition[] = $hash;
			}
		}

		if(!empty($condition)) {
			$condition = array_unique($condition);

			$knowledge = $this->Knowledge->getByHash($condition);
			foreach($knowledge as $word) {
				$this->knowledge[$word['word']] = $word;
			}
		}

		$spam = 0;
		$ham = 0;
		foreach($text as $word) {
			if(isset($this->knowledge[$word])) {
				$spam += $this->knowledge[$word]['spam'];
				$ham += $this->knowledge[$word]['ham'];
			}
			else {
				$spam += 0;
				$ham += 1;
			}
		}

		$pts = ($spam / ($ham + $spam ? $ham + $spam : 1)) * -$weight;

		return $pts;
	}

	/**
	 * Splits text for probability calculations
	 * Only words with 3 or more chars are included
	 *
	 * @param string $text
	 *
	 * @return array|string[]
	 */
	protected function splitText($text) {
		$text = preg_replace('#<a.*href="([^"]*)"[^>]*>([^<]*)</a>#im', '$1 $2', $text);
		$text = strip_tags($text);
		$text = str_replace($this->strip, ' ', $text);
		$text = mb_strtolower($text, 'UTF-8');
		$text = explode(' ', $text);

		$output = array();
		foreach($text as $word) {
			if(mb_strlen($word, 'UTF8') <= 3) {
				continue;
			}

			$output[crc32($word)] = $word;
		}

		return $output;
	}


	/**
	 * Grades text authors history.
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param int $previousPostsCount number of posts non spam posts minus number of spam posts
	 * @param int $weight             grade weight
	 *
	 * @return int
	 */
	public function history($previousPostsCount, $weight = 1) {
		return (int) $previousPostsCount * $weight;
	}

	/**
	 * Grades text according to previously posted texts
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param int $existingPostsCount number of equal texts
	 * @param int $weight             grade weight
	 *
	 * @return int
	 */
	public function existing($existingPostsCount, $weight = 100) {
		return (int) $existingPostsCount * -$weight;
	}

	/**
	 * Grades honeypot
	 * If honeypot is empty - grade is equal to 0, otherwise equals weight
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param bool $emptyHoneypot
	 * @param int  $weight grade weight
	 *
	 * @return int
	 */
	public function honeypot($emptyHoneypot, $weight = 100) {
		return ($emptyHoneypot ? 0 : 1) * -$weight;
	}

	/**
	 * Grades referrer
	 * If referrer is missing or invalid - grade is equal to weight, otherwise equals 0
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param bool $refererExists
	 * @param int  $weight grade weight
	 *
	 * @return int
	 */
	public function referer($refererExists, $weight = 100) {
		return ($refererExists ? 0 : 1) * -$weight;
	}

	/**
	 * Grades time elapsed from rendering form to its submit
	 * If elapsed time is greater than limit - grade is equal to weight
	 * Otherwise equals weight * -1
	 * Returns grade (negative values are bad, positive are good)
	 *
	 * @param int $elapsedSeconds time elapsed from form render to submit
	 * @param int $limit          minimal time needed to submit form
	 * @param int $weight         grade weight
	 *
	 * @return int
	 */
	public function elapsed($elapsedSeconds, $limit = 5, $weight = 100) {
		if($elapsedSeconds < $limit) {
			return -$weight;
		}

		return 0;
	}
}