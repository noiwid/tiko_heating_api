# tiko_heating_api

Ce composant permet de gérer les radiateurs traditionnels connectés via la solution TIKO depuis le serveur Home Assistant. Il nécessite l'hébergement d'une page web qui servira d'endpoint pour communiquer avec l'API de TIKO.

Ce programme a deux fonctions principales :
- Il aide à configurer le package Tiko dans votre Home Assistant en générant un fichier tiko.yaml et les cartes associées.
- Il sert d'endpoint entre votre Home Assistant et l'API de Tiko (pour mettre à jour les capteurs, envoyer des commandes, etc.)

Ce programme doit être hébergé et accessible en ligne pour que votre serveur assistant puisse y accéder.
Il utilise la librairie Spyc (https://github.com/mustangostang/spyc) qui permet de convertir des tableaux PHP en YAML, cette librairie (spyc.php) sera automatiquement téléchargée depuis github pendant l'installation, et copiée dans le même dossier que le fichier tiko.php.

Après la première utilisation, le script créera un fichier tiko.env dans le même répertoire avec les informations d'identification et l'URL de l'endpoint.
Vérifier que la variable ENDPOINT_URL contient bien l'url d'accès à votre script (ex : https://mon.i.p/tiko.php)

La page de configuration peut être accédée comme ceci :
http://www.votredomaine.com/tiko.php?install=true&hash=ENDPOINT_TOKEN (remplacez ENDPOINT_TOKEN par la valeur trouvée dans tiko.env)

Preview de l'integration dans lovelace :
https://community.jeedom.com/uploads/default/original/3X/f/2/f2b58b1243929012af284ff6c9c3778923484686.png

##==========================================================================


Component allowing to manage traditional radiators connected via the TIKO solution from within Home Assistant server. It requires hosting a web page that will serve as an endpoint to communicate with TIKO through their API.

This program has two main functions:
- It'll help to setup Tiko package in your home assistant, by generating tiko.yaml file + related cards
- Serve as endpoint between your Home Assistant and Tiko's API (to update sensors, send commands, etc.)

This program need to be hosted for this purpose.
It use Spyc library (https://github.com/mustangostang/spyc) that convert PHP array to YAML, this lib (spyc.php) will automaticly downloaded in the same folder as main script.

After first use the script will create a tiko.env file in the same directory with credentials & endpoint URL.
Check the ENDPOINT_URL variable, it should contain your script URL (ex : https://www.yourdomain.com/tiko.php)

The setup page can be access like this :
https://www.yourdomain.com/tiko.php?install=true&hash=ENDPOINT_TOKEN (replace ENDPOINT_TOKEN with value found in tiko.env)

Lovelace card's preview : 
https://community.jeedom.com/uploads/default/original/3X/f/2/f2b58b1243929012af284ff6c9c3778923484686.png


##==========================================================================

Release date : 2023-03-04


