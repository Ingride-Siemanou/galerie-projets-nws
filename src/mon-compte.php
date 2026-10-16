<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION["utilisateur_id"])) {
    header("Location: connexion-utilisateur.php");
    exit;
}

require_once __DIR__ . "/connexion.php";

$message = "";
$erreur = "";

$requete = $connexion->prepare(
    "SELECT id, nom, prenom, email, mot_de_passe
     FROM utilisateurs
     WHERE id = :id
     LIMIT 1"
);
$requete->execute([
    "id" => (int) $_SESSION["utilisateur_id"]
]);
$utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    $_SESSION = [];
    session_destroy();
    header("Location: connexion-utilisateur.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $ancienMotDePasse = $_POST["ancien_mot_de_passe"] ?? "";
    $nouveauMotDePasse = $_POST["nouveau_mot_de_passe"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";

    if ($nom === "" || $prenom === "") {
        $erreur = "Le nom et le prénom sont obligatoires.";
    } elseif (
        $nouveauMotDePasse !== "" &&
        !password_verify($ancienMotDePasse, $utilisateur["mot_de_passe"])
    ) {
        $erreur = "Ton ancien mot de passe est incorrect.";
    } elseif (
        $nouveauMotDePasse !== "" &&
        strlen($nouveauMotDePasse) < 12
    ) {
        $erreur = "Le nouveau mot de passe doit contenir au moins 12 caractères.";
    } elseif (
        $nouveauMotDePasse !== "" &&
        $nouveauMotDePasse !== $confirmation
    ) {
        $erreur = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        if ($nouveauMotDePasse !== "") {
            $motDePasseHache = password_hash(
                $nouveauMotDePasse,
                PASSWORD_DEFAULT
            );

            $miseAJour = $connexion->prepare(
                "UPDATE utilisateurs
                 SET nom = :nom,
                     prenom = :prenom,
                     mot_de_passe = :mot_de_passe
                 WHERE id = :id"
            );

            $miseAJour->execute([
                "nom" => $nom,
                "prenom" => $prenom,
                "mot_de_passe" => $motDePasseHache,
                "id" => (int) $utilisateur["id"]
            ]);
        } else {
            $miseAJour = $connexion->prepare(
                "UPDATE utilisateurs
                 SET nom = :nom, prenom = :prenom
                 WHERE id = :id"
            );

            $miseAJour->execute([
                "nom" => $nom,
                "prenom" => $prenom,
                "id" => (int) $utilisateur["id"]
            ]);
        }

        $_SESSION["utilisateur_nom"] = $nom;
        $_SESSION["utilisateur_prenom"] = $prenom;

        header("Location: mon-compte.php?modifie=1");
        exit;
    }

    $utilisateur["nom"] = $nom;
    $utilisateur["prenom"] = $prenom;
}

if (isset($_GET["modifie"]) && $_GET["modifie"] === "1") {
    $message = "Ton compte a bien été mis à jour.";

    $requete->execute([
        "id" => (int) $_SESSION["utilisateur_id"]
    ]);
    $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon compte - Galerie NWS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="formulaire-inscription">
    <h1>Mon compte</h1>
    <p>Consulte et modifie tes informations personnelles.</p>

    <?php if ($message !== ""): ?>
        <p role="status"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <?php if ($erreur !== ""): ?>
        <p role="alert"><?= htmlspecialchars($erreur, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <p>
        <strong>E-mail :</strong>
        <?= htmlspecialchars($utilisateur["email"], ENT_QUOTES, "UTF-8") ?>
    </p>

    <form method="post" action="mon-compte.php">
        <label for="nom">Nom</label>
        <input
            type="text"
            id="nom"
            name="nom"
            maxlength="100"
            required
            value="<?= htmlspecialchars($utilisateur["nom"], ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="prenom">Prénom</label>
        <input
            type="text"
            id="prenom"
            name="prenom"
            maxlength="100"
            required
            value="<?= htmlspecialchars($utilisateur["prenom"], ENT_QUOTES, "UTF-8") ?>"
        >

        <h2>Modifier le mot de passe</h2>
        <p>Laisse ces champs vides si tu ne souhaites pas le modifier.</p>

        <label for="ancien_mot_de_passe">Ancien mot de passe</label>
        <input
            type="password"
            id="ancien_mot_de_passe"
            name="ancien_mot_de_passe"
            autocomplete="current-password"
        >

        <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
        <input
            type="password"
            id="nouveau_mot_de_passe"
            name="nouveau_mot_de_passe"
            minlength="12"
            autocomplete="new-password"
        >

        <label for="confirmation">Confirmer le nouveau mot de passe</label>
        <input
            type="password"
            id="confirmation"
            name="confirmation"
            minlength="12"
            autocomplete="new-password"
        >

        <button type="submit">Enregistrer les modifications</button>
    </form>

    <p><a href="index.php">Retour à la galerie</a></p>
</main>

</body>
</html>
