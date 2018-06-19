<?php

namespace Proxy\Plugin;

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;

use Proxy\Html;

class XHamsterPlugin extends AbstractPlugin {

	protected $url_pattern = 'xhamster.com';
	
	private function find_video($html){

		$file = false;
		
		if(preg_match("/\"mp4File\"\s*:\s*\"([^\"]+)\"/", $html, $matches)){
			$file = rawurldecode(trim($matches[1]));

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $file);
			curl_setopt($ch, CURLOPT_HEADER, 1);
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
			
			$file = preg_match('/Location\:\s*(.+?)($|\n)/is', $result, $matches) ? $matches[1] : $file;
			
		} else if(preg_match("/\"480p\"\:\"(.+?)\"/s", $html, $matches)){
			$file = rawurldecode(stripslashes(trim($matches[1])));
		} else if(preg_match("/\"720p\"\:\"(.+?)\"/s", $html, $matches)){
			$file = rawurldecode(stripslashes(trim($matches[1])));
		}
		
		return $file;
	}
	
	private function img_sprite($matches){
		return str_replace($matches[1], proxify_url($matches[1], $matches[1]), $matches[0]);
	}

	public function onCompleted(ProxyEvent $event){
	
		$response = $event['response'];
		$content = $response->getContent();
		$this->base_url = $event['request']->getUri();
		
		// remove ts_popunder stuff
		$content = preg_replace('/<script[^>]*no-popunder[^>]*><\/script>/m', '', $content);
		
		$content = preg_replace_callback('/<img[^>]*sprite=\'(.*?)\'/im', array($this, 'img_sprite'), $content);
		
		// are we on a video page?
		$file = $this->find_video($content);
		
		if($file){
		
			$player = vid_player($file, 638, 504);
			
			$content = Html::replace_inner("#playerSwf", $player, $content);
		}
		
		$response->setContent($content);
	}
}

?>
