# Résidences meublées — DALAKOUN

Plateforme de gestion de résidences meublées : site public, back office, espaces partenaires et applications mobiles.
Le suivi du projet est dans [PLAN-REALISATION.md](PLAN-REALISATION.md) ; le besoin dans `Cahier-des-charges-Residences-meublees.docx`.

| Dossier | Contenu | Pile |
|---|---|---|
| `api/` | API REST unique (`/api/v1`), toute la logique métier | Laravel 13, PHP 8.3, PostgreSQL, JWT |
| `web/` | Site public, back office, espaces partenaires | React 19, TypeScript, Vite, Ant Design |
| `mobile/` | Paquets partagés `core`, `api_client`, `ui` + une application par profil | Flutter 3.38, Riverpod, dio |
| `docs/` | Décisions, recettes, manuels | — |

Règle d'or : **prix, taxes et disponibilité se calculent uniquement dans `api/`**. Le web et le mobile envoient des intentions et affichent ce que le serveur renvoie.

## Démarrer en local

### 1. Base de données (une seule fois)

Avec le PostgreSQL du poste — le mot de passe du compte `postgres` est demandé :

```bash
psql -U postgres -h 127.0.0.1 -f docker/postgres/creer-bases-locales.sql
```

Ou avec Docker (port 5433, à reporter dans `api/.env`) : `docker compose up -d`.

### 2. API

```bash
cd api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve          # http://localhost:8000/api/v1/etat
```

### 3. Front

```bash
cd web
npm install
cp .env.example .env
npm run dev                # http://localhost:5173
```

### 4. Mobile

```bash
cd mobile
flutter pub get
cd apps/client && flutter gen-l10n
flutter run --dart-define=ENV=dev
```

## Vérifier avant de livrer

| Projet | Commandes |
|---|---|
| `api/` | `vendor/bin/pint --test` · `vendor/bin/phpstan analyse` · `vendor/bin/pest` |
| `web/` | `npm run lint` · `npm test` · `npm run build` |
| `mobile/` | `dart analyze .` · `flutter test` dans chaque paquet |

Les tests de l'API tournent sur la base `residences_test`, jamais sur la base de développement.

## Suivi d'avancement

Cocher la tâche dans `PLAN-REALISATION.md`, puis recalculer le tableau :

```bash
pwsh -File ./maj-avancement.ps1
```
