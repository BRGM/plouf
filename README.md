# Plouf

Démonstrateur pour "un puits dans mon jardin"

Calcul de la profondeur simplifié : on affiche la profondeur au piézomètre le plus proche, sans notion de nappe captée.

Etapes :
1. Saisie utilisateur (adresse ou point sur la carte)  
1a. si adresse : utilisation API Etalab pour récupérer les coordonnées de l'adresse qui matche le mieux  
1b. si click sur la carte : utilisation API Etalab inverse pour récupérer l'adresse  
2. Appel d'un Web Service créé pour l'occasion qui renvoie les infos du piézomètre le plus proche :  
2a. Recherche de la station la plus proche dans Hub'Eau par bbox successives de 0.05, 0.1, 0.25, 0.5, 1 degrés (recherche par rayon non possible)  
&nbsp;&nbsp;&nbsp;2a1. test si la station la plus proche contient bien des données (bug Hub'Eau qui référence des stations sans données)  
&nbsp;&nbsp;&nbsp;2a2. récupération dans InfoTerre de l'altitude au sol de la station (information indisponible dans Hub'Eau) : http://ficheinfoterre.brgm.fr/InfoterreFiche/ficheBss.action?id=<code_bss>  
2b. Récupération dans Hub'Eau de la dernière mesure (niveau d'eau en cote NGF, profondeur indisponible)  
2c. Calcul de la profondeur au mètre près = round(altitude station - dernier niveau NGF)  
3. Ecriture des infos : adresse, profondeur, piézo le plus proche, distance, date mesure
4. Cartographie point de recherche et piézo le plus proche