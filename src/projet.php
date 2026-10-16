<?php
require_once __DIR__ . "/connexion.php";

function e($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

/* Récupération du projet */
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    exit("Projet introuvable.");
}

/*
 * Incrémenter le compteur uniquement pour les projets publiés.
 * L'UPDATE est atomique pour éviter de perdre des incréments simultanés.
 */
$requeteVues = $connexion->prepare(
    "UPDATE projets
     SET nombre_vues = nombre_vues + 1
     WHERE id = :id AND statut = 'publie'"
);
$requeteVues->execute(["id" => $id]);

$requete = $connexion->prepare(
    "SELECT *
     FROM projets
     WHERE id = :id AND statut = 'publie'"
);
$requete->execute(["id" => $id]);
$projet = $requete->fetch(PDO::FETCH_ASSOC);

if (!$projet) {
    http_response_code(404);
    exit("Projet introuvable.");
}

// Le compteur affiché inclut la consultation actuelle.
$nombreVues = (int) $projet["nombre_vues"];

/* Récupération des catégories */
$requeteCategories = $connexion->prepare(
    "SELECT c.nom
     FROM projet_categories pc
     INNER JOIN categories c ON c.id = pc.categorie_id
     WHERE pc.projet_id = :projet_id
     ORDER BY c.nom ASC"
);
$requeteCategories->execute(["projet_id" => $id]);
$categoriesProjet = $requeteCategories->fetchAll(PDO::FETCH_COLUMN);

/* Image de couverture */
$image = null;

if (!empty($projet["image_couverture"])) {
    $nomImage = basename($projet["image_couverture"]);
    $cheminImage = "images/projets/" . $nomImage;

    if (is_file(__DIR__ . "/" . $cheminImage)) {
        $image = $cheminImage;
    }
}

/* Fichiers associés au projet */
$requeteFichiers = $connexion->prepare(
    "SELECT id, nom_fichier, nom_original, type_fichier
     FROM fichiers_projets
     WHERE projet_id = :projet_id
     ORDER BY date_ajout DESC, id DESC"
);
$requeteFichiers->execute(["projet_id" => $id]);
$fichiers = $requeteFichiers->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($projet["titre"]) ?> | Galerie NWS</title>

    <link rel="stylesheet" href="style.css?v=20">

    <style>
        .categories-detail {
            margin-top: 22px;
        }

        .categories-detail-titre {
            margin-bottom: 10px;
            font-weight: 700;
        }

        .categories-detail-liste {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .categorie-detail-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            background-color: #e0f5f3;
            color: #007b78;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .compteur-vues {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            padding: 9px 14px;
            border-radius: 20px;
            background: #fff1ee;
            color: #b83224;
            font-weight: 600;
        }

        .galerie-fichiers {
            margin-top: 45px;
        }

        .galerie-fichiers h2 {
            margin-bottom: 12px;
        }

        .galerie-fichiers-intro {
            margin-bottom: 24px;
            line-height: 1.7;
        }

        .fichiers-grille {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .fichier-carte {
            min-width: 0;
            padding: 16px;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            background: #fff;
        }

        .fichier-image {
            display: block;
            width: 100%;
            height: 220px;
            object-fit: contain;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .fichier-nom {
            margin: 12px 0;
            overflow-wrap: anywhere;
        }

        .fichier-lien {
            display: inline-block;
            margin-top: 8px;
            color: #e94a34;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
        }

        .fichier-pdf {
            display: flex;
            min-height: 150px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            border-radius: 8px;
            background: #fff1ee;
            color: #e94a34;
            font-size: 20px;
            font-weight: 700;
            text-decoration: none;
            text-align: center;
        }

        .fichier-pdf:hover,
        .fichier-lien:hover {
            opacity: 0.75;
        }

        .fichier-type {
            color: #666;
            font-size: 13px;
        }

        .fichiers-vides {
            padding: 20px;
            border-radius: 10px;
            background: #f5f5f5;
        }
    </style>
</head>

<body>

<header class="site-header">
    <a href="index.php" aria-label="Retour à la galerie">
        <img
            src="images/logo-nws.jpeg"
            alt="Logo Normandie Web School"
        >
    </a>

    <a class="lien-retour-haut" href="index.php">
        ← Toutes les réalisations
    </a>
</header>

<main class="detail-projet">

    <nav class="fil-ariane" aria-label="Fil d'Ariane">
        <a href="index.php">Galerie</a>
        <span> / </span>
        <span><?= e($projet["titre"]) ?></span>
    </nav>

    <section class="detail-hero">

        <div class="detail-hero-texte">

            <p class="detail-surtitre">
                PROJET ÉTUDIANT · NORMANDIE WEB SCHOOL
            </p>

            <h1><?= e($projet["titre"]) ?></h1>

            <p class="detail-auteur">
                Réalisé par <strong><?= e($projet["etudiant"]) ?></strong>
            </p>

            <div class="detail-badges">
                <span><?= e($projet["specialite"]) ?></span>
                <span><?= e($projet["type_livrable"]) ?></span>
                <span>Promotion <?= e($projet["promotion"]) ?></span>
                <span><?= e($projet["annee"]) ?></span>
            </div>

            <p class="compteur-vues">
                👁️ <?= number_format($nombreVues, 0, ",", " ") ?>
                <?= $nombreVues > 1 ? "vues" : "vue" ?>
            </p>

            <?php if (!empty($categoriesProjet)): ?>
                <div class="categories-detail">
                    <p class="categories-detail-titre">
                        Catégories du projet
                    </p>

                    <div class="categories-detail-liste">
                        <?php foreach ($categoriesProjet as $categorie): ?>
                            <span class="categorie-detail-badge">
                                <?= e($categorie) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($projet["lien_projet"])): ?>
                <a
                    class="bouton-projet-externe"
                    href="<?= e($projet["lien_projet"]) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Découvrir le projet <span aria-hidden="true">↗</span>
                </a>
            <?php endif; ?>

        </div>

        <?php if ($image !== null): ?>
            <div class="detail-image-conteneur">
                <img
                    class="detail-image"
                    src="<?= e($image) ?>"
                    alt="Visuel du projet <?= e($projet["titre"]) ?>"
                >
            </div>
        <?php endif; ?>

    </section>

    <div class="detail-grille">

        <article class="detail-contenu">

            <section class="detail-section">
                <p class="detail-numero">01 — PRÉSENTATION</p>
                <h2>À propos du projet</h2>
                <p class="detail-texte">
                    <?= nl2br(e($projet["description"])) ?>
                </p>
            </section>

            <?php if (!empty($projet["contexte"])): ?>
                <section class="detail-section">
                    <p class="detail-numero">02 — CONTEXTE</p>
                    <h2>Le point de départ</h2>
                    <p class="detail-texte">
                        <?= nl2br(e($projet["contexte"])) ?>
                    </p>
                </section>
            <?php endif; ?>

            <?php if (!empty($projet["objectifs"])): ?>
                <section class="detail-section">
                    <p class="detail-numero">03 — OBJECTIFS</p>
                    <h2>Les objectifs</h2>
                    <p class="detail-texte">
                        <?= nl2br(e($projet["objectifs"])) ?>
                    </p>
                </section>
            <?php endif; ?>

            <?php if (!empty($projet["outils_technologies"])): ?>
                <section class="detail-section">
                    <p class="detail-numero">04 — SAVOIR-FAIRE</p>
                    <h2>Outils et technologies</h2>
                    <div class="technologies-texte">
                        <?= nl2br(e($projet["outils_technologies"])) ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="detail-section galerie-fichiers">
                <p class="detail-numero">05 — RÉALISATIONS</p>
                <h2>Galerie des fichiers du projet</h2>

                <p class="galerie-fichiers-intro">
                    Retrouvez les images et documents complémentaires
                    de cette réalisation étudiante.
                </p>

                <?php $nombreFichiersAffiches = 0; ?>

                <?php if (!empty($fichiers)): ?>
                    <div class="fichiers-grille">

                        <?php foreach ($fichiers as $fichier): ?>
                            <?php
                            $nomStocke = basename($fichier["nom_fichier"]);
                            $nomOriginal = $fichier["nom_original"] ?? $nomStocke;

                            $extension = strtolower(
                                pathinfo($nomStocke, PATHINFO_EXTENSION)
                            );

                            $extensionOriginale = strtolower(
                                pathinfo($nomOriginal, PATHINFO_EXTENSION)
                            );

                            $typeMime = strtolower(trim(
                                explode(
                                    ";",
                                    (string) ($fichier["type_fichier"] ?? "")
                                )[0]
                            ));

                            $cheminFichier = __DIR__
                                . "/uploads/projets/" . $nomStocke;

                            $urlFichier = "uploads/projets/"
                                . rawurlencode($nomStocke);

                            $estImage =
                                in_array(
                                    $extension,
                                    ["jpg", "jpeg", "png", "webp"],
                                    true
                                )
                                || in_array(
                                    $extensionOriginale,
                                    ["jpg", "jpeg", "png", "webp"],
                                    true
                                )
                                || in_array(
                                    $typeMime,
                                    ["image/jpeg", "image/png", "image/webp"],
                                    true
                                );

                            $estPdf =
                                $extension === "pdf"
                                || $extensionOriginale === "pdf"
                                || $typeMime === "application/pdf";

                            if (!is_file($cheminFichier)) {
                                continue;
                            }

                            $nombreFichiersAffiches++;
                            ?>

                            <div class="fichier-carte">

                                <?php if ($estImage): ?>
                                    <a
                                        href="<?= e($urlFichier) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="Ouvrir l'image <?= e($nomOriginal) ?>"
                                    >
                                        <img
                                            class="fichier-image"
                                            src="<?= e($urlFichier) ?>"
                                            alt="<?= e($nomOriginal) ?>"
                                            loading="lazy"
                                        >
                                    </a>

                                    <p class="fichier-nom">
                                        <strong><?= e($nomOriginal) ?></strong>
                                    </p>

                                    <a
                                        class="fichier-lien"
                                        href="<?= e($urlFichier) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Voir l'image ↗
                                    </a>

                                    <p class="fichier-type">Image</p>

                                <?php elseif ($estPdf): ?>
                                    <a
                                        class="fichier-pdf"
                                        href="<?= e($urlFichier) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="Ouvrir le PDF <?= e($nomOriginal) ?>"
                                    >
                                        <span aria-hidden="true">📄 PDF</span>
                                        <span>Ouvrir le document ↗</span>
                                    </a>

                                    <p class="fichier-nom">
                                        <strong><?= e($nomOriginal) ?></strong>
                                    </p>

                                    <a
                                        class="fichier-lien"
                                        href="<?= e($urlFichier) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Consulter le PDF ↗
                                    </a>

                                    <p class="fichier-type">Document PDF</p>

                                <?php else: ?>
                                    <p class="fichier-nom">
                                        <strong><?= e($nomOriginal) ?></strong>
                                    </p>

                                    <a
                                        class="fichier-lien"
                                        href="<?= e($urlFichier) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Ouvrir ou télécharger le fichier ↗
                                    </a>

                                    <p class="fichier-type">Fichier joint</p>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>

                    </div>

                    <?php if ($nombreFichiersAffiches === 0): ?>
                        <p class="fichiers-vides">
                            Aucun fichier disponible sur le serveur pour ce projet.
                        </p>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="fichiers-vides">
                        Aucun fichier complémentaire n'a été ajouté à ce projet.
                    </p>
                <?php endif; ?>

            </section>

            <?php if (!empty($projet["lien_projet"])): ?>
                <section class="detail-cta">
                    <div>
                        <h2>Envie de découvrir le résultat ?</h2>
                        <p>
                            Consultez la réalisation complète de ce projet étudiant.
                        </p>
                    </div>

                    <a
                        class="bouton-projet-externe"
                        href="<?= e($projet["lien_projet"]) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Voir le projet ↗
                    </a>
                </section>
            <?php endif; ?>

        </article>

        <aside class="detail-sidebar">
            <div class="detail-info-card">
                <p class="detail-numero">FICHE DU PROJET</p>
                <h2>En bref</h2>

                <div class="detail-info-ligne">
                    <span>Étudiant(s)</span>
                    <strong><?= e($projet["etudiant"]) ?></strong>
                </div>

                <div class="detail-info-ligne">
                    <span>Année</span>
                    <strong><?= e($projet["annee"]) ?></strong>
                </div>

                <div class="detail-info-ligne">
                    <span>Promotion</span>
                    <strong><?= e($projet["promotion"]) ?></strong>
                </div>

                <div class="detail-info-ligne">
                    <span>Spécialité</span>
                    <strong><?= e($projet["specialite"]) ?></strong>
                </div>

                <div class="detail-info-ligne">
                    <span>Livrable</span>
                    <strong><?= e($projet["type_livrable"]) ?></strong>
                </div>

                <div class="detail-info-ligne">
                    <span>Nombre de vues</span>
                    <strong><?= number_format($nombreVues, 0, ",", " ") ?></strong>
                </div>
            </div>
        </aside>

    </div>

    <a class="retour-galerie-detail" href="index.php">
        ← Retour à toutes les réalisations
    </a>

</main>

<footer class="detail-footer">
    <p>Galerie des projets étudiants · Normandie Web School</p>
</footer>

</body>
</html>
