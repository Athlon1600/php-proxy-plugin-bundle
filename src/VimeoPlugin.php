<?php

namespace Proxy\Plugin;

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;

use Proxy\Html;

class VimeoPlugin extends AbstractPlugin {

	protected $url_pattern = 'vimeo.com';
	
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
	
	public function onCompleted(ProxyEvent $event){
	
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
			if(preg_match('/XMLHttpRequest;e\.open\("GET","(.+?)"/is', $output, $matches))
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
					$json = preg_match('/\(function\(e\,a\)\{var t=(.+?)\;if\(\!t\.request\)/is', $result, $matches) ? $matches[1] : $result;

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
									// Remove the "p" so we can ksort() the array
									$urls[str_replace("p", "", $video['quality'])] = rawurldecode(stripslashes(trim($video['url'])));
								}
							}
						
							// If we have at least one URL
							if(count($urls)>0)
							{
								// Sort array based on quality (lower to high)
								ksort($urls);

								// First try to set preferred video quality URL
								$video_url = $urls['540'] ? $urls['540'] : $urls['720'];
							
								// In case there is no URL of that quality, get the HD quality
								if(!$video_url) $video_url = $urls['1080']; 

								// In case there is no URL of that quality, get the lowest quality
								if(!$video_url) $video_url = $urls['360']; 
								
								// Validate the video URL
								if(filter_var($video_url, FILTER_VALIDATE_URL))
								{
									// Set video URL on HTML5 player
									$player = vid_player($video_url, 973, 547, 'mp4');

									// Replace original player container with our player
									$output = Html::replace_inner(".app_banner_container", $player, $output);
								}
							}
						}
					}
				}
			}
		}
		
		$event['response']->setContent($output);
	}
}

?>
