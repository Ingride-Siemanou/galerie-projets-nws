
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerie NWS</title>
    <link rel="stylesheet" href="style.css?v=12">
</head>
<body>

<header class="entete-nws">
    <img src="images/logo-nws.jpeg" alt="Logo Normandie Web School" class="logo-nws">
    <h1>Galerie des projets étudiants NWS</h1>
</header>

<p>Bienvenue dans notre application</p>

<form action="index.php" method="GET">
    <div class="filtres-nws">

        <input
            type="search"
            name="recherche"
            placeholder="Rechercher un projet..."
            value="<?= htmlspecialchars($_GET['recherche'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <!-- Menu des spécialités -->
        <div class="menu-specialite">
            <select name="specialite" id="specialite-native">
                <option value="">spécialités</option>

                <option value="développement web"
                    <?= ($_GET["specialite"] ?? "") === "développement web" ? "selected" : "" ?>>
                    Développement web
                </option>

                <option value="design"
                    <?= ($_GET["specialite"] ?? "") === "design" ? "selected" : "" ?>>
                    Design
                </option>

                <option value="communication"
                    <?= ($_GET["specialite"] ?? "") === "communication" ? "selected" : "" ?>>
                    Communication
                </option>
            </select>
        </div>

        <!-- Menu des années -->
        <div class="menu-annee">
            <select name="annee" id="annee-native">
                <option value="">Toutes les années</option>

                <option value="2026"
                    <?= ($_GET["annee"] ?? "") === "2026" ? "selected" : "" ?>>
                    2026
                </option>

                <option value="2025"
                    <?= ($_GET["annee"] ?? "") === "2025" ? "selected" : "" ?>>
                    2025
                </option>

                <option value="2024"
                    <?= ($_GET["annee"] ?? "") === "2024" ? "selected" : "" ?>>
                    2024
                </option>
            </select>
        </div>

        <button type="submit">Rechercher</button>

        <a href="index.php" class="bouton-reset">
            Effacer les filtres
        </a>

    </div>
</form>

<?php

require_once "connexion.php";

// Récupérer les filtres
$recherche = trim($_GET["recherche"] ?? "");
$specialite = trim($_GET["specialite"] ?? "");
$annee = trim($_GET["annee"] ?? "");

// Rechercher les projets dans MariaDB
$requete = $connexion->prepare(
    "SELECT * FROM projets
     WHERE titre LIKE :recherche
     AND (:specialite_vide = '' OR TRIM(specialite) = :specialite)
     AND (:annee_vide = '' OR annee = :annee)"
);

$requete->execute([
    "recherche" => "%" . $recherche . "%",
    "specialite_vide" => $specialite,
    "specialite" => $specialite,
    "annee_vide" => $annee,
    "annee" => $annee
]);

$projets = $requete->fetchAll(PDO::FETCH_ASSOC);

// Message si aucun projet n'est trouvé
if (empty($projets)) {
    echo '<p class="message-vide">Aucun projet trouvé pour cette recherche.</p>';
}

// Ouvrir la grille AVANT la boucle
echo '<div class="galerie-projets">';

// Afficher chaque projet
foreach ($projets as $projet) {

    echo '<div class="carte-projet">';

    echo "<h2>" . htmlspecialchars($projet["titre"]) . "</h2>";
    echo "<p>Étudiant(e) : " . htmlspecialchars($projet["etudiant"]) . "</p>";
    echo "<p>Année : " . htmlspecialchars($projet["annee"]) . "</p>";
    echo "<p>Promotion : " . htmlspecialchars($projet["promotion"]) . "e année</p>";
    echo "<p>Spécialité : " . htmlspecialchars($projet["specialite"]) . "</p>";
    echo "<p>Type de livrable : " . htmlspecialchars($projet["type_livrable"]) . "</p>";
    echo "<p>Description : " . htmlspecialchars($projet["description"]) . "</p>";

    echo '</div>';
}

// Fermer la grille APRÈS la boucle
echo '</div>';

?>

<script src="script.js?v=3" defer></script>
</body>
</html>
