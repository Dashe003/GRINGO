# GRINGO - My Wardrobe. My Style.

Application web de gestion de garde-robe multi-utilisateur pour PHP 8.2+, MySQL/MariaDB et XAMPP.

## Installation

1. Placer `Gringo` dans `C:\xampp\htdocs\Gringo`.
2. Demarrer Apache et MySQL dans XAMPP.
3. Importer `database/gringo_db.sql` dans phpMyAdmin. Le script cree `gringo_db` et ses tables de reference.
4. Verifier `config/database.php` (configuration XAMPP locale par defaut : root sans mot de passe).
5. Ouvrir `http://localhost/Gringo/` puis creer un compte.

Extensions PHP : `pdo_mysql`, `fileinfo` et `mbstring`. Les sauvegardes TAR.GZ utilisent `PharData` et zlib. Activer les extensions requises dans `C:\xampp\php\php.ini`, puis redemarrer Apache.

## Modules

- Compte, profil, avatar, preferences et categories personnelles.
- Garde-robe avec galerie photo, recherche, filtres multicriteres, tri, grille/liste, pagination, favoris, etat du linge et archivage/restauration.
- Composition, copie, archivage/restauration, favoris et historique des tenues.
- Suggestions locales de tenues et assistant d'associations de couleurs.
- Calendrier des tenues, collections, liste de souhaits, depenses et budgets multi-devises, valises et listes a emporter.
- Recherche globale, notifications, statistiques et tableau de bord alimentes par les donnees MySQL.
- Sauvegarde/restauration par compte, avec photos, archive privee sous `storage/backups`.

Les conversions monetaires ne sont jamais faites automatiquement. Les taux saisis sont conserves avec leur date. Les suggestions de couleurs sont indicatives.

## Mise a niveau d'une base existante

Pour une ancienne base GRINGO, importer dans l'ordre `database/migrations/001_complete_modules.sql`, `002_user_categories.sql`, `003_clothing_formality.sql` puis `004_ratings.sql`. Pour une installation neuve, le schéma complet est déjà dans `database/gringo_db.sql`; ne pas rejouer les migrations ensuite.

## Securite et structure

Les mots de passe sont haches, les sessions regenerent leur identifiant a la connexion, les ecritures utilisent CSRF et les operations filtrent les donnees par `user_id`. Les images sont limitees a 5 Mo et validees par type MIME reel. L'acces direct aux sauvegardes et aux fichiers de configuration est bloque par `.htaccess`.

- `index.php` : routeur.
- `app/controllers` : traitement et autorisation.
- `app/views` : vues PHP.
- `database/gringo_db.sql` : schema complet et donnees de reference.
- `public/assets` : styles et interactions.
- `storage/uploads` : photos televersees.
- `storage/backups` : archives privees par utilisateur.
