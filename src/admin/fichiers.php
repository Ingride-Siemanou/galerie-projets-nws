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

// Dossier de stockage : src/uploads/projets/
$dossier = dirname(__DIR__) . "/uploads/projets/";
$urlDossier = "../uploads/projets/";

$erreur = "";
$succes = "";

$idProjet = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$idProjet || $idProjet < 1) {
    $idProjet = filter_input(INPUT_POST, "projet_id", FILTER_VALIDATE_INT);
}

// Récupérer les projets
$projets = $connexion->query(
    "SELECT id, titre FROM projets ORDER BY titre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$projetSelectionne = null;

// Vérifier le projet sélectionné
if ($idProjet) {
    $requete = $connexion->prepare(
        "SELECT id, titre FROM projets WHERE id = :id"
    );
    $requete->execute(["id" => $idProjet]);
    $projetSelectionne = $requete->fetch(PDO::FETCH_ASSOC);

    if (!$projetSelectionne) {
        $erreur = "Le projet sélectionné n'existe pas.";
        $idProjet = null;
    }
}

/* Ajouter des fichiers */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["ajouter_fichiers"])
) {
    if (!$idProjet || !$projetSelectionne) {
        $erreur = "Sélectionne un projet valide.";
    } elseif (
        !isset($_FILES["fichiers"])
        || !is_array($_FILES["fichiers"]["name"])
    ) {
        $erreur = "Sélectionne au moins un fichier.";
    } else {
        $formats = [
            "image/jpeg" => ["jpg", "image"],
            "image/png" => ["png", "image"],
            "image/webp" => ["webp", "image"],
            "application/pdf" => ["pdf", "document"]
        ];

        // Vérifier le dossier de stockage
        if (!is_dir($dossier)) {
            $erreur = "Le dossier de stockage est introuvable : "
                . $dossier
                . " Crée le dossier src/uploads/projets dans VS Code.";
        } elseif (!is_writable($dossier)) {
            $erreur = "PHP ne peut pas écrire dans le dossier "
                . "src/uploads/projets. Vérifie les permissions Docker.";
        } else {
            $total = count($_FILES["fichiers"]["name"]);
            $ajoutes = 0;

            if ($total > 20) {
                $erreur = "Maximum 20 fichiers par envoi.";
            }

            for ($i = 0; $erreur === "" && $i < $total; $i++) {
                $fichier = [
                    "name" => $_FILES["fichiers"]["name"][$i],
                    "tmp_name" => $_FILES["fichiers"]["tmp_name"][$i],
                    "error" => $_FILES["fichiers"]["error"][$i],
                    "size" => $_FILES["fichiers"]["size"][$i]
                ];

                // Afficher une explication précise en cas d'erreur PHP
                if ($fichier["error"] !== UPLOAD_ERR_OK) {
                    $codesErreurs = [
                        UPLOAD_ERR_INI_SIZE =>
                            "Le fichier dépasse la limite définie dans PHP.",
                        UPLOAD_ERR_FORM_SIZE =>
                            "Le fichier dépasse la taille autorisée par le formulaire.",
                        UPLOAD_ERR_PARTIAL =>
                            "Le fichier a été envoyé partiellement.",
                        UPLOAD_ERR_NO_FILE =>
                            "Aucun fichier n'a été reçu.",
                        UPLOAD_ERR_NO_TMP_DIR =>
                            "Le dossier temporaire de PHP est absent.",
                        UPLOAD_ERR_CANT_WRITE =>
                            "PHP ne peut pas écrire le fichier temporaire.",
                        UPLOAD_ERR_EXTENSION =>
                            "Une extension PHP a interrompu l'envoi."
                    ];

                    $code = $fichier["error"];

                    $erreur = $codesErreurs[$code]
                        ?? "Erreur inconnue pendant l'envoi (code "
                        . $code . ").";

                    break;
                }

                // Vérifier la taille maximale : 10 Mo
                if ($fichier["size"] > 10 * 1024 * 1024) {
                    $erreur = "Chaque fichier doit peser au maximum 10 Mo.";
                    break;
                }

                // Vérifier que le fichier temporaire existe
                if (
                    $fichier["tmp_name"] === ""
                    || !is_uploaded_file($fichier["tmp_name"])
                ) {
                    $erreur = "PHP n'a pas reçu correctement le fichier.";
                    break;
                }

                // Détecter le véritable type MIME du fichier
                $mime = (new finfo(FILEINFO_MIME_TYPE))
                    ->file($fichier["tmp_name"]);

                if (!isset($formats[$mime])) {
                    $erreur = "Formats autorisés : JPG, PNG, WEBP et PDF.";
                    break;
                }

                // Vérifier que le fichier image est valide
                if (
                    str_starts_with($mime, "image/")
                    && @getimagesize($fichier["tmp_name"]) === false
                ) {
                    $erreur = "Un fichier image n'est pas valide.";
                    break;
                }

                $extension = $formats[$mime][0];
                $typeFichier = $formats[$mime][1];
                $nomOriginal = basename($fichier["name"]);

                try {
                    $nomUnique = bin2hex(random_bytes(16))
                        . "." . $extension;
                } catch (Throwable $exception) {
                    error_log($exception->getMessage());
                    $erreur = "Impossible de générer le nom du fichier.";
                    break;
                }

                $cheminFinal = $dossier . $nomUnique;

                // Déplacer le fichier dans le dossier définitif
                if (!move_uploaded_file(
                    $fichier["tmp_name"],
                    $cheminFinal
                )) {
                    $erreur = "PHP n'a pas réussi à enregistrer le fichier. "
                        . "Vérifie les permissions du dossier.";
                    break;
                }

                // Enregistrer les informations dans la base de données
                try {
                    $requete = $connexion->prepare(
                        "INSERT INTO fichiers_projets
                            (projet_id, nom_fichier, nom_original, type_fichier)
                         VALUES
                            (:projet_id, :nom_fichier, :nom_original, :type_fichier)"
                    );

                    $requete->execute([
                        "projet_id" => $idProjet,
                        "nom_fichier" => $nomUnique,
                        "nom_original" => $nomOriginal,
                        "type_fichier" => $typeFichier
                    ]);

                    $ajoutes++;
                } catch (PDOException $exception) {
                    if (is_file($cheminFinal)) {
                        unlink($cheminFinal);
                    }

                    error_log($exception->getMessage());
                    $erreur = "Erreur lors de l'enregistrement en base de données.";
                    break;
                }
            }

            // Rediriger après un ajout réussi
            if ($erreur === "" && $ajoutes > 0) {
                header(
                    "Location: fichiers.php?id="
                    . $idProjet
                    . "&ajoute="
                    . $ajoutes
                );
                exit;
            }

            if ($erreur === "" && $ajoutes === 0) {
                $erreur = "Sélectionne au moins un fichier.";
            }
        }
    }
}

/* Message après ajout */
if (
    isset($_GET["ajoute"])
    && ctype_digit((string) $_GET["ajoute"])
) {
    $succes = (int) $_GET["ajoute"]
        . " fichier(s) ajouté(s) avec succès.";
}

/* Supprimer un fichier */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["supprimer_fichier"])
) {
    $fichierId = filter_input(INPUT_POST, "fichier_id", FILTER_VALIDATE_INT);

    if (!$idProjet || !$fichierId || !$projetSelectionne) {
        $erreur = "Projet ou fichier invalide.";
    } else {
        try {
            $requete = $connexion->prepare(
                "SELECT id, nom_fichier
                 FROM fichiers_projets
                 WHERE id = :fichier_id AND projet_id = :projet_id"
            );

            $requete->execute([
                "fichier_id" => $fichierId,
                "projet_id" => $idProjet
            ]);

            $fichier = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$fichier) {
                $erreur = "Fichier introuvable.";
            } else {
                $requete = $connexion->prepare(
                    "DELETE FROM fichiers_projets
                     WHERE id = :fichier_id AND projet_id = :projet_id"
                );

                $requete->execute([
                    "fichier_id" => $fichierId,
                    "projet_id" => $idProjet
                ]);

                $chemin = $dossier
                    . basename($fichier["nom_fichier"]);

                if (is_file($chemin)) {
                    unlink($chemin);
                }

                header(
                    "Location: fichiers.php?id="
                    . $idProjet
                    . "&supprime=1"
                );
                exit;
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $erreur = "Impossible de supprimer le fichier.";
        }
    }
}

/* Message après suppression */
if (isset($_GET["supprime"])) {
    $succes = "Le fichier a été supprimé.";
}

/* Récupérer les fichiers du projet sélectionné */
$fichiers = [];

if ($idProjet && $projetSelectionne) {
    $requete = $connexion->prepare(
        "SELECT id, nom_fichier, nom_original, type_fichier, date_ajout
         FROM fichiers_projets
         WHERE projet_id = :id
         ORDER BY date_ajout DESC"
    );

    $requete->execute(["id" => $idProjet]);
    $fichiers = $requete->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fichiers des projets - Administration NWS</title>
    <link rel="stylesheet" href="admin.css?v=14">
</head>
<body>

<header class="admin-header">
    <img src="../images/logo-nws.jpeg"
         alt="Logo Normandie Web School"
         class="admin-logo">
    <h1>Fichiers des projets</h1>
    <p>Gestion des images et documents de la galerie NWS</p>
</header>

<main class="admin-main">
    <h2>Gérer les fichiers d'un projet</h2>

    <?php if ($erreur !== ""): ?>
        <p class="form-error"><?= e($erreur) ?></p>
    <?php endif; ?>

    <?php if ($succes !== ""): ?>
        <p class="success-message"><?= e($succes) ?></p>
    <?php endif; ?>

    <form method="get" class="projet-form">
        <label for="id">Choisir un projet</label>

        <select id="id" name="id" required>
            <option value="">Sélectionner un projet</option>

            <?php foreach ($projets as $projet): ?>
                <option
                    value="<?= (int) $projet["id"] ?>"
                    <?= (int) $idProjet === (int) $projet["id"]
                        ? "selected"
                        : "" ?>>
                    <?= e($projet["titre"]) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Afficher les fichiers</button>
    </form>

    <?php if ($projetSelectionne): ?>
        <h2><?= e($projetSelectionne["titre"]) ?></h2>

        <form method="post"
              enctype="multipart/form-data"
              class="projet-form">

            <input type="hidden"
                   name="projet_id"
                   value="<?= (int) $idProjet ?>">

            <label for="fichiers">Images et documents à ajouter</label>

            <input type="file"
                   id="fichiers"
                   name="fichiers[]"
                   accept=".jpg,.jpeg,.png,.webp,.pdf"
                   multiple
                   required>

            <small>
                Formats autorisés : JPG, PNG, WEBP et PDF.
                Maximum 10 Mo par fichier, 20 fichiers par envoi.
            </small>

            <div class="form-actions">
                <button type="submit"
                        name="ajouter_fichiers"
                        value="1">
                    Ajouter les fichiers
                </button>
            </div>
        </form>

        <h2>Fichiers déjà ajoutés</h2>

        <?php if (!$fichiers): ?>
            <p>Aucun fichier supplémentaire pour ce projet.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="projets-table">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Type</th>
                            <th>Date d'ajout</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($fichiers as $fichier): ?>
                        <tr>
                            <td>
                                <a
                                    href="<?= e(
                                        $urlDossier
                                        . rawurlencode($fichier["nom_fichier"])
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer">
                                    <?= e($fichier["nom_original"]) ?>
                                </a>
                            </td>

                            <td><?= e($fichier["type_fichier"]) ?></td>
                            <td><?= e($fichier["date_ajout"]) ?></td>

                            <td>
                                <form method="post"
                                      onsubmit="return confirm('Supprimer ce fichier ?');">

                                    <input type="hidden"
                                           name="projet_id"
                                           value="<?= (int) $idProjet ?>">

                                    <input type="hidden"
                                           name="fichier_id"
                                           value="<?= (int) $fichier["id"] ?>">

                                    <button type="submit"
                                            name="supprimer_fichier"
                                            value="1"
                                            class="delete-button">
                                        Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="gerer.php">← Retour à la gestion des projets</a></p>
</main>

</body>
</html>
