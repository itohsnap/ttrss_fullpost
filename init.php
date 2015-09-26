<?php
// Initial version of this plugin: https://github.com/atallo/ttrss_fullpost/
// Now with preferences ripped out of fox's af_readability plugin: https://tt-rss.org/gitlab/fox/tt-rss/tree/master/plugins/af_readability
// Relies on the fivefilters port of Readability: http://code.fivefilters.org/php-readability/overview

class Af_Fullpost extends Plugin {
	private $host;

	function about() {
		return array(1.40,
			"Alternate attempt to inline article content using Readability",
			"itohsnap");
	}
	
	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
	}
	
	function hook_article_filter($article) {
		// Stop if we don't have curl (i.e. some way to get the article's HTML)
		if (!function_exists("curl_init")) return $article;
		
		// Stop if the user hasn't checked the Full Post checkbox in this particular feed's settings
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		$key = array_search($article["feed"]["id"], $enabled_feeds);
		if ($key === FALSE) return $article;
		
		// Have Readability parse the page
		try {
			$article['content'] = $this->get_full_post($article['link']);
		} catch (Exception $e) {
			// Readability failed (?!); don't modify the article's content and keep going
		}
		
		return $article;
	}
	
	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;
		
		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('FullPost settings')."\">";
		print_notice("Enable the plugin for specific feeds in the feed editor.");
		
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();
		$enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
		$this->host->set($this, "enabled_feeds", $enabled_feeds);
		
		if (count($enabled_feeds) > 0) {
			print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";
			print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
			foreach ($enabled_feeds as $f) {
				print "<li>" .
					"<img src='images/pub_set.png'
						style='vertical-align : middle'> <a href='#'
						onclick='editFeed($f)'>".
					getFeedTitle($f) . "</a></li>";
			}
			print "</ul>";
		}
		print "</div>";
	}
	
	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("FullPost")."</div>";
		print "<div class=\"dlgSecCont\">";
		
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();
		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";
		
		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"af_fullpost_enabled\"
			name=\"af_fullpost_enabled\"
			$checked>&nbsp;<label for=\"af_fullpost_enabled\">".__('Fetch full post')."</label>";
		print "</div>";
	}

	function hook_prefs_save_feed($feed_id) {
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();
		$enable = checkbox_to_sql_bool($_POST["af_fullpost_enabled"]) == 'true';
		$key = array_search($feed_id, $enabled_feeds);
		
		if ($enable) {
			if ($key === FALSE) {
				array_push($enabled_feeds, $feed_id);
			}
		} else {
			if ($key !== FALSE) {
				unset($enabled_feeds[$key]);
			}
		}
		$this->host->set($this, "enabled_feeds", $enabled_feeds);
	}
	
	function save() {
		//
	}
	
	function api_version() {
		return 2;
	}
	
	private function get_full_post($request_url) {
		// now an amalgamation of code from:
		//   1) https://github.com/feelinglucky/php-readability
		//   2) http://code.fivefilters.org/php-readability/src
		
		// Option 1: Use the version of Readability that we've been using (and it has been pretty solid). To use this, you need to include the "Readability.php" and "JSLikeHTMLElement.php" files in the af_fullpost folder.
		//   include_once 'Readability.php';
		// Option 2: use TT-RSS' now-built-in copy of Readability, which is ~ the same (but maybe a touch better?)
		if (!class_exists("Readability")) require_once(dirname(dirname(__DIR__)). "/lib/readability/Readability.php");
		
		$handle = curl_init();
		curl_setopt_array($handle, array(
			CURLOPT_USERAGENT => USER_AGENT,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER  => false,
			CURLOPT_HTTPGET => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_URL => $request_url
		));
		
		$html = curl_exec($handle);
		curl_close($handle);
		
		// Try to fix encoding issues
		preg_match("/charset=([\w|\-]+);?/", $html, $match);
		$charset = isset($match[1]) ? $match[1] : 'utf-8';
		$html = mb_convert_encoding($html, 'UTF-8', $charset);
		
		// If we've got Tidy, let's clean up input.
		// This step is highly recommended - PHP's default HTML parser often doesn't do a great job and results in strange output.
		if (function_exists('tidy_parse_string')) {
			$tidy = tidy_parse_string($html, array(), 'UTF8');
			$tidy->cleanRepair();
			$html = $tidy->value;
		}
		
		$readability = new Readability($html);
		// $readability->debug = true;
		
		if ($readability->init()) {
			// $title = $readability->getTitle()->textContent;
			$content = $readability->getContent()->innerHTML;
			// if we've got Tidy, let's clean it up for output
			if (function_exists('tidy_parse_string')) {
				$tidy = tidy_parse_string($content, array('indent'=>true, 'show-body-only' => true), 'UTF8');
				$tidy->cleanRepair();
				$content = $tidy->value;
			}
		} else {
			# Raise an error so that we know not to replace the RSS stub article with something even less helpful
			throw new Exception('Full-text extraction failed');
		}
		
		return $content;
	}
	
	private function filter_unknown_feeds($enabled_feeds) {
		$tmp = array();
		foreach ($enabled_feeds as $feed) {
			$result = db_query("SELECT id FROM ttrss_feeds WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);
			if (db_num_rows($result) != 0) {
				array_push($tmp, $feed);
			}
		}
		return $tmp;
	}
}
?>
