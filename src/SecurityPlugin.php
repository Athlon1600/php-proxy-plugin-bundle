<?php

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;
use Proxy\Config;
use Proxy\Proxy;

class SecurityPlugin extends AbstractPlugin {

	public function onBeforeRequest(ProxyEvent $event){

		// Get URL and trim it (important for FILTER_VALIDATE_URL)
		$url = trim($event['request']->getUri());
		
		// Remove "www." from url's host, example: www.google.fr -> google.fr
		$url_host = preg_replace('/^www\./is', '', trim(parse_url($url, PHP_URL_HOST)));

		// Remove "www." from app_url(), example: www.proxyurl.com -> proxyurl.com
		$app_host = preg_replace('/^www\./is', '', trim(parse_url(app_url(), PHP_URL_HOST)));
		
		// Do not proxify invalid URLs
		if(!filter_var($url, FILTER_VALIDATE_URL)){
			throw new \Exception("URL is not valid");
		}
		
		// Do not proxify URLs with "/.htpasswd" or "/../" (hidden folders or files)
		if(preg_match('/(\/\.|\.\.)/is', $url)){
			throw new \Exception("URL is not valid");
		}
		
		// Do not proxify URLs with invalid or unsupported scheme
		if(!in_array(strtolower(parse_url($url, PHP_URL_SCHEME)), array("http","https"))){
			throw new \Exception("Scheme is not allowed");
		}
		
		// Do not proxify localhost
		if(preg_match('/^localhost/is', $url_host)){
			throw new \Exception("Host is not allowed");
		}
		
		// Do not proxify internal IP addresses
		if(filter_var($url_host, FILTER_VALIDATE_IP)){
			if(filter_var($url_host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false){
				throw new \Exception("Host is not allowed");
			}
		}
		
		// Do not proxify the server's IP address
		if(filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP)){
		    if($url_host == $_SERVER['SERVER_ADDR']){
				throw new \Exception("Host is not allowed");
			}
		}
		
		// Do not proxify our own proxy host ($_SERVER['HTTP_HOST'])
		if(stripos($url_host, $_SERVER['HTTP_HOST']) === 0){
			throw new \Exception("Host is not allowed");
		}
		
		// Do not proxify our own proxy host (app_url())
		if(stripos($url_host, $app_host) === 0){
			throw new \Exception("Host is not allowed");
		}
		
	}
	
}

?>
