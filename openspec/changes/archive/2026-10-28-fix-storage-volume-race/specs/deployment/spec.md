## MODIFIED Requirements

### Requirement: Données conservées entre les déploiements
La base PostgreSQL et les fichiers de l'application (photos à venir) SHALL vivre dans des volumes nommés. Redéployer, changer d'image ou recréer les conteneurs MUST NOT perdre de donnée. Le web et le worker voient les mêmes fichiers. Le lancement de la stack sur un volume de stockage neuf MUST réussir à chaque essai, quel que soit l'ordre dans lequel les conteneurs sont créés.

#### Scenario: Redéploiement
- **GIVEN** la base contient des sessions et un fichier a été écrit dans le stockage de l'application
- **WHEN** l'opérateur redéploie la stack sur une nouvelle image
- **THEN** les sessions sont toujours ouvertes et le fichier est toujours lisible

#### Scenario: Fichier écrit par le worker
- **WHEN** le worker écrit un fichier dans le stockage de l'application
- **THEN** le web lit ce fichier au même chemin

#### Scenario: Lancement sur un volume de stockage neuf
- **GIVEN** aucun volume de stockage n'existe pour la stack
- **WHEN** l'opérateur lance `docker compose up --wait` cinq fois de suite, en retirant la stack et ses volumes entre deux essais
- **THEN** chaque lancement rend la main avec `pgsql`, `web`, `worker` et `backup` sains et `migrate` terminé avec le code 0, et le stockage de l'application contient les dossiers `public` et `private`, où le web et le worker peuvent écrire

#### Scenario: Redéploiement sur un volume de stockage existant
- **GIVEN** le volume de stockage contient le fichier `public/keep.txt` écrit par la version précédente, et pas le fichier `.gitignore` livré avec l'image
- **WHEN** l'opérateur redéploie la stack sur une nouvelle image
- **THEN** `public/keep.txt` est toujours lisible avec le même contenu, et aucun fichier de l'image n'est ajouté au volume
