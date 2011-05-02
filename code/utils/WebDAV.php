<?php

class WebDAV {

	static function curl_init($url, $method) {
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		return $ch;
	}

	static function exists($url) {
		$ch = self::curl_init($url, 'PROPFIND');
		
		$res = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($code == 404) return false;
		if ($code == 200 || $code == 207) return true;

		user_error("Got error from webdav server - ".$code, E_USER_ERROR);
	}

	static function mkdir($url) {
		$ch = self::curl_init(rtrim($url, '/').'/', 'MKCOL');

		$res = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return $code == 201;
	}

	static function put($handle, $url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

		curl_setopt($ch, CURLOPT_PUT, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

		curl_setopt($ch, CURLOPT_INFILE, $handle);

		$res = curl_exec($ch);
		fclose($handle);

		return curl_getinfo($ch, CURLINFO_HTTP_CODE);
	}

	static function upload_from_string($string, $url) {
		$fh = tmpfile();
		fwrite($fh, $string);
		fseek($fh, 0);
		return self::put($fh, $url);
	}

	static function upload_from_file($string, $url) {
		return self::put(fopen($string, 'rb'), $url);
	}


}