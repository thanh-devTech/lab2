<?php
class CI_Api_LOINC
{
	private $apiBaseUrl = 'https://loinc.regenstrief.org/searchapi/loincs'; // Replace with the actual LOINC API base URL
	private $apiKey; // API key for authentication
	private $cache_file;
	private $cache_data = [];
	private $cache_expiry = 60 * 60; // Cache expiry time in seconds (1 hours)

	public function __construct($apiKey = 'cG50cmluaDg3OlRyaW5oQ29ybmVsIw==')
	{
		$this->apiKey = $apiKey;
		$this->cache_file = WP_CONTENT_DIR . '/cache/loinc_cache.json';
	}

	function getCachedata()
	{
		if (file_exists($this->cache_file) && empty($this->cache_data)) {
			$this->cache_data = json_decode(file_get_contents($this->cache_file), true);
		}
		return $this->cache_data;
	}

	function putCache($loincCode, $shortname)
	{
		$this->cache_data[$loincCode] = [
			'timestamp' => time(),
			'shortname' => $shortname
		];
		file_put_contents($this->cache_file, json_encode($this->cache_data));
	}

	function can_request($loincCode)
	{
		$cache_data = $this->getCachedata();
		//check in cache file if this code checked before and if it is within 24 hours, skip API call
		if (isset($cache_data[$loincCode]) && (time() - $cache_data[$loincCode]['timestamp'] < $this->cache_expiry)) {
			return false;
		}
		return true;
	}

	public function getShortName($loincCode)
	{
		if (!$this->can_request($loincCode)) {
			return '';
		}
		$res = $this->getLoincInfo($loincCode);
		$shortname = '';
		if (isset($res['Results'])) {
			foreach ($res['Results'] as $r) {
				if ($r['LOINC_NUM'] == $loincCode) {
					$shortname = $r['SHORTNAME'];
					break;
				}
			}
		}
		if (empty($shortname)) {
			$this->putCache($loincCode, '');
		}
		return $shortname;
	}

	public function getLoincInfo($loincCode)
	{
		$url = $this->apiBaseUrl . '?query=' . urlencode($loincCode);

		$response = $this->makeRequest($url);

		if ($response['status'] === 200) {
			return json_decode($response['body'], true);
		} else {
			return [
				'error' => 'Failed to fetch data',
				'status' => $response['status'],
				'message' => $response['body']
			];
		}
	}

	private function makeRequest($url)
	{
		$args = [
			'headers' => [
				'Authorization' => 'Basic ' . $this->apiKey,
				'Accept' => 'application/json'
			]
		];

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) {
			return [
				'status' => 500,
				'body' => $response->get_error_message()
			];
		}

		return [
			'status' => wp_remote_retrieve_response_code($response),
			'body' => wp_remote_retrieve_body($response)
		];
	}
}
