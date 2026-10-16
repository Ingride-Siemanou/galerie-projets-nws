<?php
session_start();

if (empty($_SESSION["admin_connecte"])) {
    http_response_code(403);
    exit("Accès interdit : connexion administrateur requise.");
}

require_once __DIR__ . "/../connexion.php";

function e($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Identifiant du projet invalide.");
}

// Charger le projet
$requete = $connexion->prepare(
    "SELECT * FROM projets WHERE id = :id"
);
$requete->execute(["id" => $id]);
$projet = $requete->fetch(PDO::FETCH_ASSOC);

if (!$projet) {
    http_response_code(404);
    exit("Projet introuvable.");
}

// Charger les catégories disponibles
$requeteCategories = $connexion->query(
    "SELECT id, nom FROM categories ORDER BY nom ASC"
);
$categoriesDisponibles = $requeteCategories->fetchAll(PDO::FETCH_ASSOC);

// Charger les catégories associées au projet
$requeteCategoriesProjet = $connexion->prepare(
    "SELECT categorie_id
     FROM projet_categories
     WHERE projet_id = :projet_id"
);
$requeteCategoriesProjet->execute(["projet_id" => $id]);

$categoriesSelectionnees = array_map(
    "intval",
    $requeteCategoriesProjet->fetchAll(PDO::FETCH_COLUMN)
);

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $champs = [
        "titre" => trim($_POST["titre"] ?? ""),
        "description" => trim($_POST["description"] ?? ""),
        "etudiant" => trim($_POST["etudiant"] ?? ""),
        "annee" => trim($_POST["annee"] ?? ""),
        "promotion" => trim($_POST["promotion"] ?? ""),
        "specialite" => trim($_POST["specialite"] ?? ""),
        "type_livrable" => trim($_POST["type_livrable"] ?? ""),
        "contexte" => trim($_POST["contexte"] ?? ""),
        "objectifs" => trim($_POST["objectifs"] ?? ""),
        "outils_technologies" => trim($_POST["outils_technologies"] ?? ""),
        "lien_projet" => trim($_POST["lien_projet"] ?? ""),
        "mis_en_avant" => isset($_POST["mis_en_avant"]) ? 1 : 0
    ];

    $projet = array_merge($projet, $champs);

    // Lire les catégories sélectionnées
    $categoriesPost = $_POST["categories"] ?? [];

    if (!is_array($categoriesPost)) {
        $categoriesPost = [];
    }

    $categoriesPost = array_unique(
        array_filter(
            array_map(
                static function ($valeur) {
                    if (!is_scalar($valeur)) {
                        return 0;
                    }

                    $valeur = (string) $valeur;

                    return ctype_digit($valeur) ? (int) $valeur : 0;
                },
                $categoriesPost
            ),
            static fn($valeur) => $valeur > 0
        )
    );

    $categoriesSelectionnees = array_values($categoriesPost);

    // Vérifier les champs obligatoires
    if (
        $champs["titre"] === "" ||
        $champs["description"] === "" ||
        $champs["etudiant"] === "" ||
        $champs["specialite"] === "" ||
        $champs["type_livrable"] === "" ||
        !ctype_digit($champs["annee"]) ||
        (int) $champs["annee"] < 2000 ||
        (int) $champs["annee"] > 2100 ||
        !ctype_digit($champs["promotion"]) ||
        (int) $champs["promotion"] < 1 ||
        (int) $champs["promotion"] > 5
    ) {
        $erreur = "Merci de remplir correctement tous les champs obligatoires.";
    }

    // Vérifier le lien du projet
    if ($erreur === "" && $champs["lien_projet"] !== "") {
        $url = $champs["lien_projet"];
        $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (
            !filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array($schema, ["http", "https"], true)
        ) {
            $erreur = "Le lien doit commencer par http:// ou https://.";
        }
    }

    // Vérifier que les catégories existent
    if ($erreur === "" && !empty($categoriesSelectionnees)) {
        $placeholders = implode(
            ",",
            array_fill(0, count($categoriesSelectionnees), "?")
        );

        $requeteVerification = $connexion->prepare(
            "SELECT id FROM categories WHERE id IN ($placeholders)"
        );
        $requeteVerification->execute($categoriesSelectionnees);

        $idsValides = array_map(
            "intval",
            $requeteVerification->fetchAll(PDO::FETCH_COLUMN)
        );

        if (count($idsValides) !== count($categoriesSelectionnees)) {
            $erreur = "Une ou plusieurs catégories sélectionnées sont invalides.";
        }
    }

    // Gestion de l'image de couverture
    $nomImage = $projet["image_couverture"] ?? null;
    $nouveauFichier = null;

    if (
        $erreur === "" &&
        isset($_FILES["image_couverture"]) &&
        $_FILES["image_couverture"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $fichier = $_FILES["image_couverture"];

        if ($fichier["error"] !== UPLOAD_ERR_OK) {
            $erreur = "Erreur lors de l'envoi de l'image.";
        } elseif ($fichier["size"] > 5 * 1024 * 1024) {
            $erreur = "L'image ne doit pas dépasser 5 Mo.";
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file(
                $fichier["tmp_name"]
            );

            $formats = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (!isset($formats[$mime])) {
                $erreur = "Format accepté : JPG, PNG ou WEBP.";
            } elseif (@getimagesize($fichier["tmp_name"]) === false) {
                $erreur = "Le fichier sélectionné n'est pas une image valide.";
            } else {
                $dossier = __DIR__ . "/../images/projets/";

                if (
                    !is_dir($dossier) &&
                    !mkdir($dossier, 0755, true) &&
                    !is_dir($dossier)
                ) {
                    $erreur = "Impossible de créer le dossier des images.";
                } elseif (!is_writable($dossier)) {
                    $erreur = "Le dossier des images n'est pas accessible en écriture.";
                } else {
                    $nomImage = bin2hex(random_bytes(16))
                        . "." . $formats[$mime];

                    $nouveauFichier = $dossier . $nomImage;

                    if (!move_uploaded_file(
                        $fichier["tmp_name"],
                        $nouveauFichier
                    )) {
                        $erreur = "Impossible d'enregistrer l'image.";
                        $nomImage = $projet["image_couverture"] ?? null;
                        $nouveauFichier = null;
                    }
                }
            }
        }
    }

    // Enregistrer le projet et ses catégories
    if ($erreur === "") {
        try {
            $connexion->beginTransaction();

            $requete = $connexion->prepare(
                "UPDATE projets SET
                    titre = :titre,
                    description = :description,
                    etudiant = :etudiant,
                    annee = :annee,
                    promotion = :promotion,
                    specialite = :specialite,
                    type_livrable = :type_livrable,
                    contexte = :contexte,
                    objectifs = :objectifs,
                    outils_technologies = :outils_technologies,
                    lien_projet = :lien_projet,
                    image_couverture = :image_couverture,
                    mis_en_avant = :mis_en_avant
                 WHERE id = :id"
            );

            $requete->execute([
                "titre" => $champs["titre"],
                "description" => $champs["description"],
                "etudiant" => $champs["etudiant"],
                "annee" => (int) $champs["annee"],
                "promotion" => (int) $champs["promotion"],
                "specialite" => $champs["specialite"],
                "type_livrable" => $champs["type_livrable"],
                "contexte" => $champs["contexte"],
                "objectifs" => $champs["objectifs"],
                "outils_technologies" => $champs["outils_technologies"],
                "lien_projet" => $champs["lien_projet"],
                "image_couverture" => $nomImage ?? "",
                "mis_en_avant" => $champs["mis_en_avant"],
                "id" => $id
            ]);

            // Remplacer les anciennes associations de catégories
            $requeteSuppression = $connexion->prepare(
                "DELETE FROM projet_categories WHERE projet_id = :id"
            );
            $requeteSuppression->execute(["id" => $id]);

            // Enregistrer les nouvelles associations
            if (!empty($categoriesSelectionnees)) {
                $requeteAssociation = $connexion->prepare(
                    "INSERT INTO projet_categories (projet_id, categorie_id)
                     VALUES (:projet_id, :categorie_id)"
                );

                foreach ($categoriesSelectionnees as $categorieId) {
                    $requeteAssociation->execute([
                        "projet_id" => $id,
                        "categorie_id" => $categorieId
                    ]);
                }
            }

            $connexion->commit();

            // Supprimer l'ancienne image après l'enregistrement réussi
            if (
                $nouveauFichier !== null &&
                !empty($projet["image_couverture"])
            ) {
                $ancienneImage = basename($projet["image_couverture"]);
                $ancienChemin = __DIR__
                    . "/../images/projets/" . $ancienneImage;

                if (is_file($ancienChemin)) {
                    unlink($ancienChemin);
                }
            }

            header("Location: gerer.php?modifie=1");
            exit;
        } catch (Throwable $exception) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            if (
                $nouveauFichier !== null &&
                is_file($nouveauFichier)
            ) {
                unlink($nouveauFichier);
            }

            error_log($exception->getMessage());
            $erreur = "Impossible d'enregistrer les modifications. Vérifie les informations et les catégories sélectionnées.";
        }
    }
}

// Spécialités disponibles

$specialites = [
    "développement web",
    "communication graphique",
    "communication digital",
    "Marketing digital"
];


$specialiteActuelle = trim($projet["specialite"] ?? "");

if (
    $specialiteActuelle !== "" &&
    !in_array($specialiteActuelle, $specialites, true)
) {
    $specialites[] = $specialiteActuelle;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un projet - Administration NWS</title>
    <link rel="stylesheet" href="admin.css?v=17">

    <style>
        .categories-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin: 10px 0 22px;
        }

        .categorie-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
        }

        .categorie-option input {
            width: auto;
            margin: 0;
            accent-color: #00a5a5;
        }

        .categories-aide {
            color: #666;
            margin: 5px 0 12px;
        }

        .mise-en-avant-option {
            margin: 18px 0 24px;
            padding: 15px;
            background: #fff8d9;
            border: 1px solid #fec800;
            border-radius: 8px;
        }

        .mise-en-avant-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 700;
        }

        .mise-en-avant-option input[type="checkbox"] {
            width: auto;
            margin: 0;
            accent-color: #e94a34;
        }

        .mise-en-avant-option .categories-aide {
            margin: 8px 0 0 28px;
        }
    </style>
</head>
<body>

<header class="admin-header">
    <img
        src="../images/logo-nws.jpeg"
        alt="Logo Normandie Web School"
        class="admin-logo"
    >

    <h1>Modifier un projet</h1>
    <p>Administration de la galerie NWS</p>
</header>

<main class="admin-main">
    <h2>Modification du projet étudiant</h2>

    <?php if ($erreur !== ""): ?>
        <p class="form-error"><?= e($erreur) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="projet-form">

        <label for="titre">Titre du projet *</label>
        <input
            type="text"
            id="titre"
            name="titre"
            value="<?= e($projet["titre"]) ?>"
            required
        >

        <label for="description">Description du projet *</label>
        <textarea id="description" name="description" rows="5" required><?= e($projet["description"]) ?></textarea>

        <label for="contexte">Contexte du projet</label>
        <textarea id="contexte" name="contexte" rows="4"><?= e($projet["contexte"]) ?></textarea>

        <label for="objectifs">Objectifs du projet</label>
        <textarea id="objectifs" name="objectifs" rows="4"><?= e($projet["objectifs"]) ?></textarea>

        <label for="outils_technologies">Outils et technologies</label>
        <textarea
            id="outils_technologies"
            name="outils_technologies"
            rows="3"
            placeholder="HTML, CSS, JavaScript, Figma..."
        ><?= e($projet["outils_technologies"]) ?></textarea>

        <label for="lien_projet">Lien vers le rendu final</label>
        <input
            type="url"
            id="lien_projet"
            name="lien_projet"
            value="<?= e($projet["lien_projet"]) ?>"
            placeholder="https://..."
        >

        <label for="etudiant">Nom de l'étudiant ou du groupe *</label>
        <input
            type="text"
            id="etudiant"
            name="etudiant"
            value="<?= e($projet["etudiant"]) ?>"
            required
        >

        <label for="annee">Année de réalisation *</label>
        <input
            type="number"
            id="annee"
            name="annee"
            min="2000"
            max="2100"
            value="<?= e($projet["annee"]) ?>"
            required
        >

        <label for="promotion">Promotion (1 à 5) *</label>
        <input
            type="number"
            id="promotion"
            name="promotion"
            min="1"
            max="5"
            step="1"
            value="<?= e($projet["promotion"]) ?>"
            required
        >

        <label for="specialite">Spécialité *</label>
        <select id="specialite" name="specialite" required>
            <option value="">Choisir une spécialité</option>
            <?php foreach ($specialites as $specialite): ?>
                <option
                    value="<?= e($specialite) ?>"
                    <?= $specialiteActuelle === $specialite ? "selected" : "" ?>
                >
                    <?= e($specialite) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="type_livrable">Type de livrable *</label>
        <input
            type="text"
            id="type_livrable"
            name="type_livrable"
            value="<?= e($projet["type_livrable"]) ?>"
            required
        >

        <!-- Mise en avant sur la page d'accueil -->
        <div class="mise-en-avant-option">
            <label for="mis_en_avant">
                <input
                    type="checkbox"
                    id="mis_en_avant"
                    name="mis_en_avant"
                    value="1"
                    <?= !empty($projet["mis_en_avant"]) ? "checked" : "" ?>
                >
                Mettre ce projet en avant sur la page d'accueil
            </label>

            <p class="categories-aide">
                Coche cette case pour valoriser ce projet dans la galerie publique.
            </p>
        </div>

        <!-- Catégories du projet -->
        <label>Catégories du projet</label>
        <p class="categories-aide">
            Coche les catégories que tu souhaites associer à ce projet.
            Tu peux en sélectionner plusieurs.
        </p>

        <div class="categories-selection">
            <?php foreach ($categoriesDisponibles as $categorie): ?>
                <label class="categorie-option">
                    <input
                        type="checkbox"
                        name="categories[]"
                        value="<?= (int) $categorie["id"] ?>"
                        <?= in_array(
                            (int) $categorie["id"],
                            $categoriesSelectionnees,
                            true
                        ) ? "checked" : "" ?>
                    >
                    <span><?= e($categorie["nom"]) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php if (empty($categoriesDisponibles)): ?>
            <p class="form-error">
                Aucune catégorie disponible. Ajoute d'abord des catégories
                dans la table categories de la base de données.
            </p>
        <?php endif; ?>

        <label>Image de couverture actuelle</label>

        <?php
        $imageActuelle = $projet["image_couverture"] ?? "";
        $cheminImage = $imageActuelle !== ""
            ? "../images/projets/" . basename($imageActuelle)
            : null;
        ?>

        <?php if (
            $cheminImage !== null &&
            is_file(__DIR__ . "/" . $cheminImage)
        ): ?>
            <img
                src="<?= e($cheminImage) ?>"
                alt="Image actuelle du projet"
                style="width: 220px; max-width: 100%; border-radius: 10px;"
            >
        <?php else: ?>
            <p>Aucune image de couverture pour ce projet.</p>
        <?php endif; ?>

        <label for="image_couverture">Remplacer l'image de couverture</label>
        <input
            type="file"
            id="image_couverture"
            name="image_couverture"
            accept=".jpg,.jpeg,.png,.webp"
        >

        <small>
            Laisse vide pour conserver l'image actuelle.
            Formats acceptés : JPG, PNG et WEBP. Maximum 5 Mo.
        </small>

        <div class="form-actions">
            <button type="submit">Enregistrer les modifications</button>
            <a href="gerer.php" class="cancel-button">Annuler</a>
        </div>

    </form>

    <p>
        <a href="gerer.php">← Retour à la liste des projets</a>
    </p>
</main>

</body>
</html>
