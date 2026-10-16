<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/connexion.php";


function e($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

// Récupérer les filtres
$recherche = trim($_GET["recherche"] ?? "");
$specialite = trim($_GET["specialite"] ?? "");
$annee = trim($_GET["annee"] ?? "");
$promotion = trim($_GET["promotion"] ?? "");
$typeLivrable = trim($_GET["type_livrable"] ?? "");
$tri = trim($_GET["tri"] ?? "recent");
$ajax = ($_GET["ajax"] ?? "") === "1";

// Définir le tri autorisé
switch ($tri) {
    case "titre":
        $ordre = "titre ASC, id DESC";
        break;
    case "ancien":
        $ordre = "annee ASC, id ASC";
        break;
    default:
        $ordre = "annee DESC, id DESC";
        $tri = "recent";
        break;
}

$sansFiltres = (
    $recherche === "" &&
    $specialite === "" &&
    $annee === "" &&
    $promotion === "" &&
    $typeLivrable === ""
);

// Récupérer les projets à la une
$projetsEnAvant = [];

if ($sansFiltres) {
    $requeteEnAvant = $connexion->query(
        "SELECT *
         FROM projets
         WHERE statut = 'publie'
           AND mis_en_avant = 1
         ORDER BY annee DESC, id DESC
         LIMIT 6"
    );

    $projetsEnAvant = $requeteEnAvant->fetchAll(PDO::FETCH_ASSOC);
}

// Construire la recherche avec des paramètres sécurisés
$sql = "SELECT *
        FROM projets
        WHERE statut = 'publie'";

$parametres = [];

if ($recherche !== "") {
    $sql .= " AND (
        titre LIKE :recherche_titre
        OR description LIKE :recherche_description
        OR etudiant LIKE :recherche_etudiant
        OR type_livrable LIKE :recherche_type
        OR specialite LIKE :recherche_specialite
        OR outils_technologies LIKE :recherche_outils
    )";

    $motRecherche = "%" . $recherche . "%";

    $parametres["recherche_titre"] = $motRecherche;
    $parametres["recherche_description"] = $motRecherche;
    $parametres["recherche_etudiant"] = $motRecherche;
    $parametres["recherche_type"] = $motRecherche;
    $parametres["recherche_specialite"] = $motRecherche;
    $parametres["recherche_outils"] = $motRecherche;
}

if ($specialite !== "") {
    $sql .= " AND TRIM(specialite) = :specialite";
    $parametres["specialite"] = $specialite;
}

if ($annee !== "") {
    $sql .= " AND annee = :annee";
    $parametres["annee"] = $annee;
}

if ($promotion !== "") {
    $sql .= " AND promotion = :promotion";
    $parametres["promotion"] = $promotion;
}

if ($typeLivrable !== "") {
    $sql .= " AND TRIM(type_livrable) = :type_livrable";
    $parametres["type_livrable"] = $typeLivrable;
}

$sql .= " ORDER BY " . $ordre;

$requete = $connexion->prepare($sql);
$requete->execute($parametres);
$projets = $requete->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories des projets affichés
$tousLesProjets = array_merge($projetsEnAvant, $projets);
$categoriesParProjet = [];

if (!empty($tousLesProjets)) {
    $idsProjets = array_values(array_unique(array_map(
        "intval",
        array_column($tousLesProjets, "id")
    )));

    $placeholders = implode(",", array_fill(0, count($idsProjets), "?"));

    $sqlCategories = "
        SELECT pc.projet_id, c.nom
        FROM projet_categories pc
        INNER JOIN categories c ON c.id = pc.categorie_id
        WHERE pc.projet_id IN ($placeholders)
        ORDER BY c.nom ASC
    ";

    $requeteCategories = $connexion->prepare($sqlCategories);
    $requeteCategories->execute($idsProjets);

    foreach ($requeteCategories->fetchAll(PDO::FETCH_ASSOC) as $categorie) {
        $idProjet = (int) $categorie["projet_id"];
        $categoriesParProjet[$idProjet][] = $categorie["nom"];
    }
}

// Types de livrables disponibles
$requeteTypes = $connexion->query(
    "SELECT DISTINCT type_livrable
     FROM projets
     WHERE statut = 'publie'
       AND type_livrable IS NOT NULL
       AND TRIM(type_livrable) <> ''
     ORDER BY type_livrable ASC"
);

$typesLivrables = $requeteTypes->fetchAll(PDO::FETCH_COLUMN);

// Années disponibles
$anneesDisponibles = range(2000, 2100);

/**
 * Afficher une carte de projet.
 */
function afficherCarteProjet(
    array $projet,
    array $categoriesParProjet
): void {
    $idProjet = (int) $projet["id"];
    $urlDetail = "projet.php?id=" . $idProjet;
    $image = null;

    if (!empty($projet["image_couverture"])) {
        $nomImage = basename($projet["image_couverture"]);
        $cheminImage = "images/projets/" . $nomImage;

        if (is_file(__DIR__ . "/" . $cheminImage)) {
            $image = $cheminImage;
        }
    }

    $categoriesProjet = $categoriesParProjet[$idProjet] ?? [];
    ?>
    <article class="carte-projet">
        <a
            class="lien-carte-projet"
            href="<?= e($urlDetail) ?>"
            aria-label="Découvrir le projet <?= e($projet["titre"]) ?>"
        >
            <?php if ($image !== null): ?>
                <img
                    src="<?= e($image) ?>"
                    alt="Couverture du projet <?= e($projet["titre"]) ?>"
                    class="image-projet"
                    loading="lazy"
                >
            <?php endif; ?>

            <h2><?= e($projet["titre"]) ?></h2>
        </a>

        <div class="categories-projet">
            <?php if (!empty($categoriesProjet)): ?>
                <?php foreach ($categoriesProjet as $categorie): ?>
                    <span class="categorie-badge">
                        <?= e($categorie) ?>
                    </span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="aucune-categorie">
                    Aucune catégorie renseignée
                </span>
            <?php endif; ?>
        </div>

        <p>Étudiant(e) : <?= e($projet["etudiant"]) ?></p>
        <p>Année : <?= e($projet["annee"]) ?></p>
        <p>Promotion : <?= e($projet["promotion"]) ?>e année</p>
        <p>Spécialité : <?= e($projet["specialite"]) ?></p>
        <p>Type de livrable : <?= e($projet["type_livrable"]) ?></p>
        <p>Description : <?= e($projet["description"]) ?></p>

        <a class="lien-detail-projet" href="<?= e($urlDetail) ?>">
            Voir le projet →
        </a>
    </article>
    <?php
}

// Construire le HTML des résultats
ob_start();
?>
<h2>Tous les projets</h2>

<p class="nombre-projets">
    <?= count($projets) ?>
    <?= count($projets) > 1 ? "projets trouvés" : "projet trouvé" ?>
</p>

<?php if (empty($projets)): ?>
    <p class="message-vide">
        Aucun projet trouvé pour ces critères.
        Essaie de modifier ta recherche ou d'effacer les filtres.
    </p>
<?php else: ?>
    <div class="galerie-projets">
        <?php foreach ($projets as $projet): ?>
            <?php afficherCarteProjet($projet, $categoriesParProjet); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$htmlResultats = ob_get_clean();

// Construire le HTML des projets à la une
ob_start();

if ($sansFiltres && !empty($projetsEnAvant)):
    ?>
    <h2>⭐ Projets à la une</h2>

    <p class="projets-a-la-une-intro">
        Découvrez une sélection de projets mis en avant par l'équipe NWS.
    </p>

    <div class="galerie-projets">
        <?php foreach ($projetsEnAvant as $projet): ?>
            <div>
                <span class="badge-une">⭐ Projet à la une</span>
                <?php afficherCarteProjet($projet, $categoriesParProjet); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
endif;

$htmlUne = ob_get_clean();

// Réponse AJAX : les deux zones HTML attendues par script.js
if ($ajax) {
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "featuredHtml" => $htmlUne,
        "resultsHtml" => $htmlResultats
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Galerie des projets étudiants NWS</title>

    <link rel="stylesheet" href="style.css?v=19">

    <style>
        .categories-projet {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 12px 0;
        }

        .categorie-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background-color: #e0f5f3;
            color: #007b78;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .aucune-categorie {
            color: #777;
            font-size: 0.9rem;
            font-style: italic;
        }

        .projets-a-la-une {
            margin: 35px 0 45px;
            padding: 25px;
            background: #fff8e8;
            border-radius: 14px;
        }

        .projets-a-la-une:empty {
            display: none;
        }

        .projets-a-la-une h2 {
            margin-top: 0;
            color: #e94a34;
        }

        .projets-a-la-une-intro {
            margin-bottom: 20px;
        }

        .badge-une {
            display: inline-block;
            margin: 0 0 10px;
            padding: 6px 10px;
            border-radius: 5px;
            background: #fec800;
            color: #333;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
</head>

<body>

<header class="entete-nws">
    <img
        src="images/logo-nws.jpeg"
        alt="Logo Normandie Web School"
        class="logo-nws"
    >

    <h1>Galerie des projets étudiants NWS</h1>
</header>

<?php if (
    !empty($_SESSION["utilisateur_id"]) &&
    ($_SESSION["utilisateur_role"] ?? "") === "admin"
): ?>
    <?php if (
    !empty($_SESSION["utilisateur_id"]) &&
    ($_SESSION["utilisateur_role"] ?? "") === "admin"
): ?>
<?php endif; ?>
<?php endif; ?>


<p class="message-bienvenue">
    Bienvenue dans la galerie des réalisations étudiantes
    de Normandie Web School.
</p>

<div class="bouton-proposer-haut">
    <a href="proposer-projet.php">+ Proposer un projet</a>
</div>


<section class="projets-a-la-une" id="zone-une">
    <?= $htmlUne ?>
</section>

<!-- Recherche, filtres et tri -->
<form action="index.php" method="get" id="form-filtres-projets">
    <div class="filtres-nws">

        <input
            type="search"
            name="recherche"
            placeholder="Rechercher un projet, un outil..."
            aria-label="Rechercher un projet"
            value="<?= e($recherche) ?>"
        >

        <select name="specialite" aria-label="Filtrer par spécialité">
            <option value=""> spécialités</option>

            <option value="développement web"
                <?= $specialite === "développement web" ? "selected" : "" ?>>
                Développement web
            </option>

            <option value="communication graphique"
                <?= $specialite === "communication graphique" ? "selected" : "" ?>>
                Communication graphique
            </option>

            <option value="communication digital"
                <?= $specialite === "communication digital" ? "selected" : "" ?>>
                Communication digital
            </option>

            <option value="Marketing digital"
                <?= $specialite === "Marketing digital" ? "selected" : "" ?>>
                Marketing digital
            </option>
        </select>

        <select name="type_livrable" aria-label="Filtrer par type de livrable">
            <option value=""> livrables</option>

            <?php foreach ($typesLivrables as $type): ?>
                <option
                    value="<?= e($type) ?>"
                    <?= $typeLivrable === $type ? "selected" : "" ?>
                >
                    <?= e($type) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="promotion" aria-label="Filtrer par promotion">
            <option value=""> promotions</option>

            <?php for ($numero = 1; $numero <= 5; $numero++): ?>
                <option
                    value="<?= $numero ?>"
                    <?= $promotion === (string) $numero ? "selected" : "" ?>
                >
                    <?= $numero ?>e année
                </option>
            <?php endfor; ?>
        </select>

        <select name="annee" aria-label="Filtrer par année de réalisation">
            <option value=""> années</option>

            <?php foreach ($anneesDisponibles as $anneeOption): ?>
                <option
                    value="<?= e($anneeOption) ?>"
                    <?= $annee === (string) $anneeOption ? "selected" : "" ?>
                >
                    <?= e($anneeOption) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="tri" aria-label="Trier les projets">
            <option value="recent" <?= $tri === "recent" ? "selected" : "" ?>>
                Plus récents
            </option>
            <option value="ancien" <?= $tri === "ancien" ? "selected" : "" ?>>
                Plus anciens
            </option>
            <option value="titre" <?= $tri === "titre" ? "selected" : "" ?>>
                Ordre alphabétique
            </option>
        </select>

        <button type="submit">Rechercher</button>

        <a href="index.php" class="bouton-reset">Effacer les filtres</a>
    </div>
</form>

<main class="galerie-contenu" id="zone-resultats-projets">
    <?= $htmlResultats ?>
</main>

<footer class="footer-nws">
    <div class="footer-nws-contenu">

        <div class="footer-nws-identite">
            <img
                src="images/logo-nws.jpeg"
                alt="Logo Normandie Web School"
            >

            <div>
                <h2>Normandie Web School</h2>
            </div>
        </div>

        <nav class="footer-nws-liens" aria-label="Navigation de pied de page">
            <a href="index.php">Accueil</a>
            <a href="#zone-resultats-projets">Les projets</a>
            <a href="proposer-projet.php">Proposer un projet</a>
        </nav>

    </div>

    <div class="footer-nws-bas">
        <p>
            © <?= date("Y") ?> Normandie Web School
            — Galerie des projets étudiants
        </p>
    </div>
</footer>


</body>
</html>
