<?php

/*
* This file is part of the spamfilter package
*
* (c) Michal Wachowski <wachowski.michal@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace tests\SpamFilter;


use SpamFilter\SpamFilter;

class SpamFilterTest extends \PHPUnit_Framework_TestCase
{

    public function testOptions()
    {
        $options = array(
            'linksCountLimit' => 12,
            'linksLengthLimit' => 130,
            'links' => 11,

            'bodyMinLength' => 160,
            'body' => 12,

            'prop' => 110,
            'history' => 11,
            'exists' => 1100,
            'honeypot' => 1100,
            'referrer' => 1100,
            'elapsed' => 11
        );

        $filter = new SpamFilter();
        $this->assertEquals($options, $filter->options($options));
    }

    /**
     * @dataProvider linksProvider
     */
    public function testLinksRating($string, $expected)
    {
        $filter = new SpamFilter();
        $this->assertEquals($expected, $filter->links($string));
    }

    public function linksProvider()
    {
        return array(
            array('This is a sample text', 2),
            array('This <a href="https://foo.com">is</a> a sample text', 1),
            array('This <a href="ftp://foo.com">is</a> a https://bar.net sample text', 0),
            array('This <a href="http://foo.com/?yada=yada">is</a> a https://bar.net sample ftp://www.yada.yada.pl text', -1),
            array('This <a href="http://foo-foo.com/bar-bar/yada-yada.html">is</a> a https://bar.net text ftp://www.yada.yada.pl text www.foo.bar.dot.com', -2.5)
        );
    }

    /**
     * @dataProvider bodyProvider
     */
    public function testBodyRating($string, $expected)
    {
        $filter = new SpamFilter();
        $this->assertEquals($expected, $filter->body($string));
    }

    public function bodyProvider()
    {
        return array(
            array('Short text, to short to be positive', -2),
            array('Long text <a href="http://that-would-be-positively-graded-but-links-in-">anchors</a> are removed.', -2),
            array('Text exceeding limit, that checks if grading for 60 char long string works.', 2.5),
            array('Very long text that exceeds limit, so test can check if very very long texts are promoted, this text should be better than previous because its longer.', 5.0334)
        );
    }

    public function testTokenizeWithTags()
    {
        $string = 'String <a href="http://google.com">that has</a> tags with urls';
        $expected = array('string', 'that', 'has', 'tags', 'with', 'urls', 'http://google.com');

        $filter = new SpamFilter();
        $this->assertEquals($expected, $filter->tokenize($string, 3));
    }

    public function testTokenizeWithWordLengthLimit()
    {
        $string = 'Simple string to tokenize';
        $expected = array('simple', 'string', 'tokenize');

        $filter = new SpamFilter();
        $this->assertEquals($expected, $filter->tokenize($string, 3));
    }

    public function testTokenizeWithWordNumberLimit()
    {
        $string = 'Simple string to tokenize';
        $expected = array('simple', 'string');

        $filter = new SpamFilter();
        $this->assertEquals($expected, $filter->tokenize($string, null, 2));
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Missing dictionary instance
     */
    public function testLearnWithoutDictionaryInstance()
    {
        $filter = new SpamFilter();
        $filter->learn('Learning text marked as ham', false);
    }

    public function testLearn()
    {
        $dictionary = $this->getMock('\SpamFilter\DictionaryInterface');

        $dictionary->expects($this->once())
            ->method('addWordsToCategory')
            ->with('ham', array('learning', 'text', 'marked', 'ham'));

        $filter = new SpamFilter($dictionary);
        $filter->learn('Learning text marked as ham', false);
    }

    /**
     * @dataProvider probabilityProvider
     */
    public function testProbabilityRating($string, $knowledge, $expected)
    {
        $dictionary = $this->getMock('\SpamFilter\DictionaryInterface');

        $dictionary->expects($this->any())
            ->method('getCategories')
            ->will($this->returnValue(array('spam' => array_sum($knowledge), 'ham' => 0)));

        $dictionary->expects($this->at(1))
            ->method('getWordsFromCategory')
            ->will($this->returnValue($knowledge['ham']));

        $dictionary->expects($this->at(2))
            ->method('getWordsFromCategory')
            ->will($this->returnValue($knowledge['spam']));

        $filter = new SpamFilter($dictionary);
        $this->assertEquals($expected, $filter->prop($string));
    }

    public function probabilityProvider()
    {
        return array(
            array(
                'no spam at all',
                array('ham' => array(), 'spam' => array()),
                0
            ),
            array(
                'short text with short spam',
                array('ham' => array(), 'spam' => array('spam' => 10)),
                -23.0260
            ),
            array(
                'short text with equal spam and ham',
                array('ham' => array('ham' => 10), 'spam' => array('spam' => 10)),
                0
            ),
            array(
                'short spam text with repeated spam word',
                array('ham' => array(), 'spam' => array('spam' => 10)),
                -46.0520
            )
        );
    }

    public function testAuthorsHistoryRating()
    {
        $history = $this->getMock('\SpamFilter\HistoryInterface');

        $history->expects($this->once())
            ->method('history')
            ->with(1);

        $filter = new SpamFilter(null, $history);
        $filter->history(1);
    }

    public function testExistingEntriesRating()
    {
        $history = $this->getMock('\SpamFilter\HistoryInterface');

        $history->expects($this->once())
            ->method('exists')
            ->with('Some text');

        $filter = new SpamFilter(null, $history);
        $filter->exists('Some text');
    }

    public function testHoneypotField()
    {
        $filter = new SpamFilter();
        $this->assertEquals(0, $filter->honeypot(''));
        $this->assertEquals(-100, $filter->honeypot('123'));
    }

    public function testReferrerAccordance()
    {
        $filter = new SpamFilter();
        $this->assertEquals(0, $filter->referrer('123', '123'));
        $this->assertEquals(-100, $filter->referrer('123', 'abc'));
    }

    public function testElapsedTime()
    {
        $filter = new SpamFilter();
        $this->assertEquals(10, $filter->elapsed(new \DateTime('-10seconds')));
        $this->assertEquals(0, $filter->elapsed(new \DateTime()));
    }

    public function testRateVerbose()
    {
        $expected = array(
            'links' => 1, // one link, more thant 2 will be graded negatively
            'body' => -2, // short post, not good
            'prop' => 36.8890, // 2 popular words and one spam url
            'history' => 1, // one entry in history, that's good
            'exists' => 0, // no identical posts in history
            'honeypot' => 0, // honeypot was empty
            'referrer' => 0, // referrer was same
            'elapsed' => 5, // 5 seconds elapsed, that is good
        );

        $dictionary = $this->getMock('\SpamFilter\DictionaryInterface');

        $dictionary->expects($this->at(0))
            ->method('getCategories')
            ->will($this->returnValue(array('spam' => 0, 'ham' => 20)));

        $dictionary->expects($this->at(1))
            ->method('getWordsFromCategory')
            ->will($this->returnValue(array('http://spam.com' => 10)));

        $dictionary->expects($this->at(2))
            ->method('getWordsFromCategory')
            ->will($this->returnValue(array('some' => 10, 'text' => 10)));

        $history = $this->getMock('\SpamFilter\HistoryInterface');

        $history->expects($this->any())
            ->method('history')
            ->will($this->returnValue(1));

        $history->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(0));

        $filter = new SpamFilter($dictionary, $history);
        $result = $filter->rate(
            'Some text with <a href="http://spam.com">link</a>',
            1,
            '',
            'http://ham.com',
            'http://ham.com',
            new \DateTime('-5seconds'),
            true
        );

        $this->assertEquals($expected, $result);
    }

    public function testRate()
    {
        $dictionary = $this->getMock('\SpamFilter\DictionaryInterface');

        $dictionary->expects($this->at(0))
            ->method('getCategories')
            ->will($this->returnValue(array('spam' => 0, 'ham' => 20)));

        $dictionary->expects($this->at(1))
            ->method('getWordsFromCategory')
            ->will($this->returnValue(array('http://spam.com' => 10)));

        $dictionary->expects($this->at(2))
            ->method('getWordsFromCategory')
            ->will($this->returnValue(array('some' => 10, 'text' => 10)));

        $history = $this->getMock('\SpamFilter\HistoryInterface');

        $history->expects($this->any())
            ->method('history')
            ->will($this->returnValue(1));

        $history->expects($this->once())
            ->method('exists')
            ->will($this->returnValue(0));

        $filter = new SpamFilter($dictionary, $history);
        $result = $filter->rate(
            'Some text with <a href="http://spam.com">link</a>',
            1,
            '',
            'http://google.com',
            'http://google.com',
            new \DateTime('-5seconds'),
            false
        );

        $this->assertEquals(41.889, $result);
    }

    public function testRateWithoutInstances()
    {
        $expected = array(
            'links' => 1, // one link, more thant 2 will be graded negatively
            'body' => -2, // short post, not good
            'prop' => null, // missing dictionary instance,
            'history' => null, // missing history instance
            'exists' => null, // missing history instance
            'honeypot' => 0, // honeypot was empty
            'referrer' => 0, // referrer was same
            'elapsed' => 5, // 5 seconds elapsed, that is good
        );

        $filter = new SpamFilter();
        $result = $filter->rate(
            'Some text with <a href="http://spam.com">link</a>',
            1,
            '',
            'http://google.com',
            'http://google.com',
            new \DateTime('-5seconds'),
            true
        );

        $this->assertEquals($expected, $result);
    }
}
 