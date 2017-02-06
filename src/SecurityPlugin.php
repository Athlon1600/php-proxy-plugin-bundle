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
