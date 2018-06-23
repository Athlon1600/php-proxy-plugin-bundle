<?php

namespace Proxy\Plugin;

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;

use Proxy\Html;

class VimeoPlugin extends AbstractPlugin {

	protected $url_pattern = '/^(vimeo\.com|gcs-vimeo\.akamaized\.net)$/i';
	
	public function onBeforeRequest(ProxyEvent $event){
		$request = $event['request'];
		// get request url
		$request_url = $request->getUri();
		// get request hostname
		$request_hostname = parse_url($request_url, PHP_URL_HOST);
		if ($request_hostname ==='gcs-vimeo.akamaized.net') {
			// if it is akamai, clear cookies
			$event['request']->headers->remove('Cookie');
		}
	}

	private function json_src($matches){
		
		// Ignore empty\bad "url":"", or "src":"", 
		if(stripos($matches[1], "\/") !== 0 && !preg_match('/^https?:/i', $matches[1])) return $matches[0];

		// Strip slashes \ from URL
		$url = rawurldecode(stripslashes(trim($matches[1])));
		
		// Do not proxify already proxified URLs
		if(stripos($url, app_url()) === 0) return $matches[0];

		// Remove ?autoplay=1
		$url = str_replace('?autoplay=1', '', $url);
		
		// Proxify URL => JSON_Encode => Remove "
		$url_proxied = str_replace("\"", "", json_encode(proxify_url($url, $this->base_url)));
		
		// Return data with proxified URLs
		return str_replace($matches[1], $url_proxied, $matches[0]);
	}
	
	private function json_parse($matches){
		
		// Find and replace all JSON "url" and "src" fields
		$matches[0] = preg_replace_callback('/"url":"(.+?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"src":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"src_2x":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"src_4x":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"src_8x":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"thumbnail":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"thumbnail_2x":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"thumbnail_4x":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"thumbnail_8x":"(.*?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"link":"(.+?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"background_image_url":"(.+?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"background_image_url_2x":"(.+?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"portrait":"(.+?)"/', array($this, 'json_src'), $matches[0]);
		$matches[0] = preg_replace_callback('/"portrait_bg":"(.+?)"/', array($this, 'json_src'), $matches[0]);
		
		//Set autoplay to false else it appends &autoplay=1 to proxified URLs
		$matches[0] = str_replace(',"autoplay":true', ',"autoplay":false', $matches[0]);
		$matches[0] = str_replace('"name":"autoplay","choice":"on",', '"name":"autoplay","choice":"off",', $matches[0]);
		
		// Return data
		return $matches[0];
	}
	
	// determine if the passed argument can be treated like a string.
	private function is_stringy($text) {
		return (is_string($text) || (is_object($text) && method_exists($text, '__toString' )));
	}

	public function onCompleted(ProxyEvent $event){
		$request = $event['request'];
		// get request url
		$request_url = $request->getUri();
		// get request hostname
		$request_hostname = parse_url($request_url, PHP_URL_HOST);
		// if it is akamai, do nothing
		if ($request_hostname ==='gcs-vimeo.akamaized.net') {
			return;
		}
	
	    // Set HTML content
		$output = $event['response']->getContent();
		
		// Set base_url needed for proxify_url()
		$this->base_url = $event['request']->getUri();
		
		// Replace JSON https:\/\/... URLs inside scripts
		$output = preg_replace_callback('/<script[^>]*?>([\s\S]*?)<\/script>/', array($this, 'json_parse'), $output);
		
		$url = '';
		
		// Download URL that contains video info (method 1)
		if(preg_match('/\<meta property\=\"og\:video\:url\" content\=\"(.+?)\"\>/is', $output, $matches))
		{
			$url = str_replace('?autoplay=1', '', trim($matches[1]));
		}
		
		// Download URL that contains video info (method 2)
		if(!$url)
		{
		    if(preg_match('/\<meta name\=\"twitter:player\" content\=\"(.+?)\"\>/is', $output, $matches))
			$url = str_replace('?autoplay=1', '', trim($matches[1]));
		}
		
		// Download URL that contains video info (method 3) ***data is JSON***
		if(!$url)
		{
			if(preg_match('/xhr\.open\(["\']GET["\'],\s*["\'](.+?)["\']/is', $output, $matches))
			$url = trim($matches[1]);
		}

		// If we have the URL with video info
		if($url)
		{
			// Make sure the URL is valid
			if(filter_var($url, FILTER_VALIDATE_URL))
			{
				// Use cURL to download the URL content
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:50.0) Gecko/20100101 Firefox/50.0');
				curl_setopt($ch, CURLOPT_REFERER, $this->base_url);
				curl_setopt($ch, CURLOPT_TIMEOUT, 5);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, False);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, False);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, False);
				$result = curl_exec($ch);
				curl_close($ch);
				
				// If we have URL content
			    if($result)
				{
					// Extract JSON data
					$json = preg_match('/\(function\(\w+\,\w+\)\{var \w+=(.+?)\;if\(\!\w+\.request\)/is', $result, $matches) ? $matches[1] : $result;

					// If we have JSON data
					if($json)
					{
						// Decode JSON data
						$decoded = json_decode($json, true);
						
						// If we have video info
						if($decoded['request']['files']['progressive'])
						{
							// Set our array for video URLs
							$urls = array();
						
							// Loop each array item and get video URLs
							foreach($decoded['request']['files']['progressive'] as $video)
							{
								if($video['url'])
								{
									// Remove the "p" so we get video quality as numbers (i.e 270, 360, etc)
									//$urls[str_replace("p", "", $video['quality'])] = rawurldecode(stripslashes(trim($video['url'])));
									$urls[str_replace("p", "", $video['quality'])] = stripslashes(trim($video['url']));
								}
							}
						
							// If we have at least one URL
							if(count($urls)>0)
							{
								// First try to set preferred (low) video quality URL
								$video_url = isset($urls['270']) ? $urls['270'] : $urls['360'];
							
								// In case there is no URL of that quality, try to get better quality
								if(!$video_url) $video_url = isset($urls['540']) ? $urls['540'] : $urls['720'];

								// In case there is no URL of that quality, try to get HD quality
								if(!$video_url) $video_url = $urls['1080']; 

								// Validate the video URL
								if(filter_var($video_url, FILTER_VALIDATE_URL))
								{
									// Set video URL on HTML5 player
									$player = vid_player($video_url, 973, 547, 'mp4');

									// Replace original player container with our player
									$output = Html::replace_inner(".player_area", $player, $output);
								}
							}
						}
					}
				}
			}
		}
		
		// fix header
		$css='<style type="text/css">.wrap_content { padding-top: unset !important; }</style>';
		$topnav = Html::extract_inner("#topnav_outer_wrap", $output);
		if (isset($topnav[0]) && $this->is_stringy($topnav[0])){
			$topnav=$css.$topnav[0];
			$output = Html::replace_inner("#topnav_outer_wrap", $topnav, $output);
		}

		// Show video info
		$clip_main = Html::extract_inner(".clip_main", $output);
		if (isset($clip_main[0]) && $this->is_stringy($clip_main[0])){
			$clip_main = preg_replace('%</?noscript>%m', '', $clip_main[0]);
			$output = Html::replace_inner(".clip_main", $clip_main, $output);
			// Selector support of Html::find is not good. We have to use illegal selector.
			// USE phpQuery or QueryPath in the future?
			$output = Html::replace_outer(".row u-collapse clip-notification", "", $output);
		}
		$event['response']->setContent($output);
	}
}

?>
