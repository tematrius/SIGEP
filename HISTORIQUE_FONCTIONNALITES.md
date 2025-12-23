# Historique des Fonctionnalités - SIGEP

**Dernière mise à jour:** 23 décembre 2024

---

## 📌 Version 1.0 - Fonctionnalités de Base

### 1. Localisation par Province (RDC)
- Intégration des 26 provinces de la RDC
- Sélection de la province lors de la création/modification de projet
- Affichage de la province dans les détails du projet

### 2. Validation des Tâches avec Documents Justificatifs
- Upload de documents (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP, RAR)
- Taille maximale : 10 MB par fichier
- Validation des tâches avec justificatifs obligatoires
- Téléchargement des documents uploadés

---

## 📌 Version 1.3 - Timeline et Analyses

**Date:** 19 décembre 2025

### 1. Timeline de Projet
**Fichier:** `public/project_timeline.php`

**Fonctionnalités:**
- Affichage chronologique de tous les événements du projet
- Types d'événements trackés :
  - Création du projet
  - Création et complétion de tâches
  - Upload de documents
  - Identification de risques
  - Ajout de commentaires
  - Mises à jour du projet
- Timeline interactive avec icônes et couleurs
- Liens directs vers les détails des éléments

### 2. Analyses et Statistiques Avancées
**Fichier:** `public/analytics.php`

**Graphiques disponibles (Chart.js):**
- Projets par statut (Doughnut)
- Tâches par statut (Pie)
- Top 10 des localisations (Bar)
- Top 10 des budgets (Bar)
- Évolution mensuelle des projets (Line)
- Taux de complétion mensuel (Line)
- Risques par niveau (Doughnut)
- Documents par type (Pie)

**Tableau de performance:**
- Total de tâches assignées par utilisateur
- Tâches terminées
- Taux de complétion en %
- Barre de progression visuelle

**Autres:**
- Fonction d'impression pour rapports

---

## 📌 Version 1.4 - Système de Jalons

**Date:** 21 décembre 2025

### Gestion des Jalons (Milestones)
Définition et suivi des étapes clés du projet avec livrables et échéances.

#### Création de Jalons
**Fichier:** `public/milestone_create.php`

**Champs:**
- Titre (obligatoire)
- Description
- Date d'échéance (obligatoire, doit être dans la période du projet)
- Ordre d'affichage
- Livrables attendus

**Notifications:**
- Notification au chef de projet
- Log d'activité

#### Modification de Jalons
**Fichier:** `public/milestone_edit.php`

**Statuts:**
- `pending` - En attente
- `in_progress` - En cours
- `completed` - Complété (date auto)
- `delayed` - En retard

#### Suppression de Jalons
**Fichier:** `public/milestone_delete.php`
- Confirmation obligatoire
- Suppression définitive

#### Intégration
- Section jalons sur la page détails du projet
- Affichage dans la timeline
- Badges colorés par statut
- Indicateur de retard

**Base de données:**
Table `project_milestones` créée avec `database/create_milestones.sql`

---

## 📌 Version 1.5 - Diagramme de Gantt

**Date:** 22 décembre 2025

### 1. Diagramme de Gantt Interactif
**Fichier:** `public/project_gantt.php`

**Fonctionnalités:**
- Affichage graphique de toutes les tâches et jalons
- Barres colorées selon le statut
- Lignes de dépendance entre tâches
- Mode responsive

**Vues disponibles:**
- Vue Jour (granularité fine)
- Vue Semaine (par défaut)
- Vue Mois (planification stratégique)

**Codes couleur:**
- 🟢 Vert : Tâche terminée
- 🔵 Bleu : Tâche en cours
- 🟡 Jaune : Tâche en attente
- 🔴 Rouge : Tâche en retard
- ⚫ Gris : Jalon (milestone)

**Interactivité:**
- Clic sur tâche : popup avec détails
- Export PNG haute résolution
- Filtres : afficher/masquer jalons
- Navigation vers le projet

### 2. Gestion des Dépendances de Tâches
**Fichier:** `public/task_dependencies.php`

**Fonctionnalités:**
- Définition des dépendances entre tâches
- Types de dépendances :
  - Finish-to-Start (FS) : La tâche B commence après la fin de A
  - Start-to-Start (SS) : La tâche B commence en même temps que A
  - Finish-to-Finish (FF) : La tâche B finit en même temps que A
  - Start-to-Finish (SF) : La tâche B finit quand A commence
- Visualisation des dépendances sur le Gantt
- Suppression de dépendances

**Base de données:**
Table `task_dependencies` créée avec `database/create_task_dependencies.sql`

**Bibliothèque:**
- DHTMLX Gantt 8.0.6 (JavaScript)

---

## 📌 Version 1.6 - Import en Masse

**Date:** 22 décembre 2025

### Système d'Import Excel/CSV
**Fichier:** `public/import.php`

**Import de Projets:**
Champs supportés :
- `title` (obligatoire)
- `description`
- `context`
- `status` : prevu, en_cours, suspendu, termine, annule
- `priority` : low, medium, high
- `start_date` (YYYY-MM-DD)
- `end_date` (YYYY-MM-DD)
- `budget_estimated` (FC)
- `budget_validated` (FC)
- `location_province` (code province)

**Import de Tâches:**
Champs supportés :
- `project_id` (obligatoire)
- `title` (obligatoire)
- `description`
- `status` : pending, in_progress, completed, blocked
- `priority` : low, medium, high
- `start_date` (YYYY-MM-DD)
- `due_date` (YYYY-MM-DD)
- `assigned_to` (ID utilisateur)

**Fonctionnalités:**
- Templates téléchargeables avec exemples
- Validation des données avant insertion
- Historique des imports (table `import_logs`)
- Support Excel (.xlsx, .xls) et CSV
- Gestion des erreurs avec détails

**Fichiers:**
- `public/import_template.php` : génération de templates
- `public/import_process.php` : traitement des imports
- `database/create_import_logs.sql` : table historique

**Bibliothèque:**
- PhpSpreadsheet pour lecture Excel/CSV

---

## 📌 Version 1.7 - Carte Géographique

**Date:** 23 décembre 2024

### Carte Interactive des Projets
**Fichier:** `public/project_map.php`

**Caractéristiques:**
- Carte interactive de la RDC avec Leaflet.js 1.9.4
- Fond de carte : OpenStreetMap
- Centrage : RDC (-4.0383, 21.7587)
- Zoom : niveaux 5 à 18
- Mode plein écran

**Marqueurs:**
- Couleurs selon le statut :
  - 🔴 Rouge : En attente
  - 🟡 Jaune : En cours
  - 🟢 Vert : Terminé
  - ⚫ Gris : Annulé
- Clustering automatique des marqueurs proches
- Popup informatif au clic

**Filtres:**
1. Par statut (Tous, En attente, En cours, Terminé, Annulé)
2. Par priorité (Toutes, Basse, Moyenne, Haute, Critique)
3. Par province (26 provinces de la RDC)

**Panneau de statistiques:**
- Nombre total de projets
- Projets visibles sur la carte
- Budget total des projets visibles
- Répartition par statut (graphique)

**Base de données:**
- Table `locations` avec coordonnées GPS
  - Colonnes : id, name, type (province/territoire/ville), latitude, longitude, parent_id
- Script `database/update_locations_coordinates.sql` pour ajouter coordonnées

**Bibliothèques:**
- Leaflet.js 1.9.4
- Leaflet.markercluster
- Leaflet.fullscreen
- Chart.js pour statistiques

---

## 📌 Version 1.8 - Système d'Archivage

**Date:** 23 décembre 2024

### 1. Archivage de Projets
**Fichier:** `public/project_archive.php`

**Caractéristiques:**
- Archivage des projets terminés ou annulés uniquement
- Raison d'archivage (optionnelle)
- Traçabilité : utilisateur, date, raison
- Confirmation avant archivage
- Notifications automatiques

**Processus:**
1. Bouton "Archiver" sur projets terminés/annulés
2. Page de confirmation avec info projet
3. Saisie de la raison d'archivage
4. Validation avec horodatage
5. Notification aux utilisateurs concernés

### 2. Page des Archives
**Fichier:** `public/archives.php`

**Fonctionnalités:**
- Liste complète des projets archivés
- Recherche par nom/description
- Filtres multiples :
  - Par statut (Tous, Terminé, Annulé)
  - Par province
  - Par date d'archivage
- Tri personnalisable :
  - Date d'archivage
  - Nom du projet
  - Budget
  - Date de fin
- Pagination (10 projets par page)

**Statistiques:**
- Total des projets archivés
- Nombre de projets terminés
- Nombre de projets annulés
- Budget total archivé

### 3. Restauration de Projets
**Fichier:** `public/project_restore.php`

**Processus:**
1. Bouton "Restaurer" sur projets archivés
2. Affichage de l'info d'archivage (qui, quand, pourquoi)
3. Confirmation de restauration
4. Projet redevient actif
5. Notification de restauration

### 4. Intégration Interface
- Menu "Archives" dans la navigation principale
- Badge avec nombre de projets archivés
- Bouton "Archiver" sur pages détails projets éligibles
- Bouton "Restaurer" sur liste archives
- Icônes Font Awesome appropriées

**Base de données:**
Script : `database/create_archive_system.sql`

**Modifications table `projects`:**
- `archived` BOOLEAN (défaut FALSE)
- `archived_at` TIMESTAMP NULL
- `archived_by` INT NULL (FK vers users)
- `archive_reason` TEXT NULL

**Index:**
- idx_archived sur colonne archived
- idx_archived_at sur colonne archived_at

**Vues SQL:**
- `active_projects` : projets non archivés
- `archived_projects` : projets archivés

**Procédures stockées:**
- `archive_project(project_id, user_id, reason)`
- `restore_project(project_id)`

---

## 📊 Résumé des Fichiers Créés

### Pages PHP (public/)
1. `project_timeline.php` - Timeline du projet (v1.3)
2. `analytics.php` - Analyses et statistiques (v1.3)
3. `milestone_create.php` - Création de jalons (v1.4)
4. `milestone_edit.php` - Modification de jalons (v1.4)
5. `milestone_delete.php` - Suppression de jalons (v1.4)
6. `project_gantt.php` - Diagramme de Gantt (v1.5)
7. `task_dependencies.php` - Gestion dépendances (v1.5)
8. `import.php` - Interface d'import (v1.6)
9. `import_template.php` - Génération templates (v1.6)
10. `import_process.php` - Traitement imports (v1.6)
11. `project_map.php` - Carte géographique (v1.7)
12. `project_archive.php` - Archivage projet (v1.8)
13. `project_restore.php` - Restauration projet (v1.8)
14. `archives.php` - Liste des archives (v1.8)

### Scripts SQL (database/)
1. `create_milestones.sql` - Table jalons (v1.4)
2. `create_task_dependencies.sql` - Table dépendances (v1.5)
3. `create_import_logs.sql` - Table imports (v1.6)
4. `update_locations_coordinates.sql` - Coordonnées GPS (v1.7)
5. `create_archive_system.sql` - Système archivage (v1.8)

### Bibliothèques Externes
- Chart.js (v1.3) - Graphiques
- DHTMLX Gantt 8.0.6 (v1.5) - Diagramme Gantt
- PhpSpreadsheet (v1.6) - Import Excel/CSV
- Leaflet.js 1.9.4 (v1.7) - Cartes interactives
- Leaflet.markercluster (v1.7) - Regroupement marqueurs
- Leaflet.fullscreen (v1.7) - Mode plein écran

---

## 🔧 Instructions d'Installation Complète

### 1. Base de données
```sql
-- Dans l'ordre :
source database/create_milestones.sql
source database/create_task_dependencies.sql
source database/create_import_logs.sql
source database/update_locations_coordinates.sql
source database/create_archive_system.sql
```

### 2. Permissions
Vérifier que le dossier `uploads/` est accessible en écriture.

### 3. Bibliothèques PHP
Installer via Composer :
```bash
composer require phpoffice/phpspreadsheet
```

### 4. Vérification
Accéder aux pages :
- `/public/project_timeline.php?id=1`
- `/public/analytics.php`
- `/public/project_gantt.php?id=1`
- `/public/import.php`
- `/public/project_map.php`
- `/public/archives.php`

---

## 📝 Notes Importantes

### Conventions de colonnes
- **Utilisateurs** : colonne `full_name` (pas `name`)
- **Projets** : colonne `budget_validated` (pas `budget`)
- **Projets** : colonne `created_by` (pas `user_id`)

### Localisation
- 26 provinces RDC dans table `locations`
- Type ENUM : 'province', 'territoire', 'ville', 'autre'
- Coordonnées GPS : latitude DECIMAL(10,8), longitude DECIMAL(11,8)

### Sécurité
- Toutes les pages vérifient l'authentification
- Permissions pour archivage : admin ou gestionnaire
- Validation des données à l'import
- Protection CSRF sur formulaires

### Performance
- Index sur colonnes fréquemment interrogées
- Pagination sur listes longues
- Clustering des marqueurs sur carte
- Vues SQL pour requêtes courantes

---

**Fin du document**
