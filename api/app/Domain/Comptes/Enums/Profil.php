<?php

namespace App\Domain\Comptes\Enums;

/**
 * Les douze profils du cahier des charges (§ 3). Un compte = un profil,
 * et le profil décide de ce que le compte peut atteindre.
 *
 * Mon Gravier comparait partout un entier (`type_user_id in [1, 2]`) ;
 * ici le profil porte lui-même ses règles, et le code qui les lit se relit.
 */
enum Profil: string
{
    case SuperAdministrateur = 'super_administrateur';
    case Administrateur = 'administrateur';
    case Gestionnaire = 'gestionnaire';
    case Gouvernante = 'gouvernante';
    case AgentAssistance = 'agent_assistance';
    case Proprietaire = 'proprietaire';
    case AgentTerrain = 'agent_terrain';
    case Chauffeur = 'chauffeur';
    case Livreur = 'livreur';
    case Restaurateur = 'restaurateur';
    case Apporteur = 'apporteur';
    case Client = 'client';

    public function libelle(): string
    {
        return match ($this) {
            self::SuperAdministrateur => 'Super administrateur',
            self::Administrateur => 'Administrateur',
            self::Gestionnaire => 'Gestionnaire',
            self::Gouvernante => 'Gouvernante',
            self::AgentAssistance => 'Agent d’assistance',
            self::Proprietaire => 'Propriétaire',
            self::AgentTerrain => 'Agent de terrain',
            self::Chauffeur => 'Chauffeur',
            self::Livreur => 'Livreur',
            self::Restaurateur => 'Restaurateur partenaire',
            self::Apporteur => 'Apporteur d’affaires',
            self::Client => 'Client',
        };
    }

    /**
     * L'espace où le compte atterrit après connexion : le web et le mobile
     * s'en servent pour rediriger, le serveur pour cloisonner les routes.
     */
    public function espace(): string
    {
        return match ($this) {
            self::SuperAdministrateur, self::Administrateur, self::Gestionnaire, self::Gouvernante => 'backoffice',
            self::AgentAssistance => 'assistance',
            self::Proprietaire => 'proprietaire',
            self::AgentTerrain => 'agent',
            self::Chauffeur => 'chauffeur',
            self::Livreur => 'livreur',
            self::Restaurateur => 'restaurateur',
            self::Apporteur => 'apporteur',
            self::Client => 'client',
        };
    }

    /** Seuls ces deux profils valident un encaissement, un tarif ou une publication. */
    public function estAdministrateur(): bool
    {
        return in_array($this, [self::SuperAdministrateur, self::Administrateur], true);
    }

    /** Personnel de l'entreprise : compte créé par un administrateur, identifiant généré (CdC § 9.5). */
    public function estPersonnel(): bool
    {
        return in_array($this, [
            self::SuperAdministrateur, self::Administrateur, self::Gestionnaire,
            self::Gouvernante, self::AgentAssistance,
        ], true);
    }

    /** Partenaires payés par la plateforme : leurs reversements subissent la retenue à la source. */
    public function estPartenaire(): bool
    {
        return in_array($this, [
            self::Proprietaire, self::AgentTerrain, self::Chauffeur,
            self::Livreur, self::Restaurateur, self::Apporteur,
        ], true);
    }

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
