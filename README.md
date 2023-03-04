# tiko_heating_api

Ce composant permet de gérer les radiateurs traditionnels connectés via la solution TIKO depuis le serveur Home Assistant. Il nécessite l'hébergement d'une page web qui servira d'endpoint pour communiquer avec l'API de TIKO.

Ce programme a deux fonctions principales :
a) Il aide à configurer le package Tiko dans votre Home Assistant en générant un fichier tiko.yaml et les cartes associées.
b) Il sert d'endpoint entre votre Home Assistant et l'API de Tiko (pour mettre à jour les capteurs, envoyer des commandes, etc.)

Ce programme doit être hébergé à cet effet.

Après la première utilisation, le script créera un fichier tiko.env dans le même répertoire avec les informations d'identification et l'URL de l'endpoint.

La page de configuration peut être accédée comme ceci :
http://www.votredomaine.com/tiko.php?install=true&hash=ENDPOINT_TOKEN (remplacez ENDPOINT_TOKEN par la valeur trouvée dans tiko.env)

##================================================================================================================================

Component allowing to manage traditional radiators connected via the TIKO solution from within Home Assistant server. It requires hosting a web page that will serve as an endpoint to communicate with TIKO through their API.

This program has two main functions:
a) It'll help to setup Tiko package in your home assistant, by generating tiko.yaml file + related cards
b) Serve as endpoint between your Home Assistant and Tiko's API (to update sensors, send commands, etc.)

This program need to be hosted for this purpose.

After first use the script will create a tiko.env file in the same directory with credentials & endpoint URL.

The setup page can be access like this :
http://www.yourdomain.com/tiko.php?install=true&hash=ENDPOINT_TOKEN (replace ENDPOINT_TOKEN with value found in tiko.env)

##================================================================================================================================

Release date : 2023-03-04


