<?php

// Modèle de configuration.
// L'installation (adresse /installation du site) crée config/config.php
// automatiquement. Vous pouvez aussi copier ce fichier sous le nom
// config.php et le remplir à la main.

return [
    'db' => [
        // Sur iFastNet, ces valeurs se trouvent dans cPanel > MySQL Databases.
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'nom_de_la_base',
        'user' => 'utilisateur',
        'pass' => 'mot_de_passe',
    ],

    // Adresse publique complète du site, utilisée dans les QR codes et les PDF.
    // Laissez vide pour la détecter automatiquement.
    'url_base' => '',

    // Taille maximale d'un fichier déposé, en mégaoctets.
    'televersement_max_mo' => 50,

    // Affiche le détail des erreurs. À laisser sur false en production.
    'debug' => false,
];
