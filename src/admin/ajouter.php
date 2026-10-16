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

$erreur = "";

$champs = [
    "titre" => "",
    "description" => "",
    "etudiant" => "",
    "annee" => (string) date("Y"),
    "promotion" => "",
    "specialite" => "",
    "type_livrable" => "",
    "contexte" => "",
    "objectifs" => "",
    "outils_technologies" => "",
    "lien_projet" => "",
    "mis_en_avant" => "0"
];

$categoriesSelectionnees = [];
$categoriesDisponibles = [];

// Charger les catégories
try {
    $requeteCategories = $connexion->query(
        "SELECT id, nom FROM categories ORDER BY nom ASC"
    );
    $categoriesDisponibles = $requeteCategories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $erreur = "Impossible de charger les catégories.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($champs as $nom => $valeur) {
        if ($nom !== "mis_en_avant") {
            $champs[$nom] = trim((string) ($_POST[$nom] ?? ""));
        }
    }

    // La case n'est pas envoyée si elle n'est pas cochée
    $champs["mis_en_avant"] = isset($_POST["mis_en_avant"]) ? "1" : "0";

    // Récupérer les catégories
    $categoriesPost = $_POST["categories"] ?? [];

    if (!is_array($categoriesPost)) {
        $categoriesPost = [];
    }

    $categoriesSelectionnees = array_values(array_unique(
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
            static fn($id) => $id > 0
        )
    ));

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

    // Vérifier le lien
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

    // Vérifier les catégories sélectionnées
    if ($erreur === "" && !empty($categoriesSelectionnees)) {
        try {
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
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $erreur = "Erreur lors de la vérification des catégories.";
        }
    }

    // Image de couverture
    $nomImage = null;
    $cheminImage = null;

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

                    $cheminImage = $dossier . $nomImage;

                    if (!move_uploaded_file(
                        $fichier["tmp_name"],
                        $cheminImage
                    )) {
                        $erreur = "Impossible d'enregistrer l'image.";
                        $nomImage = null;
                        $cheminImage = null;
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
                "INSERT INTO projets (
                    titre,
                    description,
                    etudiant,
                    annee,
                    promotion,
                    specialite,
                    type_livrable,
                    image_couverture,
                    contexte,
                    objectifs,
                    outils_technologies,
                    lien_projet,
                    mis_en_avant
                ) VALUES (
                    :titre,
                    :description,
                    :etudiant,
                    :annee,
                    :promotion,
                    :specialite,
                    :type_livrable,
                    :image_couverture,
                    :contexte,
                    :objectifs,
                    :outils_technologies,
                    :lien_projet,
                    :mis_en_avant
                )"
            );

            $requete->execute([
                "titre" => $champs["titre"],
                "description" => $champs["description"],
                "etudiant" => $champs["etudiant"],
                "annee" => (int) $champs["annee"],
                "promotion" => (int) $champs["promotion"],
                "specialite" => $champs["specialite"],
                "type_livrable" => $champs["type_livrable"],
                "image_couverture" => $nomImage,
                "contexte" => $champs["contexte"] !== ""
                    ? $champs["contexte"] : null,
                "objectifs" => $champs["objectifs"] !== ""
                    ? $champs["objectifs"] : null,
                "outils_technologies" => $champs["outils_technologies"] !== ""
                    ? $champs["outils_technologies"] : null,
                "lien_projet" => $champs["lien_projet"],
                "mis_en_avant" => (int) $champs["mis_en_avant"]
            ]);

            $projetId = (int) $connexion->lastInsertId();

            // Associer les catégories au projet
            if (!empty($categoriesSelectionnees)) {
                $requeteAssociation = $connexion->prepare(
                    "INSERT INTO projet_categories (projet_id, categorie_id)
                     VALUES (:projet_id, :categorie_id)"
                );

                foreach ($categoriesSelectionnees as $categorieId) {
                    $requeteAssociation->execute([
                        "projet_id" => $projetId,
                        "categorie_id" => $categorieId
                    ]);
                }
            }

            $connexion->commit();

            header("Location: gerer.php?ajoute=1");
            exit;
        } catch (Throwable $exception) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            if ($cheminImage !== null && is_file($cheminImage)) {
                unlink($cheminImage);
            }

            error_log($exception->getMessage());
            $nomImage = null;
            $cheminImage = null;
            $erreur = "Erreur lors de l'enregistrement. Vérifie les informations et la structure de la base de données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un projet - Administration NWS</title>
    <link rel="stylesheet" href="admin.css?v=16">

    <style>
        .categories-choix {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin: 10px 0 20px;
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
            accent-color: #e94a34;
        }

        .categorie-option span {
            font-weight: normal;
        }

        .categories-aide {
            display: block;
            margin-bottom: 12px;
            color: #666;
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

        .mise-en-avant-option p {
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
    <h1>Ajouter un projet</h1>
    <p>Galerie des projets étudiants NWS</p>
</header>

<main class="admin-main">
    <h2>Nouveau projet étudiant</h2>
    <p>Les champs marqués d'un * sont obligatoires.</p>

    <?php if ($erreur !== ""): ?>
        <p class="form-error"><?= e($erreur) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="projet-form">

        <label for="titre">Titre du projet *</label>
        <input
            type="text"
            id="titre"
            name="titre"
            value="<?= e($champs["titre"]) ?>"
            required
        >

        <label for="description">Description *</label>
        <textarea
            id="description"
            name="description"
            rows="5"
            required
        ><?= e($champs["description"]) ?></textarea>

        <label for="contexte">Contexte du projet</label>
        <textarea
            id="contexte"
            name="contexte"
            rows="4"
        ><?= e($champs["contexte"]) ?></textarea>

        <label for="objectifs">Objectifs du projet</label>
        <textarea
            id="objectifs"
            name="objectifs"
            rows="4"
        ><?= e($champs["objectifs"]) ?></textarea>

        <label for="outils_technologies">Outils et technologies</label>
        <textarea
            id="outils_technologies"
            name="outils_technologies"
            rows="3"
            placeholder="HTML, CSS, JavaScript, Figma..."
        ><?= e($champs["outils_technologies"]) ?></textarea>

        <label for="lien_projet">Lien vers le projet final</label>
        <input
            type="url"
            id="lien_projet"
            name="lien_projet"
            value="<?= e($champs["lien_projet"]) ?>"
            placeholder="https://..."
        >

        <label for="etudiant">Étudiant ou groupe *</label>
        <input
            type="text"
            id="etudiant"
            name="etudiant"
            value="<?= e($champs["etudiant"]) ?>"
            required
        >

        <label for="annee">Année de réalisation *</label>
        <input
            type="number"
            id="annee"
            name="annee"
            min="2000"
            max="2100"
            value="<?= e($champs["annee"]) ?>"
            required
        >

        <label for="promotion">Promotion (1 à 5) *</label>
        <select id="promotion" name="promotion" required>
            <option value="">Choisir une promotion</option>
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <option
                    value="<?= $i ?>"
                    <?= $champs["promotion"] === (string) $i ? "selected" : "" ?>
                >
                    Promotion <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="specialite">Spécialité *</label>
        <select id="specialite" name="specialite" required>
            <option value="">Choisir une spécialité</option>
            <?php
            
$specialites = [
    "développement web",
    "communication graphique",
    "communication digital",
    "Marketing digital"
];

            ?>
            <?php foreach ($specialites as $specialite): ?>
                <option
                    value="<?= e($specialite) ?>"
                    <?= $champs["specialite"] === $specialite ? "selected" : "" ?>
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
            value="<?= e($champs["type_livrable"]) ?>"
            placeholder="Site web, application, affiche..."
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
                    <?= $champs["mis_en_avant"] === "1" ? "checked" : "" ?>
                >
                Mettre ce projet en avant sur la page d’accueil
            </label>
            <p class="categories-aide">
                Coche cette case pour valoriser ce projet dans la galerie publique.
            </p>
        </div>

        <label>Catégories du projet</label>
        <small class="categories-aide">
            Coche une ou plusieurs catégories correspondant au projet.
        </small>

        <?php if (!empty($categoriesDisponibles)): ?>
            <div class="categories-choix">
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
        <?php else: ?>
            <p>Aucune catégorie disponible dans la base de données.</p>
        <?php endif; ?>

        <label for="image_couverture">Image de couverture</label>
        <input
            type="file"
            id="image_couverture"
            name="image_couverture"
            accept=".jpg,.jpeg,.png,.webp"
        >

        <small>Formats acceptés : JPG, PNG et WEBP. Taille maximale : 5 Mo.</small>

        <div class="form-actions">
            <button type="submit">Ajouter le projet</button>
            <a href="gerer.php" class="cancel-button">Annuler</a>
        </div>
    </form>

    <p><a href="index.php">← Retour au tableau de bord</a></p>
</main>

</body>
</html>
