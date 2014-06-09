# SpamFilter

Independent _spam_ filtering module.
Evaluates text length, its content - how many links there are, used words, authors history and grades it.

For licence details see LICENCE

## Usage

`SpamFilter` consists of one class that contains all rating logic.
Also there are two interfaces, that need to be implemented on your own used by `SpamFilter` in rating.

  * `DictionaryInterface` - that provides access to place where information about word probabilities are stored;
  * `HistoryInterface` - interface implemented for author history and checking if same entry exists

As text evaluation is mostly based on gathered knowledge, filter should learn from existing texts (eg. from comments) using `SpamFilter::learn()` method.
Beside knowledge evaluation, filter allows to evaluate other text properties such as: text length, number of existing links, authors history etc.

```php
$dictionary = new Dictionary(); // DictionaryInterface implementation
$history = new History(); // HistoryInterface implementation

$filter = new SpamFilter($dictionary, $history);

$grade = $filter->rate(
	'Some text with <a href="http://spam.com">link</a>', // graded text
	1123, // user identifier
	'', // honeypot content
	'http://google.com', // received referrer
	'http://google.com', // expected referrer
	$submitted // form render time
);
```

### Methods

#### SpamFilter::learn($text, $spam = false)

Learns filtering from string.
Splits string into tokens and adds them to Dictionary

* `$text` text to learn from
* `$isSpam` flag defining if string is _ham_ or _spam_

#### SpamFilter::rate($text, $author = null, $honeypot = null, $referrer = null, $expectedReferrer = null, \DateTime $submitted = null, $verbose = false)

Grades `$text` using all available methods.

  * `$text` - text to be graded, few links in long texts preferably
  * `$author` - authors identifier that will be used to check its history,
  * `$honeypot` - value from honeypot field,
  * `$referrer` - referrer from request,
  * `$expectedReferrer` - expected referrer,
  * `$submitted` - `\DateTime` instance when entry was submitted, laziness is good,
  * `$verbose` - if true will return array with grades per method, otherwise will return its sum

#### SpamFilter::links($text, $countLimit = 2, $lengthLimit = 30, $weight = 1)

Finds links in text and checks their count and length.
Links should be short (up to 30 chars) and two or less.

  * `$text` - text to be checked
  * `$countLimit` - number of allowed links in text, all subsequent links are graded negatively
  * `$lengthLimit` - maximal link length, all links longer, are graded negatively
  * `$weight` - grade weight


#### SpamFilter::body($text, $limit = 60, $weight = 2)

Checks text length, longer is better.
If length is greater than limit - is graded positively.
Short text are graded negatively

  * `$text` - text to be checked
  * `$limit` - minimal text length to be graded positively
  * `$weight` - grade weight


#### SpamFilter::prop($text, $weight = 10)

Calculates the probability of being _spam_ based on dictionary
Only words with 3 or more chars are used

  * `$text` - text to be checked
  * `$weight` - grade weight

#### SpamFilter::history($author, $weight = 1)

Grades text authors history.
Returns grade (negative values are bad, positive are good)

  * `$author` - authors identifier
  * `$weight` - grade weight

#### SpamFilter::existing($text, $weight = 100)

Checks if `$text` and how many times can be found in repository.

  * `$existingPostsCount` - number of equal texts
  * `$weight` - grade weight


#### SpamFilter::honeypot($honeypot, $weight = 100)

Honeypot is a invisible field (hidden by CSS not by its type) that usually is filled by bots and left empty by humans.

  * `$honeypot` - honeypot content
  * `$weight` - grade weight


#### SpamFilter::referrer($referrer, $expected, $weight = 100)

Grades received referrer, should be same as expected

  * `$referrer` - received referrer
  * `$expected` - expected referrer
  * `$weight` - grade weight


#### SpamFilter::elapsed(\DateTime $datetime, $weight = 100)

Grades time from rendering form to its submission.
Lazy submission is better.

  * `$datetime` - time when form was rendered
  * `$weight` grade weight


### DictionaryInterface

#### DictionaryInterface::addWordsToCategory($category, array $words)

Adds words to dictionary
If words already exist in dictionary, increases their number of occurrences
If category is missing, creates it


#### DictionaryInterface::addWordToCategory($category, $word)

Adds word to dictionary
If word already exists in dictionary, increases its number of occurrences
If category is missing, creates it


#### DictionaryInterface::getCategories($categories = array())

Returns defined categories with their occurrences
If `$categories` is specified, only those words will be returned


#### DictionaryInterface::getWordsFromCategory($category, $words = array())

Returns array of `words`, where words are keys and their values correspond to number of occurrences in set `category`
If `$words` is specified, only those words will be returned


#### DictionaryInterface::getWordFromCategory($category, $word)

Should return number of occurrences of `$word` in `$category`.


### HistoryInterface

#### HistoryInterface::history($identifier)

Returns number of _ham_ entries with subtracted _spam_ entries for authors `$identifier`

#### HistoryInterface::exists($text)

Returns number of same entries as `$text`