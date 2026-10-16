<?php
session_start();

require_once __DIR__ . "/connexion.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Si un compte correspond à cette adresse, tu recevras les instructions nécessaires.";
    } else {
        try {
            $requete = $connexion->prepare(
                "SELECT id FROM utilisateurs WHERE email = :email LIMIT 1"
            );
            $requete->execute(["email" => $email]);
            $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

            if ($utilisateur) {
                // Générer un jeton secret aléatoire.
                $jeton = bin2hex(random_bytes(32));
                $jetonHash = hash("sha256", $jeton);

                // Supprimer les anciennes demandes de cet utilisateur.
                $suppression = $connexion->prepare(
                    "DELETE FROM reinitialisations_mot_de_passe
                     WHERE utilisateur_id = :id"
                );
                $suppression->execute([
                    "id" => (int) $utilisateur["id"]
                ]);

                // Le jeton expire après 30 minutes.
                $expiration = date(
                    "Y-m-d H:i:s",
                    time() + 1800
                );

                // Enregistrer uniquement l'empreinte du jeton.
                $insertion = $connexion->prepare(
                    "INSERT INTO reinitialisations_mot_de_passe
                     (utilisateur_id, jeton_hash, expiration)
                     VALUES (:id, :jeton_hash, :expiration)"
                );

                $insertion->execute([
                    "id" => (int) $utilisateur["id"],
                    "jeton_hash" => $jetonHash,
                    "expiration" => $expiration
                ]);

                /*
                 * L'envoi de l'e-mail de réinitialisation
                 * reste à mettre en place.
                 */
            }

            // Même message si l'adresse existe ou non.
            $message = "Si un compte correspond à cette adresse, tu recevras les instructions nécessaires.";

        } catch (Throwable $e) {
            error_log($e->getMessage());
            $message = "Une erreur est survenue. Réessaie plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Mot de passe oublié - Galerie NWS</title>

    <link rel="stylesheet" href="style.css">
</head>
<body>

<a href="index.php" class="lien-logo">
    <img
        src="images/logo-nws.jpeg"
        alt="Normandie Web School"
        class="logo-nws"
    >
</a>

<main class="formulaire-inscription">

    <h1>Mot de passe oublié</h1>

    <p>
        Saisis l'adresse e-mail utilisée pour ton compte administrateur.
    </p>

    <?php if ($message !== ""): ?>
        <p role="status">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="mot-de-passe-oublie.php">

        <label for="email">Adresse e-mail</label>

        <input
            type="email"
            id="email"
            name="email"
            autocomplete="email"
            maxlength="190"
            required
        >

        <button type="submit">
            Demander la réinitialisation
        </button>

    </form>

    <p>
        <a href="connexion-utilisateur.php">
            Retour à la connexion administrateur
        </a>
    </p>

    <p>
        <a href="index.php">Retour à la galerie</a>
    </p>

</main>

</body>
</html>
