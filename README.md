# Galerie des projets étudiants NWS

## Présentation du projet

La **Galerie des projets étudiants NWS** est une application web permettant de présenter et de consulter les projets réalisés par les étudiants de la Normandie Web School.

L'objectif est de valoriser les réalisations étudiantes à travers une galerie accessible au public, avec des fonctionnalités de recherche et de filtrage. Une interface d'administration permet de gérer les projets.

## Fonctionnalités

### Espace public

- Consultation des projets étudiants.
- Recherche de projets.
- Filtrage par spécialité, année, promotion et type de livrable.
- Tri des projets.
- Mise en avant de certains projets.
- Accès aux détails des projets.

### Espace administrateur

- Connexion à l'espace d'administration.
- Gestion des projets étudiants.
- Création de projets.
- Gestion des catégories et des informations associées aux projets.
- Formulaire de mot de passe oublié.

**Remarque :** la réinitialisation complète du mot de passe par e-mail peut nécessiter une configuration supplémentaire.

## Technologies utilisées

- PHP 8.3 : développement côté serveur.
- MariaDB 11.4 : base de données.
- HTML5 : structure des pages.
- CSS3 : mise en forme et design.
- Docker et Docker Compose : environnement de développement.
- phpMyAdmin : gestion de la base de données.

## Structure du projet

```text
galerie-nws/
├── src/
│   ├── admin/
│   ├── images/
│   ├── index.php
│   ├── connexion.php
│   ├── connexion-utilisateur.php
│   ├── creation-admin.php
│   ├── deconnexion.php
│   ├── mot-de-passe-oublie.php
│   ├── proposer-projet.php
│   └── style.css
├── docker-compose.yml
└── README.md
```

La structure ci-dessus est indicative et doit être adaptée aux fichiers réellement présents dans le dépôt.

## Installation et lancement

### 1. Prérequis

Installer les outils suivants :

- Docker Desktop
- Docker Compose
- Git

### 2. Récupérer le projet

```bash
git clone URL_DU_DEPOT
cd galerie-nws
```

Remplacer `URL_DU_DEPOT` par l'adresse du dépôt GitHub.

### 3. Démarrer l'application

À la racine du projet, exécuter la commande suivante :

```bash
docker compose up -d
```

### 4. Accéder à l'application

Une fois les conteneurs démarrés :

- Galerie publique : http://localhost:8080
- Connexion administrateur : http://localhost:8080/connexion-utilisateur.php
- Mot de passe oublié : http://localhost:8080/mot-de-passe-oublie.php
- phpMyAdmin : http://localhost:8081

Ces adresses sont destinées à l'environnement local et ne sont pas accessibles directement depuis Internet.

## Base de données

L'application utilise MariaDB pour stocker les informations des projets, des catégories, des utilisateurs et des demandes de réinitialisation de mot de passe.

Principales tables :

- `projets`
- `fichiers_projets`
- `categories`
- `projet_categories`
- `utilisateurs`
- `reinitialisations_mot_de_passe`

La configuration de la base de données doit correspondre aux paramètres définis dans l'environnement Docker et dans le fichier de connexion PHP.

## Sécurité

- Les mots de passe doivent être stockés sous forme de hachage.
- L'espace d'administration doit être réservé aux utilisateurs disposant du rôle administrateur.
- Les pages d'administration doivent vérifier les droits d'accès côté serveur.
- Les identifiants de base de données ne doivent pas être publiés sur GitHub.
- Les fichiers contenant des mots de passe, des clés ou des paramètres privés doivent être exclus du dépôt.

## Objectif pédagogique

Ce projet permet de mettre en pratique le développement web avec PHP, la gestion d'une base de données relationnelle, l'authentification, les interfaces d'administration et la mise en place d'un environnement Docker.

## Informations sur le projet

- **Nom du projet :** Galerie des projets étudiants NWS
- **Établissement :** Normandie Web School
- **Type :** Application web de présentation et de gestion de projets étudiants

