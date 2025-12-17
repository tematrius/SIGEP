# CAHIER DES CHARGES

## SYSTÈME DE GESTION, PLANIFICATION ET SUIVI DES PROJETS MINISTÉRIELS

---

## 1. CONTEXTE ET JUSTIFICATION

Dans le cadre de la modernisation de l’administration publique et de l’amélioration de la gouvernance, le Ministère de l’Élégance souhaite se doter d’un **système informatique centralisé** permettant la **gestion, la planification et le suivi des projets stratégiques** issus des engagements ministériels.

Actuellement, la gestion des projets est souvent fragmentée, peu traçable et difficilement mesurable, ce qui entraîne :

* Un manque de visibilité sur l’état réel des projets
* Des retards non détectés à temps
* Une coordination inefficace entre les acteurs
* Une difficulté à rendre compte de l’exécution des engagements

Le présent projet vise à concevoir et développer une **plateforme numérique intégrée** permettant de répondre à ces problématiques.

---

## 2. OBJECTIFS DU PROJET

### 2.1 Objectif général

Mettre en place une plateforme web permettant au cabinet du ministère de **concevoir, planifier, suivre et évaluer** l’exécution des projets publics, depuis leur création jusqu’à leur clôture.

### 2.2 Objectifs spécifiques

* Centraliser l’ensemble des projets du ministère
* Assurer un suivi en temps réel de l’avancement des projets
* Faciliter la coordination entre les différents acteurs
* Renforcer la transparence et la traçabilité des actions
* Produire des rapports fiables pour la prise de décision
* Anticiper les risques, retards et blocages

---

## 3. PÉRIMÈTRE DU SYSTÈME

Le système couvre :

* Les projets initiés par le ministre, le cabinet ou l’administration
* Les projets nationaux, provinciaux ou locaux
* Les projets financés par l’État ou par des partenaires
* Les projets d’infrastructures, de services ou de réformes

---

## 4. UTILISATEURS ET RÔLES

### 4.1 Types d’utilisateurs

* Ministre
* Directeur de cabinet
* Secrétaire général
* Chef de projet
* Responsable technique
* Partenaire externe
* Observateur / auditeur

### 4.2 Gestion des rôles et permissions

Chaque utilisateur dispose de droits spécifiques :

* Création / modification / validation de projets
* Consultation uniquement
* Validation des étapes
* Téléversement de documents
* Génération de rapports

---

## 5. FONCTIONNALITÉS DÉTAILLÉES

### 5.1 Authentification et sécurité

* Connexion sécurisée par identifiant et mot de passe
* Gestion des rôles et permissions
* Journalisation des connexions
* Protection contre les accès non autorisés

---

### 5.2 Gestion des projets

Fonctionnalités :

* Création d’un projet
* Modification et suppression (selon permissions)
* Attribution des responsables
* Définition des objectifs
* Définition des dates clés
* Gestion du budget
* Statut du projet :

  * Prévu
  * En cours
  * Suspendu
  * Terminé
  * Annulé

Champs principaux :

* Titre du projet
* Description
* Contexte (mission, engagement, décret, promesse)
* Localisation géographique
* Budget estimé / validé
* Date de début
* Date de fin prévue
* Statut global

---

### 5.3 Gestion des tâches et sous-tâches

Fonctionnalités :

* Création de tâches liées à un projet
* Découpage en sous-tâches
* Affectation des responsables
* Définition des priorités
* Dépendances entre tâches
* Suivi de l’avancement (%)

Chaque tâche comprend :

* Intitulé
* Description
* Responsable
* Date de début
* Date de fin
* Statut
* Avancement
* Observations

---

### 5.12 Suivi et indicateurs de performance (KPIs)

Le système doit proposer :

* Avancement global du projet
* Avancement par tâche
* Tâches en retard
* Projets à risque
* Comparaison prévu / réalisé
* Alertes automatiques en cas de retard ou blocage

Indicateurs clés de performance (KPIs) :

* Taux d'achèvement des projets
* Délai moyen d'exécution
* Taux de respect des délais
* Taux d'exécution budgétaire
* Nombre de risques identifiés/mitigés
* Satisfaction des parties prenantes
* Taux d'utilisation des ressources
* Performance par responsable

Tableaux de bord avec seuils configurables et code couleur (vert, orange, rouge)

---

### 5.13 Gestion documentaire

Fonctionnalités :

* Téléversement de documents
* Classement par projet ou tâche
* Versioning des documents
* Accès contrôlé selon les rôles
* Aperçu en ligne
* Recherche dans les documents
* Tags et métadonnées

Types de documents :

* Rapports de mission
* Contrats
* Photos de terrain
* Rapports d'avancement
* Notes administratives
* Cahiers des charges
* Procès-verbaux
* Factures et justificatifs

---

### 5.14 Historique et traçabilité

Le système doit conserver :

* Historique des actions utilisateurs
* Modifications apportées aux projets et tâches
* Dates et auteurs des modifications
* Logs d'authentification
* Actions de validation/rejet
* Exports de données
* Suppressions et restaurations

---

### 5.15 Tableaux de bord et reporting

Fonctionnalités :

* Tableau de bord global
* Tableaux de bord par rôle
* Statistiques graphiques (graphiques à barres, camemberts, lignes)
* Génération de rapports PDF / Excel
* Rapports automatisés programmables
* Rapports personnalisables
* Envoi automatique de rapports périodiques

Indicateurs :

* Nombre de projets par statut
* Projets par province
* Taux d'exécution
* Projets en retard
* Performance budgétaire
* Risques actifs
* Ressources utilisées

Types de rapports :

* Rapport d'avancement projet
* Rapport consolidé ministériel
* Rapport budgétaire
* Rapport de risques
* Rapport de performance
* Rapport d'audit

---

### 5.16 Gestion géographique

* Association des projets à des zones géographiques
* Visualisation sur carte interactive (Google Maps / OpenStreetMap)
* Filtrage par province, ville ou territoire
* Marqueurs cliquables avec infos projet
* Couches cartographiques multiples
* Statistiques géographiques

---

### 5.17 Imports et Exports

Fonctionnalités :

* Import de projets depuis Excel/CSV
* Import de tâches en masse
* Export de données (projets, tâches, rapports)
* Formats supportés : Excel, CSV, PDF, JSON
* Templates d'import fournis
* Validation des données à l'import

---

### 5.18 Archivage et historique

Fonctionnalités :

* Archivage automatique des projets terminés
* Conservation configurable (5, 10, 15 ans)
* Accès en lecture seule aux projets archivés
* Recherche dans les archives
* Restauration de projets archivés
* Conformité légale de conservation

---

### 5.19 Multilinguisme

Fonctionnalités :

* Interface disponible en français
* Support de langues additionnelles (anglais, autres)
* Traduction des contenus statiques
* Sélection de langue par utilisateur

---

## 6. EXIGENCES TECHNIQUES

### 6.1 Architecture

* Application web modulaire
* Architecture MVC
* API REST pour évolutivité future
* Design responsive (desktop, tablette, mobile)
* Progressive Web App (PWA) - optionnel

### 6.2 Technologies envisagées

* Backend : PHP (Laravel 10+)
* Frontend : HTML5, CSS3, JavaScript (ES6+)
* Framework UI : Bootstrap 5 ou Tailwind CSS
* Bibliothèques JS : Chart.js, FullCalendar, DHTMLX Gantt
* Base de données : MySQL 8.0+
* Serveur : Apache 2.4+ ou Nginx
* Cache : Redis (recommandé)

### 6.3 Exigences de performance

* Temps de chargement des pages : < 2 secondes
* Support de 100+ utilisateurs simultanés
* Disponibilité : 99,5% (uptime)
* Temps de réponse API : < 500ms
* Sauvegarde automatique toutes les 24h

### 6.4 Compatibilité navigateurs

* Chrome (dernières versions)
* Firefox (dernières versions)
* Edge (dernières versions)
* Safari 14+ 

---

## 7. BASE DE DONNÉES (ENTITÉS PRINCIPALES)

* users
* roles
* permissions
* role_permissions
* projects
* tasks
* subtasks
* task_dependencies
* documents
* budgets
* budget_items
* expenses
* locations
* activity_logs
* stakeholders
* stakeholder_project
* risks
* risk_mitigations
* resources
* resource_allocations
* milestones
* validations
* notifications
* comments
* messages
* kpis
* reports
* archives

---

## 8. SÉCURITÉ ET CONFORMITÉ

### 8.1 Sécurité

* Contrôle d'accès strict basé sur les rôles (RBAC)
* Authentification sécurisée (hash bcrypt)
* Protection CSRF et XSS
* Validation et sanitisation des entrées
* Sessions sécurisées avec timeout
* Journalisation complète des actions sensibles
* Protection contre les injections SQL
* Rate limiting sur les APIs
* Certificat SSL/TLS obligatoire

### 8.2 Sauvegarde et récupération

* Sauvegardes automatiques quotidiennes
* Sauvegarde incrémentielle
* Stockage des sauvegardes sur site distant
* Plan de reprise d'activité (PRA)
* Tests de restauration trimestriels

### 8.3 Conformité

* Protection des données personnelles
* Politique de confidentialité
* Conformité aux standards gouvernementaux
* Audit trail complet

---

## 9. TESTS ET RECETTE

### 9.1 Types de tests

* Tests unitaires
* Tests d'intégration
* Tests fonctionnels
* Tests de performance
* Tests de sécurité
* Tests d'acceptance utilisateur (UAT)

### 9.2 Critères d'acceptation

* Couverture de tests : > 80%
* Absence de bugs critiques
* Validation de toutes les fonctionnalités majeures
* Performance conforme aux exigences
* Documentation complète

### 9.3 Processus de recette

* Recette technique (développeur)
* Recette fonctionnelle (chef de projet)
* Recette utilisateur (utilisateurs finaux)
* Validation finale par le comité de pilotage
* Période de garantie : 6 mois après mise en production

---

## 10. FORMATION ET ACCOMPAGNEMENT

### 10.1 Formation des utilisateurs

* Formation des administrateurs (2 jours)
* Formation des chefs de projet (1 jour)
* Formation des utilisateurs finaux (0,5 jour)
* Supports de formation (vidéos, manuels)
* Sessions de formation en présentiel et distanciel

### 10.2 Support technique

* Hotline/helpdesk pendant 12 mois
* Documentation en ligne
* FAQ et base de connaissances
* Webinaires de mise à niveau
* Support par email et téléphone

---

## 11. ÉVOLUTIVITÉ ET PERSPECTIVES

Le système devra être conçu pour :

* Intégration future avec d'autres ministères
* Développement d'une application mobile native
* Migration vers d'autres technologies si nécessaire
* Interopérabilité avec des systèmes tiers
* Montée en charge (scalabilité horizontale)
* Modules additionnels (gestion des marchés publics, etc.)

---

## 12. LIVRABLES ATTENDUS

### 12.1 Livrables techniques

* Application web fonctionnelle et testée
* Code source commenté et versionné (Git)
* Base de données structurée avec schéma
* Scripts de migration et seeds
* API REST documentée (Swagger/Postman)

### 12.2 Documentation

* Documentation technique complète
* Manuel d'installation et de déploiement
* Manuel administrateur
* Manuel utilisateur (par rôle)
* Cahier de maintenance
* Documentation de l'API
* Diagrammes (UML, ERD, flux)

### 12.3 Formation et support

* Supports de formation (PPT, PDF, vidéos)
* Sessions de formation réalisées
* Guide de dépannage

---

## 13. CONTRAINTES DU PROJET

### 13.1 Contraintes temporelles

* Durée totale du projet : 6-9 mois
* Phase d'analyse et conception : 1 mois
* Phase de développement : 4-6 mois
* Phase de tests et recette : 1 mois
* Phase de déploiement et formation : 1 mois

### 13.2 Contraintes budgétaires

* Budget estimé : À définir selon le marché
* Modalités de paiement :
  * 30% au démarrage
  * 40% à la livraison version beta
  * 30% à la recette finale

### 13.3 Contraintes organisationnelles

* Disponibilité des utilisateurs pour tests
* Accès aux infrastructures existantes
* Formation du personnel
* Migration des données existantes

---

## 14. MODALITÉS CONTRACTUELLES

### 14.1 Propriété intellectuelle

* Le code source appartient au Ministère
* Licence open-source ou propriétaire (à définir)

### 14.2 Garantie et maintenance

* Garantie : 6 mois après mise en production
* Correction des bugs sans frais supplémentaires
* Maintenance évolutive : contrat annuel (optionnel)

### 14.3 Pénalités et bonus

* Pénalités de retard : 0,5% du montant par semaine de retard (max 10%)
* Bonus de livraison anticipée : négociable

### 14.4 Conditions de résiliation

* Résiliation possible avec préavis de 30 jours
* Remise de tous les livrables en état

---

## 15. COMITÉ DE PILOTAGE ET SUIVI

### 15.1 Composition du comité

* Directeur de cabinet (président)
* Secrétaire général
* Responsable IT du ministère
* Chef de projet côté prestataire
* Représentant des utilisateurs finaux

### 15.2 Réunions de suivi

* Réunion de lancement
* Points hebdomadaires (phase développement)
* Comités de pilotage mensuels
* Réunion de clôture

### 15.3 Indicateurs de suivi du projet

* Avancement vs planning
* Budget consommé vs prévisionnel
* Nombre de fonctionnalités livrées
* Taux de bugs identifiés/résolus

---

## 16. CONCLUSION

Le présent cahier des charges constitue la base de conception et de développement d'un **outil stratégique de gouvernance publique**, visant à améliorer l'efficacité, la transparence et la performance de l'action ministérielle.

Ce système de gestion de projets permettra au Ministère de :

* **Centraliser** l'information projet
* **Suivre** en temps réel l'avancement
* **Anticiper** les risques et retards
* **Optimiser** l'allocation des ressources
* **Rendre compte** de manière transparente
* **Décider** sur base de données fiables

La réussite du projet repose sur une collaboration étroite entre le ministère et le prestataire, ainsi que sur l'engagement de tous les acteurs concernés.