// Exécute un appel AJAX GET
// Prend en paramètres l'URL cible et la fonction callback appelée en cas de succès
function ajaxGet(url, callback) {
    var req = new XMLHttpRequest();
    req.open("GET", url);
    req.addEventListener("load", function () {
        if (req.status >= 200 && req.status < 400) {
            // Appelle la fonction callback en lui passant la réponse de la requête
            callback(req.responseText);
        } else {
            console.error(req.status + " " + req.statusText + " " + url);
        }
    });
    req.addEventListener("error", function () {
        console.error("Erreur réseau avec l'URL " + url);
    });
    req.send(null);
}

		
	function getAdresse(lonlat) {
		ajaxGet("https://api-adresse.data.gouv.fr/reverse/?lon=" + lonlat[0] + "&lat=" + lonlat[1], function (reponse) {
			var adresse = JSON.parse(reponse);
			if (adresse.features[0]) {
				divadr.innerHTML = "<p>" + adresse.features[0].properties.label + "</p>";
				delayedAlert("100");
			}	else {
				divadr.innerHTML = "<p>(adresse inconnue)</p>";
			}	
		});
	}	
