# SIGEP - Système de Gestion, Planification et Suivi des Projets Ministériels

## 📋 Vue d'ensemble

SIGEP est une plateforme web complète pour la gestion, la planification et le suivi des projets stratégiques ministériels. Développée en PHP natif avec MySQL, elle offre une solution robuste et évolutive pour la gouvernance des projets publics.

## ✨ Fonctionnalités principales

### 🔐 Authentification et Sécurité
- Système de connexion sécurisé
- Gestion des rôles et permissions (RBAC)
- Sessions sécurisées
- Logs d'activité complets

### 📁 Gestion des Projets
- CRUD complet des projets
- Statuts multiples (Prévu, En cours, Suspendu, Terminé, Annulé)
- Suivi de progression en temps réel
- Association géographique

### ✅ Gestion des Tâches
- Création de tâches et sous-tâches
- Gestion des dépendances
- Affectation des responsables
- Priorités configurables
- Suivi d'avancement

### 👥 Parties Prenantes
- Identification des stakeholders
- Matrice influence/intérêt
- Historique des interactions

### ⚠️ Gestion des Risques
- Identification et évaluation
- Matrice probabilité/impact
- Plans de mitigation
- Suivi des risques résiduels

### 💰 Gestion Budgétaire
- Budget par projet et tâche
- Suivi des dépenses réelles
- Alertes de dépassement
- Rapports d'écart

### 📊 Tableaux de Bord
- Vue d'ensemble statistique
- Graphiques interactifs (Chart.js)
- KPIs en temps réel
- Alertes et notifications

### 📄 Gestion Documentaire
- Upload de documents
- Versioning
- Contrôle d'accès
- Classification par projet/tâche

### 🔔 Notifications
- Alertes automatiques
- Rappels d'échéances
- Notifications en temps réel

### 📈 Rapports
- Génération de rapports
- Exports PDF/Excel
- Statistiques avancées

## 🛠️ Technologies utilisées

- **Backend**: PHP 8.0+
- **Base de données**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5
- **Graphiques**: Chart.js
- **Icônes**: Font Awesome 6
- **Serveur**: Apache (XAMPP)

## 📦 Structure du projet

```
SIGEP/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   └── images/
├── config/
│   ├── config.php
│   └── database.php
├── database/
│   └── schema.sql
├── includes/
│   └── get_notifications.php
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── projects.php
│   ├── project_create.php
│   └── ...
├── uploads/
├── views/
│   ├── layouts/
│   │   └── main.php
│   ├── auth/
│   ├── dashboard/
│   ├── projects/
│   └── tasks/
├── cahier_des_charges.md
├── INSTALLATION.md
└── README.md
```

## 🚀 Installation

Consultez le fichier [INSTALLATION.md](INSTALLATION.md) pour les instructions détaillées.

### Installation rapide

1. **Démarrer XAMPP** (Apache + MySQL)

2. **Créer la base de données**:
   ```sql
   CREATE DATABASE sigep_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Importer le schéma**:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root sigep_db < database\schema.sql
   ```

4. **Accéder à l'application**:
   ```
   http://localhost/SIGEP/public/
   ```

5. **Se connecter** avec les identifiants par défaut:
   - **Utilisateur**: `admin`
   - **Mot de passe**: `admin123`

## 👥 Rôles utilisateurs

Le système supporte 7 rôles prédéfinis:

1. **Ministre** - Accès complet et vision globale
2. **Directeur de Cabinet** - Gestion et supervision
3. **Secrétaire Général** - Coordination administrative
4. **Chef de Projet** - Gestion des projets assignés
5. **Responsable Technique** - Exécution technique
6. **Partenaire Externe** - Consultation et collaboration
7. **Observateur** - Consultation en lecture seule

## 🗄️ Base de données

La base contient 25+ tables pour gérer:
- Utilisateurs et rôles
- Projets et tâches
- Risques et mitigations
- Budget et dépenses
- Parties prenantes
- Documents
- Notifications
- Logs d'activité
- KPIs et rapports

## 🎨 Interface utilisateur

- Design moderne et responsive
- Compatible mobile, tablette et desktop
- Thème Bootstrap personnalisé
- Graphiques interactifs
- Notifications en temps réel

## 🔒 Sécurité

- Hashage des mots de passe (bcrypt)
- Protection CSRF
- Protection XSS
- Requêtes préparées (PDO)
- Contrôle d'accès basé sur les rôles
- Journalisation complète

## 📱 Compatibilité

- ✅ Chrome (dernières versions)
- ✅ Firefox (dernières versions)
- ✅ Edge (dernières versions)
- ✅ Safari 14+

## 🤝 Contribution

Ce projet est développé pour le Ministère. Pour toute contribution ou suggestion:
1. Créer une branche pour votre fonctionnalité
2. Commiter vos changements
3. Soumettre une pull request

## 📝 License

© 2025 SIGEP - Tous droits réservés

## 📞 Support

Pour toute question ou problème technique, contactez l'administrateur système.

---

**Version**: 1.0.0  
**Date**: Décembre 2025  
**Développé pour**: Ministère de l'Élégance
