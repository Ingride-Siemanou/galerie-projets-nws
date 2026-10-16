<?php
// Récupérer les paramètres transmis par Docker
$serveur = getenv("DB_HOST");
$baseDeDonnees = getenv("DB_NAME");
$utilisateur = getenv("DB_USER");
$motDePasse = getenv("DB_PASSWORD");

try {
    // Créer la connexion à MariaDB
    $connexion = new PDO(
        "mysql:host=$serveur;dbname=$baseDeDonnees;charset=utf8mb4",
        $utilisateur,
        $motDePasse
    );

    // Activer les messages d'erreur pour le développement
    $connexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $erreur) {
    error_log($erreur->getMessage());
    http_response_code(500);
    exit("Impossible de se connecter à la base de données.");
}
