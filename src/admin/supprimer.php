<?php
session_start();

if (empty($_SESSION["admin_connecte"])) {
    http_response_code(403);
    exit("Accès interdit : connexion administrateur requise.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Méthode non autorisée.");
}

require_once __DIR__ . "/../connexion.php";

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Identifiant du projet invalide.");
}


// Supprimer le projet sélectionné
$requete = $connexion->prepare(
    "DELETE FROM projets WHERE id = :id"
);

$requete->execute([
    "id" => $id
]);

// Revenir à la liste des projets
header("Location: gerer.php?supprime=1");
exit;

