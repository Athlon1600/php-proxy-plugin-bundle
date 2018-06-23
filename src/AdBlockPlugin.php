<?php

use Proxy\Plugin\AbstractPlugin;
use Proxy\Event\ProxyEvent;

class AdBlockPlugin extends AbstractPlugin {

	public function onBeforeRequest(ProxyEvent $event){
		$request = $event['request'];
		// load adblock serverlist
		$ab_file = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'serverlist.txt');
		// do nothing if loading fails
		if ($ab_file===false){
			return;
		}
		// remove comments
		$ab_list = preg_replace('/\s*#.*$/m', '', $ab_file);
		// parse hostnames
		$ab_hostnames = explode(",", $ab_list);
		// get request url
		$request_url = $request->getUri();
		// get request hostname
		$request_hostname = parse_url($request_url, PHP_URL_HOST);
		// check if hostname is blacklisted
		if (in_array($request_hostname, $ab_hostnames)) {
			//$request->setUrl("http://0.0.0.0/null.routed");
			throw new \Exception("Host is not allowed");
		}
	}
}

?>