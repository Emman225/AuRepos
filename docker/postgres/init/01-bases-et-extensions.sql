-- Exécuté une seule fois, à la création du volume.
-- btree_gist : indispensable à la contrainte d'exclusion qui interdit
-- deux séjours sur le même logement la même nuit (tâche P1-RES-01).
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- Base réservée aux tests automatisés : jamais la base de développement.
CREATE DATABASE residences_test OWNER residences;
\connect residences_test
CREATE EXTENSION IF NOT EXISTS btree_gist;
