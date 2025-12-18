# Nouvelles Fonctionnalités - SIGEP

## 1. Localisation par Province (RDC)

### Description
Le système intègre maintenant les 26 provinces de la République Démocratique du Congo pour la localisation des projets.

### Provinces disponibles
1. Kinshasa
2. Kongo Central
3. Kwango
4. Kwilu
5. Mai-Ndombe
6. Kasaï
7. Kasaï-Central
8. Kasaï-Oriental
9. Lomami
10. Sankuru
11. Maniema
12. Sud-Kivu
13. Nord-Kivu
14. Ituri
15. Haut-Uélé
16. Tshopo
17. Bas-Uélé
18. Nord-Ubangi
19. Mongala
20. Tshuapa
21. Équateur
22. Sud-Ubangi
23. Lualaba
24. Haut-Lomami
25. Haut-Katanga
26. Tanganyika

### Utilisation
- Lors de la création ou modification d'un projet, vous pouvez sélectionner la province dans la liste déroulante "Localisation"
- La province sélectionnée s'affichera dans les détails du projet

---

## 2. Validation des Tâches avec Documents Justificatifs

### Description
Les utilisateurs assignés à une tâche peuvent maintenant marquer leurs tâches comme terminées en fournissant des documents justificatifs.

### Fonctionnalités

#### Upload de Documents
- **Formats acceptés** : PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP, RAR
- **Taille maximale** : 10 MB par fichier
- **Champs** :
  - Document (fichier obligatoire)
  - Description (optionnel)

#### Processus de Validation
1. L'utilisateur assigné accède aux détails de la tâche
2. Il voit une section "Validation de la Tâche" (si la tâche n'est pas terminée)
3. Il uploade au moins un document justificatif
4. Une fois le document uploadé, le bouton "Marquer la tâche comme terminée" devient disponible
5. En cliquant sur ce bouton :
   - Le statut de la tâche passe à "Terminée"
   - La progression passe à 100%
   - Une notification est envoyée au chef de projet et aux administrateurs

#### Visualisation des Documents
- Tous les documents uploadés sont listés dans la section "Documents Justificatifs"
- Pour chaque document, on voit :
  - Le nom du fichier
  - La description (si fournie)
  - L'utilisateur qui l'a uploadé
  - La date et l'heure d'upload
  - La taille du fichier
  - Un bouton "Télécharger" pour récupérer le document

### Notifications
Les notifications sont automatiquement envoyées :
- Quand un document est uploadé
- Quand une tâche est marquée comme terminée
- Aux chefs de projet, administrateurs et directeurs concernés

### Sécurité
- Seul l'utilisateur assigné à la tâche peut uploader des documents et valider
- Les gestionnaires de projet (avec permission `manage_all_projects`) peuvent également valider
- Les fichiers sont stockés de manière sécurisée dans `uploads/task_documents/`
- Validation des types de fichiers et de la taille

---

## 3. Système de Notifications Corrigé

### Description
Le compteur de notifications dans la barre de navigation fonctionne maintenant correctement.

### Fonctionnalités
- **Badge rouge** : Affiche le nombre de notifications non lues
- **Mise à jour automatique** : Toutes les 30 secondes
- **Visibilité** : Le badge disparaît quand il n'y a aucune notification non lue

### Types de Notifications
- Création de projet
- Assignation de tâche
- Upload de document justificatif
- Validation de tâche
- Modification importante

### Accès aux Notifications
- Cliquez sur l'icône de cloche dans la barre de navigation
- Consultez la page "Notifications" pour voir tous les détails
- Marquez les notifications comme lues individuellement ou toutes ensemble

---

## Fichiers Créés/Modifiés

### Nouveaux Fichiers
1. `database/insert_provinces.sql` - Script SQL pour insérer les 26 provinces
2. `database/create_task_documents.sql` - Structure de la table des documents
3. `public/task_upload_document.php` - Gestion de l'upload de documents
4. `public/task_mark_complete.php` - Marquage des tâches comme terminées
5. `uploads/task_documents/` - Dossier de stockage des documents

### Fichiers Modifiés
1. `public/task_details.php` - Ajout des sections validation et documents
2. `assets/js/app.js` - Correction du chargement des notifications
3. `includes/get_notifications.php` - Déjà fonctionnel (aucune modification)

---

## Instructions de Mise à Jour

### 1. Base de Données
Exécutez les scripts SQL suivants dans l'ordre :

```bash
# Insérer les provinces
mysql -u root sigep_db < database/insert_provinces.sql

# Créer la table des documents (si pas déjà fait)
mysql -u root sigep_db < database/create_task_documents.sql
```

### 2. Permissions de Fichiers
Assurez-vous que le dossier uploads a les bonnes permissions :
```bash
chmod -R 777 uploads/
```

### 3. Test
1. Connectez-vous au système
2. Vérifiez que le compteur de notifications s'affiche
3. Créez un projet et sélectionnez une province
4. Créez une tâche et assignez-la à un utilisateur
5. Connectez-vous en tant que cet utilisateur
6. Uploadez un document justificatif
7. Marquez la tâche comme terminée
8. Vérifiez les notifications

---

## Support

Pour toute question ou problème, consultez le cahier des charges ou contactez l'équipe de développement.

**Version** : 1.1  
**Date** : <?php echo date('d/m/Y'); ?>
