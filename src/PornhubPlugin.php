<?php

namespace Proxy\Plugin;

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;

use Proxy\Html;

class PornhubPlugin extends AbstractPlugin {
	
	protected $url_pattern = 'pornhub.com';
	
	public function onCompleted(ProxyEvent $event){
		$response = $event['response'];
		
		$content = $response->getContent();
		
		if(preg_match('/"videoUrl":"([^"]+)/', $content, $matches)){
			$url = $matches[1];
			$url = str_replace('\\', '', $url);
			
			$player = vid_player($url, 989, 557);
			$content = Html::replace_inner('#player', $player, $content);
		}
		
		// too many ads
		$content = Html::remove_scripts($content);
		
		$response->setContent($content);
	}
}

