ttrss_fullpost
==============
**October 2016 Update:** The version of this plugin fox has built into TT-RSS, "af_readability", now seems to just work (earlier versions had issues with multi-byte encoding, IIRC). Given that, and some change in recent versions of TT-RSS that's broken this plugin, I have a hard time justifying the time to maintain this... and a similarly hard time arguing that you shouldn't just use the built-in version.

--

A Tiny Tiny RSS plugin to convert feeds with partial posts into feeds with full posts, using the fivefilters version of PHP-Readability.

(If you want to run all but a few feeds through the full-text processor, [ManuelW77's fork](https://github.com/ManuelW77/ttrss_fullpost) might suit your needs better.)


Installation
------------------------

Create an "af_fullpost" folder in your TT-RSS "plugins" folder, and put a copy of "init.php" into it.


Configuration
------------------------

Right-click on a feed in TT-RSS and choose "Edit Feed". In the dialog that pops up, check the "Fetch full post" checkbox, under the FullPost section.

You can also view all sites that you're fetching full posts for in the "Feeds" tab of the TT-RSS Preferences, under the "FullPost settings" section.


References
------------------------

The original version (and all credit for the idea) of this is by [atallo](https://github.com/atallo/ttrss_fullpost). 

The feed selection and whatnot code was ripped out of fox's [af\_readability](https://tt-rss.org/gitlab/fox/tt-rss/tree/master/plugins/af_readability) plugin.

PHP-Readability is the [fivefilters](http://code.fivefilters.org/php-readability/overview) variant.

Some code from the previous [PHP-Readability library](https://github.com/feelinglucky/php-readability) is still used.
