<?php

// Initial version of this plugin: https://github.com/atallo/ttrss_fullpost/
// Now with preference panel, ripped out of: https://github.com/mbirth/ttrss_plugin-af_feedmod
// Relies on the fivefilters port of Readability: http://code.fivefilters.org/php-readability/overview

// Expects the preference field to be URL fragments separated by either newlines:
//   kotaku.com
//   destructoid
//   arstechnica.com
// or commas:
//   kotaku.com, destructoid, arstechnica.com

// Note that this will consider the feed to match if the feed's "link" URL contains any
// element's text. Most notably, Destructoid's posts are linked through Feedburner, and
// so "destructoid.com" doesn't match--but there is a "Destructoid" in the Feedburner URL,
// so "destructoid" will. (Link comparisons are case-insensitive.)

class Af_Fullpost extends Plugin implements IHandler
{
	private $host;

	function about() {
		return array(1.30,
			"Full post (requires CURL).",
			"atallo");
	}
	
	function api_version() {
		return 2;
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TABS, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}
	
	function hook_article_filter($article) {
		if (!function_exists("curl_init"))
			return $article;
		
		$json_conf = $this->host->get($this, 'json_conf');
		$owner_uid = $article['owner_uid'];
		
		$data = explode(',', str_replace("\n", ",", $json_conf));
		// trim each element of the array, and then remove empty array elements
		$data = array_filter(array_map('trim', $data));
		if (!is_array($data)) {
			// no valid configuration, or no configuration at all
			return $article;
		}
		
		foreach ($data as $urlpart) {
			// skip this entry, if the URL doesn't match
			if (stripos($article['link'], $urlpart) === false) continue;
			
			// do not process an article more than once
			if (strpos($article['plugin_data'], "fullpost,$owner_uid:") !== false) {
				if (isset($article['stored']['content'])) $article['content'] = $article['stored']['content'];
				break;
			}
			
			// have Readability parse the page
			try {
				$article['content'] = $this->get_full_post($article['link']);
				$article['plugin_data'] = "fullpost,$owner_uid:" . $article['plugin_data'];
			} catch (Exception $e) {
				// Readability failed (?!); don't modify the article's content and keep going
			}
			break;
		}
		
		return $article;
	}
	
	private function get_full_post($request_url) {
		// now an amalgamation of code from:
		//   1) https://github.com/feelinglucky/php-readability
		//   2) http://code.fivefilters.org/php-readability/src
		include_once 'Readability.php';
		
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
		
		//if (!$charset = mb_detect_encoding($source)) {
		//}
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
		// print debug output? 
		$readability->debug = false;
		// convert links to footnotes?
		$readability->convertLinksToFootnotes = false;
		$result = $readability->init();
		
		if ($result) {
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
	
	function hook_prefs_tabs($args)
	{
		print '<div id="fullpostConfigTab" dojoType="dijit.layout.ContentPane"
					href="backend.php?op=af_fullpost"
					title="' . __('FullPost') . '"></div>';
	}
	
	function index()
	{
		$pluginhost = PluginHost::getInstance();
		$json_conf = $pluginhost->get($this, 'json_conf');
		
		print "<p>Comma- or newline-separated list of URLs or URL fragments (e.g. 'destructoid' for the Destructoid Feedburner URL) for sites where you want the full text of each article. Case does not matter.<br>Example: kotaku.com, destructoid, arstechnica.com</p>";
		print "<form dojoType=\"dijit.form.Form\">";
		
		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						if (transport.responseText.indexOf('error')>=0) notify_error(transport.responseText);
						else notify_info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";
		
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_fullpost\">";
		
		print "<table width='100%'><tr><td>";
		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" name=\"json_conf\" style=\"font-size: 12px; width: 99%; height: 500px;\">$json_conf</textarea>";
		print "</td></tr></table>";
		
		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button>";
		
		print "</form>";
	}
	
	function save()
	{
		$json_conf = $_POST['json_conf'];
		$this->host->set($this, 'json_conf', $json_conf);
		echo __("Configuration saved.");
	}
	
	function csrf_ignore($method)
	{
		$csrf_ignored = array("index", "edit");
		return array_search($method, $csrf_ignored) !== false;
	}
	
	function before($method)
	{
		if ($_SESSION["uid"]) {
			return true;
		}
		return false;
	}
	
	function after()
	{
		return true;
	}

}
?>
