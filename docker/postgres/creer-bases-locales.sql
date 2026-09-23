-- À lancer UNE FOIS sur le PostgreSQL du poste, avec le compte postgres :
--   psql -U postgres -h 127.0.0.1 -f docker/postgres/creer-bases-locales.sql
-- Crée un compte réservé au projet : l'application ne se connecte jamais en postgres.
-- Mot de passe de développement local uniquement ; la recette et la production ont les leurs.

CREATE ROLE residences LOGIN PASSWORD 'residences';

CREATE DATABASE residences OWNER residences ENCODING 'UTF8';
CREATE DATABASE residences_test OWNER residences ENCODING 'UTF8';

-- btree_gist : indispensable à la contrainte d'exclusion qui interdit
-- deux séjours sur le même logement la même nuit (tâche P1-RES-01).
-- Une extension se crée en superutilisateur, d'où sa place ici et non dans une migration.
\connect residences
CREATE EXTENSION IF NOT EXISTS btree_gist;

\connect residences_test
CREATE EXTENSION IF NOT EXISTS btree_gist;
