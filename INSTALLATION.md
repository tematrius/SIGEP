# GUIDE D'INSTALLATION - SIGEP

## Prérequis
- XAMPP installé (Apache + MySQL + PHP 8.0+)
- Navigateur web moderne

## Étapes d'installation

### 1. Démarrer les services XAMPP
1. Ouvrir le panneau de contrôle XAMPP
2. Démarrer **Apache**
3. Démarrer **MySQL**

### 2. Créer la base de données
1. Ouvrir phpMyAdmin: `http://localhost/phpmyadmin`
2. Cliquer sur "Nouvelle base de données"
3. Nom de la base: `sigep_db`
4. Interclassement: `utf8mb4_unicode_ci`
5. Cliquer sur "Créer"

### 3. Importer le schéma
1. Dans phpMyAdmin, sélectionner la base `sigep_db`
2. Cliquer sur l'onglet "SQL"
3. Copier tout le contenu du fichier `database/schema.sql`
4. Coller dans la zone de texte
5. Cliquer sur "Exécuter"

**OU** utiliser la ligne de commande:
```powershell
cd c:\xampp\htdocs\SIGEP
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
```

### 4. Configuration
Vérifier les paramètres dans `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sigep_db');
```

### 5. Permissions
S'assurer que le dossier `uploads` est accessible en écriture.

### 6. Accès à l'application

**URL**: `http://localhost/SIGEP/public/`

**Identifiants par défaut**:
- **Utilisateur**: `admin`
- **Mot de passe**: `admin123`

### 7. Premier accès
1. Se connecter avec les identifiants ci-dessus
2. Changer le mot de passe administrateur
3. Créer les utilisateurs nécessaires
4. Configurer les rôles et permissions

## Structure de la base de données

La base contient 25+ tables:
- `users` - Utilisateurs du système
- `roles` - Rôles (Ministre, Directeur, Chef de projet, etc.)
- `permissions` - Permissions granulaires
- `projects` - Projets
- `tasks` - Tâches
- `risks` - Risques
- `stakeholders` - Parties prenantes
- `budget_items` - Lignes budgétaires
- `documents` - Documents
- `notifications` - Notifications
- etc.

## Fonctionnalités principales

✅ **Authentification sécurisée**
✅ **Gestion des projets** (CRUD complet)
✅ **Gestion des tâches** avec dépendances
✅ **Tableau de bord** avec statistiques
✅ **Gestion des risques**
✅ **Suivi budgétaire**
✅ **Gestion des parties prenantes**
✅ **Système de notifications**
✅ **Logs d'activité**
✅ **Rapports et graphiques**

## Dépannage

### Erreur de connexion à la base
- Vérifier que MySQL est démarré dans XAMPP
- Vérifier les identifiants dans `config/database.php`

### Page blanche
- Activer l'affichage des erreurs PHP dans `php.ini`
- Vérifier les logs Apache: `C:\xampp\apache\logs\error.log`

### Problème de session
- Vérifier les permissions du dossier `C:\xampp\tmp`

## Support technique
Pour toute question, consulter la documentation ou contacter l'administrateur système.
