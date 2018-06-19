<?php

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;
use Proxy\Config;

class LogPlugin extends AbstractPlugin {

	public function onHeadersReceived(ProxyEvent $event){
	
		// to use a custom logs folder set it on main config.php with $config['custom_logs_folder'] = '/var/logs';
		// else it will use default /storage/ folder, because this will be included in index.php -> php-proxy-app/storage
		$storage_dir = Config::get('custom_logs_folder') ? Config::get('custom_logs_folder') : realpath('./storage');
		
		if(!is_writable($storage_dir)){
			return;
		}
		
		$log_file = $storage_dir.'/'.date("Y-m-d").'.log';
		
		$request = $event['request'];
		$response = $event['response'];
		
		$data = array(
			'ip' => $_SERVER['REMOTE_ADDR'],
			'time' => time(),
			'url' => $request->getUri(),
			'status' => $response->getStatusCode(),
			'type' => $response->headers->get('content-type', 'unknown'),
			'size' => $response->headers->get('content-length', 'unknown')
		);
		
		$message = implode("\t", $data)."\r\n";
		
		@file_put_contents($log_file, $message, FILE_APPEND);
	}

}

?>
