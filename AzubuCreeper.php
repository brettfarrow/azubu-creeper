<?php

require 'vendor/autoload.php';

Dotenv::load(__DIR__);

const BASE_URL = "http://api.azubu.tv/public/channel/live/list/game/";

class AzubuCreeper {
	
	public $azubuGame;
	public $requestURL;
	public $streamInfo;	
	
	public function __construct($game) {
		// Game info needs dashes instead of spaces, and has to be all lowercase to return any results.
		$this->azubuGame = strtolower(str_replace(" ", "-", $game));
		$this->requestURL = BASE_URL . $this->azubuGame;
	}
	
	// The last request will contain another stream URL that when fetched, 
	// has zero entries. updateStreamDB() will check if that is the case
	// and will return 0 when it happens. So it makes one extra request by design.
	public function beginCreeping () {
		$streamInfo = $this->getStreamInfo($this->getRequestURL()); // get the ball rolling.
		$this->updateStreamDB($streamInfo);
	}
	
	public function getStreamInfo ($apiURL) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiURL);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$data = curl_exec($ch);

		curl_close($ch);

		$streamData = json_decode($data, TRUE);
	
		return $streamData;
	}
	
	public function updateStreamDB ($streamList) {
		$pdoData = "mysql:host=localhost;dbname=" . getenv('DB_NAME');

		try {
			$db = new PDO($pdoData, getenv('DB_USER'), getenv('DB_PASS'), array(PDO::ATTR_PERSISTENT => true));
		} catch (PDOException $e) {
			echo "Error!" . $e->getMessage() . "\r\n";
			die();
		}

		$query = "INSERT INTO " . getenv('DB_TABLE') . " (azubu_name, display_name, viewers, time_gmt) VALUES(?, ?, ?, ?)";
		
		$insertStatement = $db->prepare($query);

		$insertionDate = gmdate(DATE_ISO8601);

		foreach ($streamList["data"] as $stream) {
			$insertStatement->execute(
				array(
					$stream["user"]["username"], 
					$stream["user"]["display_name"], 
					$stream["view_count"], 
					$insertionDate
				)
			);
		}
	}

	public function getRequestURL () {
		return $this->requestURL;
	}
	
	public function setRequestURL ($target) {
		$this->requestURL = $target;
	}
}
