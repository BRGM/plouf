<?php
header('Content-type: application/json');
// 2019-07-10 - Pour le fun, affichage teneur en glyphosate
function dist_km($long1, $lat1, $long2, $lat2) { // longitudes et latitudes en degrés : x1,y1, x2,y2
	// http://www.movable-type.co.uk/scripts/latlong.html
	// conversion en radians
	$x1 = $long1 * pi() / 180;
	$y1 = $lat1 * pi() / 180;
	$x2 = $long2 * pi() / 180;
	$y2 = $lat2 * pi() / 180;
	$deucl = 6367.445 * acos(sin($y1)*sin($y2) + (cos($y1)*cos($y2)*cos($x1-$x2)));
	return($deucl);
}	
function getIp() {
$ip = ($ip = getenv('HTTP_FORWARDED_FOR')) ? $ip :
($ip = getenv('HTTP_X_FORWARDED_FOR'))     ? $ip :
($ip = getenv('HTTP_X_COMING_FROM'))       ? $ip :
($ip = getenv('HTTP_VIA'))                 ? $ip :
($ip = getenv('HTTP_XROXY_CONNECTION'))    ? $ip :
($ip = getenv('HTTP_CLIENT_IP'))           ? $ip :
($ip = getenv('REMOTE_ADDR'))              ? $ip :
'0.0.0.0';
return $ip;
}
function ecritLog($s) {
	global $log;
	fwrite($log,date('Y-m-d H:i:s').' - '.$s.PHP_EOL);
}
function convertDateISO($s) { 
	// convertit une date ISO (2019-07-10) en date usuelle (10/07/2019)
	return(substr($s, 8, 2).'/'.substr($s, 5, 2).'/'.substr($s, 0, 4));
}

$log = fopen('log/cancer.log','at');
ecritLog('************ DEBUT DE SESSION ************');
$browser = $_SERVER['HTTP_USER_AGENT'];
$referrer = $_SERVER['HTTP_REFERER'];
if ($referrer == "") {  $referrer = "Page accédée directement";  }
ecritLog('IP = '.getIp());
ecritLog('Navigateur = '.$browser);
ecritLog('Referrer = '.$referrer);
$adr_texte = '(adresse inconnue)';
if (isset($_GET["adresse"])) { 
	$adr_texte = htmlspecialchars($_GET["adresse"]);
	ecritLog('Adresse saisie = '.$adr_texte);
	$adresse = utf8_decode($adr_texte);
	$adresse = str_replace(' ','+',$adresse);
	$url = "https://api-adresse.data.gouv.fr/search/?q=".$adresse."&limit=1";
	$raw = file_get_contents($url);
	$json = json_decode($raw,true);
	$longlat = $json['features'][0]['geometry']['coordinates']; // longitude en [0], latitude en [1]
	$long = $longlat[0];
	$lat = $longlat[1];
	if (isset($json['features'][0]['properties']['label'])) { $adr_texte = $json['features'][0]['properties']['label']; ecritLog('Adresse récupérée = '.$adr_texte); } // on récupère l'adresse telle que l'écrit l'API
}
if (isset($_GET["coord"])) { // les coordonnées sont prioritaires sur l'adresse
	$coord = $_GET["coord"];
	$longlat = explode(",", $coord);
	$long = $longlat[0];
	$lat = $longlat[1];
	$url = "https://api-adresse.data.gouv.fr/reverse/?lon=$long&lat=$lat";
	$raw = file_get_contents($url);
	$json = json_decode($raw,true);
	if (isset($json['features'][0]['properties']['label'])) { $adr_texte = $json['features'][0]['properties']['label']; }
	ecritLog('Coordonnées cliquées = '.$coord);
	ecritLog('Adresse correspondante = '.$adr_texte);
}

	$url1506 = "http://hubeau.eaufrance.fr/api/v1/qualite_nappes/analyses?bbox=".($long-0.1).",".($lat-0.1).",".($long+0.1).",".($lat+0.1).
		"&code_param=1506&code_remarque_analyse=1,3,7&fields=bss_id,longitude,latitude,resultat,code_remarque_analyse,nom_unite,date_debut_prelevement&size=1000";
	$raw1506 = file_get_contents($url1506);
	$json1506 = json_decode($raw1506,true);
	$c1506 = $json1506['count'];
	if ($c1506 == 0) { 
		if (isset($long)) {
			echo '{"coordonnees": ['.$long.', '.$lat.'], ';
		} else {
			echo '{"coordonnees": null, ';
		}	
		echo '"adresse": "'.$adr_texte.'", ';
		echo '"cancer": "Non", "info": "Pas de glyphosate détecté dans les environs", "piezo": null}';
		ecritLog("Pas de glyphosate détécté dans les environs");
	} else {
		$tenmax = 0;
		for ($i=0 ; $i < $c1506; $i++) {
			if ($json1506['data'][$i]['resultat'] > $tenmax) {
				$tenmax = $json1506['data'][$i]['resultat'];
				$datmax = $json1506['data'][$i]['date_debut_prelevement'];
				$unimax = $json1506['data'][$i]['nom_unite'];
				$bss_id = $json1506['data'][$i]['bss_id'];
				$long_proche = $json1506['data'][$i]['longitude'];
				$lat_proche = $json1506['data'][$i]['latitude'];
				$dist = dist_km($long, $lat, $long_proche, $lat_proche); 
			}	
		}	
		echo '{"coordonnees": ['.$long.', '.$lat.'], ';
		echo '"adresse": "'.$adr_texte.'", ';
		echo '"cancer": "Oui", "info": "Présence de Glyphosate à la teneur de '.number_format($tenmax,3).' '.$unimax.' le '.convertDateISO($datmax).' à '.number_format($dist,1).' km", ';
		echo '"piezo": {"code_bss": "'.$bss_id.'", "teneur_max": '.number_format($tenmax,3).', "unite": "'.$unimax.'", "distance": '.number_format($dist,1).', "date_analyse": "'.
			convertDateISO($datmax).'", "coordonnees": ['.$long_proche.', '.$lat_proche.']}}';
		ecritLog('Présence de Glyphosate à la teneur de '.number_format($tenmax,3).' '.$unimax.' le '.convertDateISO($datmax).' à '.number_format($dist,1).' km.');
	}	
	//echo '<div id="cancer"><p>&nbsp;</p><p><strong>'.$stooltip.'Découvrez vite si vous allez contracter<br>un cancer en buvant l\'eau de votre puits !</a></strong></p></div>';

ecritLog('************ FIN DE SESSION ************');
fclose($log);	
?>