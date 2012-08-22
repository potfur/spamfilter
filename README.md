# SpamFilter

Indepentent spam filtering module.
Evaluates text and depending od received grade - marks it as spam or ham.

For licence details see Licence.md

For ``Request`` description see REQUEST.md

## Usage

``SpamFilter`` consists of two classes: ``SpamFilter`` where all rating logic is located, and custom class implementing ``KnowledgeInterface`` that provides access to storage eg. database.
As text evaluation is mostly based on gathered knowledge, filter should learn from existing texts (eg. from comments) using ``SpamFilter::learn()`` method.

Beside knowledge evaluation, filter allows to evaluate other text properties such as: text length, number of existing links, authors history etc.

### Methods

#### SpamFilter::learn($text, $spam = false)

Learns filtering from string

* ``$text`` text to learn from
* ``$spam`` flag defining if string is ham or spam


#### SpamFilter::rate($Text, Request $Request = null, $verbose = true)

Rates comment according to passed weights
If verbose is true - returns array containing all evaluation results.
Othervise returns status (integer)
+1 - is ham
 0  - possible spam
-1 - is spam

* ``$Text`` array containing two fields, one with ``text`` key containing text to rate, and second with key defined in ``KnowledgeInterface`` containing user identifier (eg login, e-mail)
* ``$Request`` ``Request`` instance used in some ratings
* ``$verbose`` flag trigging verbose (array) result


#### SpamFilter::links($text, $countLimit = 2, $lengthLimit = 30, $weight = 1)

Finds links in text and checks their count and length.
Grades text according to set limits
Returns grade (negative values are bad, positive are good)

* ``$text`` text to be checked
* ``$countLimit`` number of allowed links in text, all subsequent links are graded negatively
* ``$lengthLimit`` maximal link length, all links longer, are graded negatively
* ``$weight`` grade weight


#### SpamFilter::body($text, $limit = 60, $weight = 2)

Checks text length
If length is greater than limit - is graded positively.
Short text are graded negatively
Returns grade (negative values are bad, positive are good)

* ``$text`` text to be checked
* ``$limit`` minimal text length to be graded positively
* ``$weight`` grade weight


#### SpamFilter::prop($text, $weight = 10)

Calculates the probability of being spam based on wordlist
Only words with 3 or more chars are used
Returns grade (negative values are bad, positive are good)

* ``$text`` text to be checked
* ``$weight`` grade weight


#### SpamFilter::history($previousPostsCount, $weight = 1)

Grades text authors history.
Returns grade (negative values are bad, positive are good)

* ``$previousPostsCount`` number of posts non spam posts minus number of spam posts
* ``$weight`` grade weight


#### SpamFilter::existing($existingPostsCount, $weight = 100)

Grades text according to previously posted texts
Returns grade (negative values are bad, positive are good)

* ``$existingPostsCount`` number of equal texts
* ``$weight`` grade weight


#### SpamFilter::honeypot($emptyHoneypot, $weight = 100)

Grades honeypot
If honeypot is empty - grade is equal to 0, otherwise equals weight
Returns grade (negative values are bad, positive are good)

* ``$emptyHoneypot`` should be true if honeypot is empty
* ``$weight`` grade weight


#### SpamFilter::referer($refererExists, $weight = 100)

Grades referrer
If referrer is missing or invalid - grade is equal to weight, otherwise equals 0
Returns grade (negative values are bad, positive are good)

* ``$refererExists`` should be true if referer is exists and is equal to expected
* ``$weight`` grade weight


#### SpamFilter::elapsed($elapsedSeconds, $limit = 5, $weight = 100)

Grades time elapsed from rendering form to its submit
If elapsed time is greater than limit - grade is equal to weight
Otherwise equals weight * -1
Returns grade (negative values are bad, positive are good)

* ``$elapsedSeconds`` time elapsed from form render to submit
* ``$limit`` minimal time needed to submit form
* ``$weight`` grade weight


### KnowledgeInterface

Knowledge interface is required for ``SpamFilter`` to access knowledge (spam propabilities for words) and users history

#### KnowledgeInterface::getWord($word);

Retrieves word from knowledge repository.
Returns array same as that passed to ``KnowledgeInterface::putWord``

* ``$word`` string representing word


#### KnowledgeInterface::putWord($word);
* Puts word into knowledge repository

* ``$word`` array containing tree fields with keys: ``word`` containing word, ``spam`` number of occurences in spam, ``ham`` number of occurences in ham


#### KnowledgeInterface::getWords($words = array());

Returns data from knowledge repository matching passed words

* ``$words`` array containing words to retrieve from knowledge


#### KnowledgeInterface::history($Comment);

Checks authors history (based on authors email)
Returns number of ham comments minus number spam comments

* ``$Text`` - array containing two fields: ``text`` with evaluated text and second containig user identifier (eg. login, e-mail)


#### KnowledgeInterface::exists($Comment);

Checks if comment with same text exists
Returns true if exists

* ``$Text`` - array containing two fields: ``text`` with evaluated text and second containig user identifier (eg. login, e-mail)
