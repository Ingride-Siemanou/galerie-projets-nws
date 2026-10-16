<?php
session_start();

if (empty($_SESSION["admin_connecte"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../connexion.php";

function e($valeur): string
{
    return htmlspecialchars(
        (string) ($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Récupérer les statistiques des projets
$requeteStats = $connexion->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(statut = 'publie'), 0) AS publies,
        COALESCE(SUM(statut = 'en_attente'), 0) AS attente,
        COALESCE(SUM(statut = 'refuse'), 0) AS refuses,
        COALESCE(
            SUM(statut = 'publie' AND mis_en_avant = 1), 0
        ) AS mis_en_avant
     FROM projets"
);

$statistiques = $requeteStats->fetch(PDO::FETCH_ASSOC);

// Récupérer les derniers projets
$requeteRecents = $connexion->query(
    "SELECT id, titre, etudiant, annee, statut
     FROM projets
     ORDER BY id DESC
     LIMIT 5"
);

$projetsRecents = $requeteRecents->fetchAll(PDO::FETCH_ASSOC);

// Compter les fichiers associés aux projets
$totalFichiers = 0;

try {
    $requeteFichiers = $connexion->query(
        "SELECT COUNT(*) FROM fichiers_projets"
    );

    $totalFichiers = (int) $requeteFichiers->fetchColumn();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
}

$statuts = [
    "publie" => "Publié",
    "en_attente" => "En attente",
    "refuse" => "Refusé"
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord - Administration NWS</title>

    <link rel="stylesheet" href="admin.css?v=12">
</head>
<body>

<header class="admin-header">
    <img
        src="../images/logo-nws.jpeg"
        alt="Logo Normandie Web School"
        class="admin-logo"
    >

    <h1>Tableau de bord NWS</h1>
    <p>Votre espace de gestion des projets étudiants</p>

    <div class="admin-header-actions">
        <a href="../index.php" class="gallery-button">
            Voir la galerie publique
        </a>

        <form action="logout.php" method="post">
            <button type="submit" class="logout-button">
                Se déconnecter
            </button>
        </form>
    </div>
</header>

<main class="admin-main">

    <section class="dashboard-intro">
        <h2>Bienvenue dans votre espace administrateur </h2>
        <p>
            Retrouvez les chiffres clés de la galerie et les accès rapides
            pour gérer les réalisations étudiantes.
        </p>
    </section>

    <!-- Statistiques -->
    <section class="dashboard-stats" aria-label="Statistiques de la galerie">

        <article class="dashboard-stat">
            <span class="dashboard-stat-label">Tous les projets</span>
            <strong class="dashboard-stat-number">
                <?= (int) $statistiques["total"] ?>
            </strong>
            <span class="dashboard-stat-detail">
                Projets enregistrés
            </span>
        </article>

        <article class="dashboard-stat published">
            <span class="dashboard-stat-label">Projets publiés</span>
            <strong class="dashboard-stat-number">
                <?= (int) $statistiques["publies"] ?>
            </strong>
            <span class="dashboard-stat-detail">
                Visibles dans la galerie
            </span>
        </article>

        <article class="dashboard-stat pending">
            <span class="dashboard-stat-label">En attente</span>
            <strong class="dashboard-stat-number">
                <?= (int) $statistiques["attente"] ?>
            </strong>
            <span class="dashboard-stat-detail">
                À examiner
            </span>
        </article>

        <article class="dashboard-stat featured">
            <span class="dashboard-stat-label">Projets à la une</span>
            <strong class="dashboard-stat-number">
                <?= (int) $statistiques["mis_en_avant"] ?>
            </strong>
            <span class="dashboard-stat-detail">
                Parmi les projets publiés
            </span>
        </article>

    </section>

    <!-- Actions rapides -->
    <section class="dashboard-section">
        <div class="dashboard-section-heading">
            <h2>Actions rapides</h2>
        </div>

        <div class="dashboard-actions">

            <a href="ajouter.php" class="admin-card admin-card-link">
                <h3>Ajouter un projet</h3>
                <p>
                    Enregistrer une nouvelle réalisation étudiante
                    dans la galerie.
                </p>
                <span class="admin-card-action">
                    Ajouter un projet →
                </span>
            </a>

            <a href="propositions.php" class="admin-card admin-card-link">
                <h3>Propositions à valider</h3>
                <p>
                    Examiner les projets envoyés par les étudiants
                    et décider de leur publication.
                </p>
                <span class="admin-card-action">
                    Examiner les propositions →
                </span>
            </a>

            <a href="gerer.php" class="admin-card admin-card-link">
                <h3>Gérer les projets</h3>
                <p>
                    Consulter les projets, modifier leurs informations
                    ou supprimer ceux qui ne sont plus nécessaires.
                </p>
                <span class="admin-card-action">
                    Gérer les projets →
                </span>
            </a>

            <a href="fichiers.php" class="admin-card admin-card-link">
            
                <h3>Gérer les fichiers</h3>
                <p>
                    Ajouter ou supprimer les images et documents PDF
                    associés aux projets.
                </p>
                <span class="admin-card-action">
                    Gérer les fichiers (<?= $totalFichiers ?>) →
                </span>
            </a>

        </div>
    </section>

    <!-- Derniers projets -->
    <section class="dashboard-section">
        <div class="dashboard-section-heading">
            <h2>Derniers projets enregistrés</h2>
            <a href="gerer.php">Voir tous les projets →</a>
        </div>

        <?php if (empty($projetsRecents)): ?>

            <div class="dashboard-table-wrap">
                <p class="dashboard-empty">
                    Aucun projet enregistré pour le moment.
                    Tu peux commencer par ajouter un projet.
                </p>
            </div>

        <?php else: ?>

            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Projet</th>
                            <th>Étudiant(e)</th>
                            <th>Année</th>
                            <th>Statut</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($projetsRecents as $projet): ?>
                            <?php
                            $statut = $projet["statut"] ?? "";
                            $libelleStatut = $statuts[$statut] ?? $statut;
                            ?>

                            <tr>
                                <td>
                                    <span class="dashboard-project-title">
                                        <?= e($projet["titre"]) ?>
                                    </span>
                                </td>

                                <td><?= e($projet["etudiant"]) ?></td>
                                <td><?= e($projet["annee"]) ?></td>

                                <td>
                                    <span class="dashboard-status <?= e($statut) ?>">
                                        <?= e($libelleStatut) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <footer class="dashboard-footer">
        Galerie des projets étudiants · Normandie Web School
    </footer>

</main>

</body>
</html>
