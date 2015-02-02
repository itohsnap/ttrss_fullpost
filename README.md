ttrss_fullpost
==============

A Tiny Tiny RSS plugin to convert feeds with partial posts into feeds with full posts, using the fivefilters version of PHP-Readability. A preference tab lets you specify which feeds you want processed.

(If you want to run all but a few feeds through the full-text processor, [ManuelW77's fork](https://github.com/ManuelW77/ttrss_fullpost) might suit your needs better... which also inspired my switching versions of PHP-Readability.)


Installation
------------------------

Create an "af_fullpost" folder in your TT-RSS "plugins" folder. Put copies of "init.php", "Readability.php", and "JSLikeHTMLElement.php" into that folder.


Configuration
------------------------

In the TT-RSS preferences, you should now find a new tab called "FullPost." In that tab is a giant text field, where you can specify the feeds you want to run through PHP-Readability either in a comma-separated list:

    kotaku.com, destructoid, arstechnica.com

or in a newline-separated list:

    kotaku.com
    destructoid
    arstechnica.com
    
**Earlier users:** this should no longer be a JSON array!

Note that this will consider the feed to match if the feed's "link" URL contains any element's text. Most notably, Destructoid's posts are linked through Feedburner, and so "destructoid.com" doesn't match--but there is a "Destructoid" in the Feedburner URL, so "destructoid" will. (Link comparisons are case-insensitive.)


References
------------------------

The original version of this (and all credit for the idea): https://github.com/atallo/ttrss_fullpost

The preference pane code was pretty much ripped wholesale from: https://github.com/mbirth/ttrss_plugin-af_feedmod

PHP-Readability is now the fivefilters variant: http://code.fivefilters.org/php-readability/overview

Some code from the previous PHP-Readability library is still used: https://github.com/feelinglucky/php-readability
