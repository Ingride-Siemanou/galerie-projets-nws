
# Galerie des projets étudiants NWS

Application web permettant de consulter les projets réalisés par les étudiants de Normandie Web School.

## Objectif

Présenter les réalisations des étudiants dans une galerie accessible et responsive.

## Fonctionnalités

- Affichage des projets étudiants
- Recherche par titre
- Filtrage par spécialité
- Filtrage par année
- Affichage responsive sur ordinateur et téléphone
- Listes déroulantes personnalisées

## Technologies utilisées

- HTML5
- CSS3
- JavaScript
- PHP 8.3
- MariaDB 11.4
- Docker
- phpMyAdmin

## Installation

1. Cloner le dépôt GitHub.
2. Créer un fichier `.env` à partir des variables nécessaires.
3. Démarrer les conteneurs :

```bash
docker compose up -d --build
```

4. Ouvrir http://localhost:8080.

**Remarque :** une base MariaDB contenant la table `projets` est nécessaire. Le script d'initialisation sera ajouté prochainement.

## Projet pédagogique

Projet réalisé dans le cadre d'un apprentissage du développement web.
