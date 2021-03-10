<?php 

class GeomarWeatherData  implements JsonSerializable {
	const GMW_INSTITUT_WINDDIRECTION = 0;
	const GMW_INSTITUT_WINDSPEED = 1;
	const GMW_INSTITUT_TEMPERATURE_AIR = 2;
	const GMW_INSTITUT_TEMPERATURE_WATER = 3;
	const GMW_INSTITUT_HUMIDITY = 4;
	const GMW_INSTITUT_PRESSURE = 6;	
	//
	const GMW_LIGHTHOUSE_WINDDIRECTION = 10;
	const GMW_LIGHTHOUSE_WINDSPEED = 11;
	const GMW_LIGHTHOUSE_TEMP_AIR = 12;
	const GMW_LIGHTHOUSE_TEMP_WATER = 13;
	const GMW_LIGHTHOUSE_HUMIDITY = 14;
	const GMW_LIGHTHOUSE_WINDSPEED_MAX = 15;
	
	const MAP_CONVERSION_BFT = array(
		0 => 0.51, 		// Flaute
		1 => 2,06,		// leiser Zug
		2 => 3,60,		// leichte Brise
		3 => 5,66,		// schwache Brise
		4 => 8,23,		// mäßige Brise
		5 => 11.32,		// frische Brise, frischer Wind
		6 => 14.40,		// starker Wind
		7 => 17.49,		// steifer Wind
		8 => 21.09,		// stürmischer Wind
		9 => 24.69,		//Sturm
		10 => 28.81, 	// schwerer Sturm
		11 => 32.92,	// orkanartiger Sturm
		12 => 999 		// Organ
	);
	const MAP_WIND_DIRECTION = array(   
		'N', 'NNO', 'NO', 'ONO',
		'O', 'OSO', 'SO,','SSO',
		'S', 'SSW', 'SW', 'WSW',
		'W', 'WNW', 'NW', 'NNW', 
		'N' // for 337.5-360
	);
	const MAP_WIND_DIRECTION_RES = 16; 
   	private $data = array();
	
	function __construct() {   
	}

	function getWindDirection($value) {
		if ( $value > 360) {
			return "-";
		}
		return self::MAP_WIND_DIRECTION[round($value/22.5,0)];
	}


	function load($url = "https://www.geomar.de/service/wetter") {
		$content = file_get_contents($url);
	
		
		$doc = new DOMDocument("1.0","utf-8");
		$doc->preserveWhiteSpace = true;
		$doc->validateOnParse = false;
		@$doc->loadHTML($content);

		$xp = new DOMXPath($doc);

		// date
		// $r = $xp->query("(//thead/tr[@class='weather-list__table-header']/th)[1]");
		// $dateRaw = $r->item(0)->nodeValue;
		// $d = DateTime::createFromFormat("d. M Y, H:i",$dateRaw); // 08. März 2021, 08:48
		// echo $d->format("d.m.Y");

		// data
		$index =0;;
		$result = $xp->query("(//td[@class='parameter'])");
		for($i = 0; $i < $result->length; $i++) {
			$item= $result->item($i);
		
			if (!($item instanceof DOMElement)) continue;
			if (empty(trim($item->nodeValue))) continue;
		
			$value = $this->clean_value($item->nodeValue);
		//	printf("%3d %s\n", $i, $value);

			switch($i) {
				case self::GMW_INSTITUT_WINDSPEED:
					$this->data["INSTITUT"]["wind_speed_ms"] = $value;
					$this->data["INSTITUT"]["wind_speed_kt"] = round($value * 1.9438444924574,0);
					break;
				case self::GMW_INSTITUT_WINDDIRECTION:
					$this->data["INSTITUT"]["wind_direction"] = $value;
					$this->data["INSTITUT"]["wind_direction_txt"] = $this->getWindDirection($value);
					break;
				case self::GMW_INSTITUT_TEMPERATURE_AIR:
					$this->data["INSTITUT"]["temperature_air"] = $value;
					break;
				case self::GMW_INSTITUT_TEMPERATURE_WATER:
					$this->data["INSTITUT"]["temperature_water"] = $value;
					break;
				case self::GMW_INSTITUT_HUMIDITY:
						$this->data["INSTITUT"]["humidity"] = $value;
						break;
				case self::GMW_INSTITUT_PRESSURE:
					$this->data["INSTITUT"]["pressure"] = $value;
					break;
			}

			switch($i) {
				case self::GMW_LIGHTHOUSE_TEMP_AIR:
					$this->data["LIGHTHOUSE"]["temperature_air"] = $value;
					break;
				case self::GMW_LIGHTHOUSE_TEMP_WATER:
					$this->data["LIGHTHOUSE"]["temperature_water"] = $value;
					break;
				case self::GMW_LIGHTHOUSE_HUMIDITY:
					$this->data["LIGHTHOUSE"]["humidity"] = $value;
					break;
				case self::GMW_LIGHTHOUSE_WINDDIRECTION:
						$value = 359;
					$this->data["LIGHTHOUSE"]["wind_direction"] = $value;
					$this->data["LIGHTHOUSE"]["wind_direction_txt"] = $this->getWindDirection($value);
					break;
				case self::GMW_LIGHTHOUSE_WINDSPEED_MAX:
					$this->data["LIGHTHOUSE"]["wind_speed_max"] = $value;
					$this->data["LIGHTHOUSE"]["wind_speed_max_kt"] = round($value * 1.9438444924574,0);
					break;
				case self::GMW_LIGHTHOUSE_WINDSPEED:
					$this->data["LIGHTHOUSE"]["wind_speed"] = $value;
					$this->data["LIGHTHOUSE"]["wind_speed_kt"] = round($value * 1.9438444924574,0);
					break;
				}
		}
	}

	function clean_value($value) {
		$value=trim($value);
		$matches = array();
		if ( ! preg_match("/^([\d\.\,]+)(.*) ([^ ]*)$/", $value, $matches)){
			return "(NA)";
		}

		$value = $matches[1];
		
		// convert to float
		$value = preg_replace("/\./","",$value);
		$value = preg_replace("/\,/",".",$value);

		return $value;
	}

	function getBFT($speed_ms) {
		foreach(self::MAP_CONVERSION_BFT as $bft => $max) {
			if ( $speed_ms >= $max) continue; 
			return $bft;
		}

		// everything above 32.92m/s
		return 12;
	}

	function jsonSerialize() {
		$export = array();

		$export["datetime_request"] = date("c");
		$export["comment"] = "Datenquelle: GEOMAR Helmholtz-Zentrum für Ozeanforschung Kiel (https://www.geomar.de/service/wetter)";
		$export["locations"] = array("Leuchtturm", "Institut");
		// 
		$export["Institut"] = $this->data["INSTITUT"];
		
		//
		$export["Leuchtturm"] = $this->data["LIGHTHOUSE"];
		$export["Leuchtturm"]["wind_speed_bft"] = $this->getBFT($this->data["LIGHTHOUSE"]["wind_speed"]);
		$export["Leuchtturm"]["wind_speed_max_bft"] = $this->getBFT($this->data["LIGHTHOUSE"]["wind_speed_max"]);

		return $export;
	} 
}

$url = "/Users/olli/wetter.html";
$d = new GeomarWeatherData();
$d->load();
Header("Content-type: text/plain");
echo json_encode($d, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>