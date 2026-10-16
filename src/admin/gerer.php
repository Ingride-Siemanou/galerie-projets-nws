<?php
session_start();

// Protéger la page administrateur
if (empty($_SESSION["admin_connecte"])) {
    http_response_code(403);
    exit("Accès interdit : connexion administrateur requise.");
}

// Connexion à la base de données
require_once __DIR__ . "/../connexion.php";

// Récupérer les projets enregistrés
$requete = $connexion->query(
    "SELECT id, titre, etudiant, annee, promotion, specialite, type_livrable
     FROM projets
     ORDER BY id DESC"
);

$projets = $requete->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les projets - Administration NWS</title>
    <link rel="stylesheet" href="admin.css?v=6">
</head>
<body>

    <header class="admin-header">
        <img
            src="../images/logo-nws.jpeg"
            alt="Logo Normandie Web School"
            class="admin-logo"
        >

        <h1>Gérer les projets</h1>
        <p>Administration de la galerie NWS</p>
    </header>

    <main class="admin-main">
        <h2>Liste des projets étudiants</h2>

<a href="ajouter.php" class="add-project-button">
    + Ajouter un projet
</a>

        
<?php if (isset($_GET["ajoute"]) && $_GET["ajoute"] === "1"): ?>
    <p class="success-message">
        Le projet a bien été ajouté à la galerie NWS !
    </p>
<?php endif; ?>

        
<?php if (isset($_GET["supprime"]) && $_GET["supprime"] === "1"): ?>
    <p class="success-message">
        Le projet a bien été supprimé !
    </p>
<?php endif; ?>

<?php if (isset($_GET["modifie"]) && $_GET["modifie"] === "1"): ?>
    <p class="success-message">
        Les modifications du projet ont bien été enregistrées !
    </p>
<?php endif; ?>



        <?php if (empty($projets)): ?>

            <p>Aucun projet enregistré pour le moment.</p>

        <?php else: ?>

            <div class="table-container">
                <table class="projets-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Étudiant</th>
                            <th>Année</th>
                            <th>Promotion</th>
                            <th>Spécialité</th>
                            <th>Type de livrable</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($projets as $projet): ?>
                            <tr>
                                <td><?= htmlspecialchars($projet["titre"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($projet["etudiant"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string) $projet["annee"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars((string) $projet["promotion"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($projet["specialite"], ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($projet["type_livrable"], ENT_QUOTES, "UTF-8") ?></td>
                                
                                <td>
                                <div class="project-actions">
                                    <a
                                        href="modifier.php?id=<?= (int) $projet['id'] ?>"
                                        class="edit-button">
                                        Modifier
                                    </a>

                                    
<form
    action="supprimer.php"
    method="post"
    onsubmit="return confirm('Voulez-vous vraiment supprimer ce projet ?');"
>
    <input
        type="hidden"
        name="id"
        value="<?= (int) $projet['id'] ?>"
    >

    <button type="submit" class="delete-button">
        Supprimer
    </button>
</form>

</div>
</td>

</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
 <p>
            <a href="index.php">← Retour au tableau de bord</a>
        </p>
    </main>

</body>
</html>
