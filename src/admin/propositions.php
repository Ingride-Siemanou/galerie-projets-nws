<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../connexion.php";

// Créer un jeton de sécurité pour les formulaires
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$typeMessage = "";

// Traiter la décision de l'administrateur
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $jeton = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $jeton)) {
        $message = "La vérification de sécurité a échoué. Réessaie.";
        $typeMessage = "erreur";
    } else {
        $idProjet = filter_input(INPUT_POST, "projet_id", FILTER_VALIDATE_INT);
        $decision = $_POST["decision"] ?? "";

        if (!$idProjet || !in_array($decision, ["publie", "refuse"], true)) {
            $message = "La demande est invalide.";
            $typeMessage = "erreur";
        } else {
            try {
                $requete = $connexion->prepare(
                    "UPDATE projets
                     SET statut = :statut
                     WHERE id = :id AND statut = 'en_attente'"
                );

                $requete->execute([
                    "statut" => $decision,
                    "id" => $idProjet
                ]);

                if ($requete->rowCount() > 0) {
                    $message = $decision === "publie"
                        ? "Le projet a été publié dans la galerie."
                        : "La proposition a été refusée.";

                    $typeMessage = "succes";
                } else {
                    $message = "Cette proposition n'est plus en attente ou n'existe pas.";
                    $typeMessage = "erreur";
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $message = "Une erreur est survenue lors du traitement.";
                $typeMessage = "erreur";
            }
        }
    }
}

// Récupérer les propositions en attente
$requete = $connexion->prepare(
    "SELECT id, titre, description, etudiant, annee, promotion,
            specialite, type_livrable, contexte, objectifs,
            outils_technologies, lien_projet
     FROM projets
     WHERE statut = 'en_attente'
     ORDER BY id ASC"
);

$requete->execute();
$propositions = $requete->fetchAll(PDO::FETCH_ASSOC);

function echapper($valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Propositions à valider - Administration NWS</title>

    <link rel="stylesheet" href="admin.css?v=17">
    <link rel="stylesheet" href="../style-proposition.css?v=2">
</head>

<body>
    <main class="propositions-conteneur">
        <a class="proposition-retour" href="index.php">
            ← Retour au tableau de bord
        </a>

        <h1>Propositions à valider</h1>
        <p>
            Consulte les projets proposés par les étudiants avant de décider
            de leur publication.
        </p>

        <?php if ($message !== ""): ?>
            <div class="proposition-message <?= echapper($typeMessage) ?>">
                <?= echapper($message) ?>
            </div>
        <?php endif; ?>

        <p>
            <strong><?= count($propositions) ?></strong>
            proposition(s) en attente de validation.
        </p>

        <?php if (empty($propositions)): ?>
            <div class="propositions-vide">
                <h2>Aucune proposition en attente</h2>
                <p>Les nouvelles propositions apparaîtront ici.</p>
            </div>
        <?php else: ?>

            <?php foreach ($propositions as $projet): ?>
                <article class="proposition-carte">
                    <h2><?= echapper($projet["titre"]) ?></h2>

                    <p class="proposition-description">
                        <?= echapper($projet["description"]) ?>
                    </p>

                    <div class="proposition-infos">
                        <p><strong>Étudiant / groupe :</strong><br>
                            <?= echapper($projet["etudiant"]) ?></p>

                        <p><strong>Année :</strong><br>
                            <?= echapper($projet["annee"]) ?></p>

                        <p><strong>Promotion :</strong><br>
                            <?= echapper($projet["promotion"]) ?></p>

                        <p><strong>Spécialité :</strong><br>
                            <?= echapper($projet["specialite"]) ?></p>

                        <p><strong>Type de livrable :</strong><br>
                            <?= echapper($projet["type_livrable"]) ?></p>

                        <p><strong>Outils et technologies :</strong><br>
                            <?= nl2br(echapper($projet["outils_technologies"])) ?></p>
                    </div>

                    <?php if (!empty($projet["contexte"])): ?>
                        <h3>Contexte</h3>
                        <p class="proposition-description">
                            <?= echapper($projet["contexte"]) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($projet["objectifs"])): ?>
                        <h3>Objectifs</h3>
                        <p class="proposition-description">
                            <?= echapper($projet["objectifs"]) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($projet["lien_projet"])): ?>
                        <p>
                            <strong>Lien du projet :</strong>
                            <a href="<?= echapper($projet["lien_projet"]) ?>"
                               target="_blank" rel="noopener noreferrer">
                                Ouvrir le lien
                            </a>
                        </p>
                    <?php endif; ?>

                    <div class="proposition-actions">
                        <form method="POST"
                              onsubmit="return confirm('Confirmer la publication de ce projet ?');">
                            <input type="hidden" name="csrf_token"
                                   value="<?= echapper($_SESSION["csrf_token"]) ?>">
                            <input type="hidden" name="projet_id"
                                   value="<?= (int) $projet["id"] ?>">
                            <input type="hidden" name="decision" value="publie">

                            <button class="bouton-publier" type="submit">
                                Publier le projet
                            </button>
                        </form>

                        <form method="POST"
                              onsubmit="return confirm('Confirmer le refus de cette proposition ?');">
                            <input type="hidden" name="csrf_token"
                                   value="<?= echapper($_SESSION["csrf_token"]) ?>">
                            <input type="hidden" name="projet_id"
                                   value="<?= (int) $projet["id"] ?>">
                            <input type="hidden" name="decision" value="refuse">

                            <button class="bouton-refuser" type="submit">
                                Refuser la proposition
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>

        <?php endif; ?>
    </main>
</body>
</html>
