<?php
header('Content-type: application/json');
// *** renvoyer les infos en JSON pour qu'elles puissent être parsées en Ajax en Javascript ***
// *** création 2019-07-13
// *** en entrée : coordonnées ou adresse (si les 2, coordonnées sont prioritaires)
// *** en sortie : "coordonnees": [latitude, longitude],		($longlat)
//				   "adresse": text, 							($adr_text)
//				   "piezo": {"profondeur": integer, "code_bss": text, "distance": float, "date_mesure": text, "coordonnees": [latitude, longitude] }
	   

	// *** HUB'EAU rencontre 4 problèmes :
	// 1. pas de possibilité de recherche par distance
	// 2. des stations renvoyées par la méthode stations n'ont en fait aucune mesure piézo (663 au 02/07/2019)
	// 3. la mesure est en cote NGF et non en profondeur
	// 4. l'information d'altitude pour convertir les cotes en profondeur n'est pas fournie (aller la chercher sur infoterre)
	// ***
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

$log = fopen('log/plouf.log','at');
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

$cc = 0;
$d = array(0.05, 0.1, 0.25, 0.5, 1);
$i = 0;
while (($cc == 0) and ($i <= 4)) {
	$url = "http://hubeau.eaufrance.fr/api/v1/niveaux_nappes/stations?bbox=".($long-$d[$i]).",".($lat-$d[$i]).",".($long+$d[$i]).",".($lat+$d[$i])."&size=100&format=json";
	$raw = file_get_contents($url);
	$json = json_decode($raw,true);
	$c = $json['count']; 
	$cc = 0;
	for ($ii=0; $ii<$c; $ii++) { // calcul du nb de stations qui ont date_debut_mesure not null
		if (!is_null($json['data'][$ii]['date_debut_mesure'])) { $cc++; }
	}
	//echo $i.' --  '.$d[$i].'  --  '.$c.' stations trouvées dont '.$cc.' ont des mesures<br>';
	$i++;
}	
if ($i < 5) { // on a trouvé au moins une station
	$cmin = 0;
	if ($c > 1) { // trouver la station la plus proche
		$distmin = 9999.9;
		for ($ii=0; $ii<$c; $ii++) {
			// test si la station contient plus de 0 mesures : on teste si date_debut_mesure est null. il faudrait tester aussi niveau_nappe_eau qui peut être null
			$debmes = $json['data'][$ii]['date_debut_mesure'];
			if (isset($debmes)) {
				$code = $json['data'][$ii]['code_bss'];
				$x = $json['data'][$ii]['x'];
				$y = $json['data'][$ii]['y'];
				$dist = dist_km($long, $lat, $x, $y); 
				//echo $ii.' -- dist = '.$dist.' -- count = '.$jsoncode['count'].'<br>';
				if ($dist < $distmin) {
					$distmin = $dist;
					$cmin = $ii; // index de la station à la distance minimale
				}
			}	
		}
		//echo 'distmin = '.$distmin.'  --  cmin = '.$cmin.'<br>';	
	}	
	$long_proche = $json['data'][$cmin]['x'];
	$lat_proche = $json['data'][$cmin]['y'];
	$distmin = dist_km($long, $lat, $long_proche, $lat_proche); 
	$icon_proche = 'images/iconPiezo.svg';
	$code_bss = $json['data'][$cmin]['code_bss'];
	
		// altitude
		$serr ='';
		$page_ades = @file_get_contents('http://ficheinfoterre.brgm.fr/InfoterreFiche/ficheBss.action?id='.$code_bss);
		if ($page_ades === false) { // try and catch ne fonctionnent pas avec file_get_contents
			$serr = 'Fiche Infoterre inaccessible - '; 
			$z = 0;
		} else {
			$ipos1 = strpos($page_ades, 'Altitude');
			$ipos1 = strpos($page_ades, '<span>', $ipos1);
			$ipos2 = strpos($page_ades, 'm', $ipos1);
			$salti = trim(substr($page_ades, $ipos1+6, $ipos2-$ipos1-6));
			$z = floatval($salti);
			//echo '<p>ipos = '.$ipos1.'  -  '.$ipos2.'  -  salti='.$salti.' - z='.$z.'</p>';
		}	
	
	$url = "http://hubeau.eaufrance.fr/api/v1/niveaux_nappes/chroniques?code_bss=".$code_bss."&size=1&sort=desc";
	$raw = file_get_contents($url);
	$json = json_decode($raw,true);
	$jsondata = $json['data'][0];
	$ngf = $jsondata['niveau_nappe_eau'];
	if (isset($ngf)) { 
		$prof = $z - $ngf;
		if ($prof < 1) { 
			$prof = 1; // on écrit 1 m
			ecritLog('Profondeur < 1 mètre'); 
		}
		else {
			ecritLog('Profondeur = '.number_format($prof, 0).' mètres'); 
		}
		$bOk = true;
	} else { // ne devrait plus se produire : si car certains $jsondata['niveau_nappe_eau'] sont à null
		$prof = 0; //null;
		$ngf = 0; //null;
		ecritLog('ERREUR'); 
		$bOk = false;
		if ($code_bss == '10396X0161/F4') { //rustines pour piezos déconnants connus. valeurs trouvées dans les graphiques Ades
			$prof = 1.72;
			$ngf = 4.28;
		}	
	}	

	ecritLog('Piézomètre le plus proche = '.$code_bss);
	ecritLog('distance = '.number_format($distmin,1).' km');
	ecritLog('date de la mesure : '.$jsondata['date_mesure']);
	ecritLog('altitude au sol = '.$z.' m NGF');
	ecritLog('altitude de la nappe = '.$ngf.' m NGF');
/*
	// 2019-07-10 - Pour le fun, affichage teneur en glyphosate
	$url1506 = "http://hubeau.eaufrance.fr/api/v1/qualite_nappes/analyses?bbox=".($long-0.1).",".($lat-0.1).",".($long+0.1).",".($lat+0.1).
		"&code_param=1506&code_remarque_analyse=1,3,7&fields=longitude,latitude,resultat,code_remarque_analyse,nom_unite,date_debut_prelevement&size=1000";
	$raw1506 = file_get_contents($url1506);
	$json1506 = json_decode($raw1506,true);
	$c1506 = $json1506['count'];
	if ($c1506 == 0) { 
		$stooltip = '<a href="#" onMouseover="ddrivetip(\'Non ! Pas de glyphosate détécté dans les environs\',\'lightgreen\', 300)" onMouseout="hideddrivetip()">';
	} else {
		$tenmax = 0;
		for ($i=0 ; $i < $c1506; $i++) {
			if ($json1506['data'][$i]['resultat'] > $tenmax) {
				$tenmax = $json1506['data'][$i]['resultat'];
				$datmax = $json1506['data'][$i]['date_debut_prelevement'];
				$unimax = $json1506['data'][$i]['nom_unite'];
			}	
		}	
		$stooltip = '<a href="#" onMouseover="ddrivetip(\'Oui ! Teneur en Glyphosate de '.number_format($tenmax,3).' '.$unimax.' le '.convertDateISO($datmax).'\',\'red\', 300)" onMouseout="hideddrivetip()">';
	}	
	echo '<div id="cancer"><p>&nbsp;</p><p><strong>'.$stooltip.'Découvrez vite si vous allez contracter<br>un cancer en buvant l\'eau de votre puits !</a></strong></p></div>';
*/
	
	echo '{"coordonnees": ['.$long.', '.$lat.'], ';
	echo '"adresse": "'.$adr_texte.'", ';
	echo '"piezo": {"code_bss": "'.$code_bss.'", "altitude_sol_ngf": '.$z.', "altitude_nappe_ngf": '.$ngf.', "profondeur": '.number_format($prof, 0).', "distance": '.number_format($distmin,1).', "date_mesure": "'.
		convertDateISO($jsondata['date_mesure']).'", "coordonnees": ['.$long_proche.', '.$lat_proche.']}}';
	
} else { // point trop éloigné de tout piézo, a priori hors France
	if (isset($long)) {
		echo '{"coordonnees": ['.$long.', '.$lat.'], ';
	} else {
		echo '{"coordonnees": null, ';
	}	
	echo '"adresse": "'.$adr_texte.'", ';
	echo '"piezo": null}';
	
	ecritLog("Calcul de la profondeur de l'eau impossible : point trop éloigné de tout piézomètre");
}	
ecritLog('************ FIN DE SESSION ************');
fclose($log);	
?>