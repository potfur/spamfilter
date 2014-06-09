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
 * Class SpamFilter
 *
 * @package SpamFilter
 */
class SpamFilter
{
    /**
     * @var DictionaryInterface
     */
    protected $dictionary;

    /**
     * @var HistoryInterface
     */
    protected $history;

    protected $options = array(
        'linksCountLimit' => 2,
        'linksLengthLimit' => 30,
        'links' => 1,

        'bodyMinLength' => 60,
        'body' => 2,

        'prop' => 10,
        'history' => 1,
        'exists' => 100,
        'honeypot' => 100,
        'referrer' => 100,
        'elapsed' => 1
    );

    /**
     * Constructor
     *
     * @param DictionaryInterface $dictionary
     * @param HistoryInterface    $history
     */
    public function __construct(DictionaryInterface $dictionary = null, HistoryInterface $history = null)
    {
        $this->dictionary = $dictionary;
        $this->history = $history;
    }

    /**
     * Sets options (limits and weights) for rating method
     *
     * @param array $options
     *
     * @return array
     */
    public function options($options = array())
    {
        array_walk($options, function (&$val) { $val = (int) $val; });

        if ($options !== array()) {
            $this->options = array_merge($this->options, $options);
        }

        return $this->options;
    }

    /**
     * Rates entry using all methods
     *
     * @param string          $text
     * @param null|int|string $author
     * @param null|string     $honeypot
     * @param null|string     $referrer
     * @param null|string     $expectedReferrer
     * @param null|\DateTime  $submitted
     * @param bool            $verbose
     *
     * @return array|number
     */
    public function rate($text, $author = null, $honeypot = null, $referrer = null, $expectedReferrer = null, \DateTime $submitted = null, $verbose = false)
    {
        $grade = array(
            'links' => $this->links($text, $this->options['linksCountLimit'], $this->options['linksLengthLimit'], $this->options['links']),
            'body' => $this->body($text, $this->options['bodyMinLength'], $this->options['body']),
            'prop' => $this->prop($text, $this->options['prop']),
            'history' => $this->history($author, $this->options['history']),
            'exists' => $this->exists($text, $this->options['exists']),
            'honeypot' => $this->honeypot($honeypot, $this->options['honeypot']),
            'referrer' => $this->referrer($referrer, $expectedReferrer, $this->options['referrer']),
            'elapsed' => $this->elapsed($submitted === null ? new \DateTime() : $submitted, $this->options['elapsed'])
        );

        if ($verbose) {
            return $grade;
        }

        $grade = array_sum($grade);

        return $grade;
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
    public function links($text, $countLimit = 2, $lengthLimit = 30, $weight = 1)
    {
        preg_match_all('/\b(?:(?:https?|ftp|file)?:\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $text, $matches);

        $pts = $countLimit * $weight;
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
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
    public function body($text, $limit = 60, $weight = 2)
    {
        $len = strlen(strip_tags($text));
        if ($len < $limit) {
            return $weight * -1;
        }

        return round($len / $limit, 4) * $weight;
    }

    /**
     * Adds words to dictionary
     *
     * @param string $text
     * @param bool   $isSpam
     *
     * @return $this
     * @throws \BadMethodCallException
     */
    public function learn($text, $isSpam)
    {
        if (!$this->dictionary) {
            throw new \BadMethodCallException('Missing dictionary instance');
        }

        $this->dictionary->addWordsToCategory($isSpam ? 'spam' : 'ham', $this->tokenize($text));

        return $this;
    }

    /**
     * Calculates the probability of being spam based on list of words
     * Only words with 3 or more chars are used
     * Returns grade (negative values are bad, positive are good)
     *
     * @param string $text   text to be checked
     * @param int    $weight grade weight
     *
     * @return float
     */
    public function prop($text, $weight = 10)
    {
        if (!$this->dictionary) {
            return null;
        }

        $keywords = $this->tokenize($text);
        $keywords = array_count_values($keywords);

        $sets = $this->dictionary->getCategories(array('ham', 'spam'));

        $words = array();
        foreach (array_keys($sets) as $set) {
            $words[$set] = $this->dictionary->getWordsFromCategory($set, array_keys($keywords));
        }

        $score = array();
        foreach ($sets as $set => $sum) {
            $sum = $sum ? : 1;

            $part = array();
            foreach ($keywords as $word => $count) {
                if (empty($words[$set][$word])) {
                    continue;
                }

                $part[$word] = log($words[$set][$word] / $sum) * $count;
            }

            $score[$set] = array_sum($part);
        }

        return round($score['spam'] - $score['ham'], 4) * $weight;
    }

    /**
     * Splits string into defined tokens with tokens longer thant $minLen and no more tokens than $maxTokenNum
     *
     * @param string $str
     * @param int    $minLen
     * @param int    $maxTokenNum
     *
     * @return array
     */
    public function tokenize($str, $minLen = 3, $maxTokenNum = null)
    {
        preg_match_all('#<\s*(?:a|frame|iframe|form)[^>]*\s+(?:href|src|URL|action)\s*=\s*["\']?(?!mailto:|news:|javascript:|ftp:|telnet:|callto:|ed2k:)([^"\'\#\s>]+)#is', $str, $urls);

        $str = strip_tags($str);
        $str = mb_strtolower($str, 'UTF8');
        $str = preg_replace('/[^\pL? ]+/ui', ' ', $str);

        $tokens = (array) preg_split('/[\pZ\pC]+/ui', $str, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_merge($tokens, $urls[1]);

        if ($minLen !== null) {
            $tokens = array_filter(
                $tokens,
                function ($token) use ($minLen) {
                    return strlen($token) >= $minLen;
                }
            );
        }

        if ($maxTokenNum !== null) {
            $tokens = array_slice($tokens, 0, $maxTokenNum);
        }

        return array_values($tokens);
    }

    /**
     * Rates authors history (based on user identifier)
     *
     * @param int|string $author
     * @param int        $weight
     *
     * @return int
     */
    public function history($author, $weight = 1)
    {
        if (!$this->history) {
            return null;
        }

        return $this->history->history($author) * $weight;
    }

    /**
     * Checks if entry with same text exists
     *
     * @param array $text
     * @param int   $weight
     *
     * @return int
     */
    public function exists($text, $weight = 100)
    {
        if (!$this->history) {
            return null;
        }

        return $this->history->exists($text) * $weight;
    }

    /**
     * Grades honeypot
     * If honeypot is empty - grade is equal to 0, otherwise equals -weight
     *
     * @param string $honeypot
     * @param int    $weight grade weight
     *
     * @return int
     */
    public function honeypot($honeypot, $weight = 100)
    {
        return (empty($honeypot) ? 0 : -1) * $weight;
    }

    /**
     * Grades referrer
     * If referrer is missing or invalid - grade is equal to -weight, otherwise equals 0
     * Returns grade (negative values are bad, positive are good)
     *
     * @param string $referrer
     * @param string $expected
     * @param int    $weight grade weight
     *
     * @return int
     */
    public function referrer($referrer, $expected, $weight = 100)
    {
        return !($referrer == $expected) * -$weight;
    }

    /**
     * Grades time elapsed from rendering form to its submission
     * Better grade for being lazy
     *
     * @param \DateTime $datetime
     * @param int       $weight
     *
     * @return int
     */
    public function elapsed(\DateTime $datetime, $weight = 1)
    {
        return (time() - $datetime->getTimestamp()) * $weight;
    }
}