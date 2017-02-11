<?php

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;
use Proxy\Config;
use Proxy\Proxy;

class SecurityPlugin extends AbstractPlugin {
	
	private function ErrorMsg($error){
		
		if(Config::get("error_redirect")){
			
			$url = render_string(Config::get("error_redirect"), array(
				'error_msg' => $error
			));
			
			header("HTTP/1.1 302 Found");
			header("Location: {$url}");
			
		}
		else {
			
			//$url = render_string(app_url()."?error={error_msg}", array(
				//'error_msg' => $error
			//));
			
			//header("HTTP/1.1 302 Found");
			//header("Location: {$url}");
			
			echo render_template("./templates/main.php", array(
				'url' => $url,
				'error_msg' => $error,
				'version' => Proxy::VERSION
			));
			
		}
		
		exit();
	}

	public function onBeforeRequest(ProxyEvent $event){

		// Get URL
		$url = $event['request']->getUri();
        
		// Do not proxify invalid URLs
		if(!filter_var($url, FILTER_VALIDATE_URL)){
			$this->ErrorMsg("URL is not valid");
		}
		
		// Do not proxify URLs that contain pattern of hidden folders or files
		if(preg_match('/(\/\.|\.\.)/is', $url)){
			$this->ErrorMsg("URL is not valid");
		}
		
		// Do not proxify invalid scheme
		if(!in_array(strtolower(parse_url($url, PHP_URL_SCHEME)), array("http","https","ftp"))){
			$this->ErrorMsg("Scheme is not allowed");
		}
		
		// Remove "www." from host
		$url_host = preg_replace('/^www\./is', '', trim(parse_url($url, PHP_URL_HOST)));
		
		// Do not proxify localhost
		if(preg_match('/^localhost/is', $url_host)){
			$this->ErrorMsg("Host is not allowed");
		}
		
		// Do not proxify internal IP addresses
		if(filter_var($url_host, FILTER_VALIDATE_IP)){
			if(filter_var($url_host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false){
				$this->ErrorMsg("Host is not allowed");
			}
		}
		
		// Do not proxify our own proxy host ($_SERVER['HTTP_HOST'])
		if(stripos($url_host, $_SERVER['HTTP_HOST']) === 0){
			$this->ErrorMsg("Host is not allowed");
		}
		
		// Remove "www." from app_url()
		$app_host = preg_replace('/^www\./is', '', trim(parse_url(app_url(), PHP_URL_HOST)));
		
		// Do not proxify our own proxy host (app_url())
		if(stripos($url_host, $app_host) === 0){
			$this->ErrorMsg("Host is not allowed");
		}
		
		// Do not proxify the server's IP address
		if(filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP)){
		    if($url_host == $_SERVER['SERVER_ADDR']){
				$this->ErrorMsg("Host is not allowed");
			}
		} 
		
	}
	
}

?>
