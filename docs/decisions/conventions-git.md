# Conventions Git

- Branche `main` : toujours livrable. Travail sur `lot1/<identifiant-tache>-<sujet>` (ex. `lot1/P1-API-02-connexion-jwt`), fusion par demande de fusion une fois la CI verte.
- Message de commit : `P1-API-02 : connexion JWT, rafraîchissement et déconnexion` — l'identifiant de la tâche du plan en tête, puis ce qui change, en français.
- Un commit ne contient jamais de secret, d'APK ni d'archive ; la CI le vérifie.
- Une tâche se coche dans `PLAN-REALISATION.md` dans le même commit que le code qui la termine.
