<?php

namespace App\Domain\Comptes\Services;

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Mail\BienvenuePersonnelMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Création des comptes du personnel — administrateurs, gestionnaires, gouvernantes,
 * agents d'assistance (CdC § 9.5). Comme pour les partenaires, aucun mot de passe
 * n'est choisi ni transmis par qui que ce soit : le compte le définit lui-même par
 * « Mot de passe oublié ». Ce qui change ici, c'est l'IDENTIFIANT : généré par la
 * plateforme et envoyé par courriel (CdC § 6.8), pas l'adresse elle-même.
 */
final class ComptesDuPersonnel
{
    /**
     * @param  array<string, mixed>  $saisie  nom, prenoms, email, telephone, profil, agence_id?, residences?
     */
    public function creer(User $auteur, array $saisie): User
    {
        $profil = Profil::from($saisie['profil']);
        $this->controlerLeDroitDeCreer($auteur, $profil);

        if (in_array($profil, [Profil::Administrateur, Profil::Gestionnaire], true) && blank($saisie['agence_id'] ?? null)) {
            throw new ErreurMetier('Un '.mb_strtolower($profil->libelle()).' doit être rattaché à une agence pour pouvoir encaisser.', 'agence_obligatoire', 422);
        }

        $identifiant = $this->genererUnIdentifiant($saisie['nom'], $saisie['prenoms'] ?? null);

        $utilisateur = DB::transaction(function () use ($saisie, $profil, $identifiant): User {
            $utilisateur = User::create([
                'nom' => $saisie['nom'],
                'prenoms' => $saisie['prenoms'] ?? null,
                'email' => mb_strtolower($saisie['email']),
                'telephone' => $saisie['telephone'] ?? null,
                'identifiant' => $identifiant,
                // Mot de passe aléatoire que personne ne connaît : le compte le remplacera lui-même.
                'password' => Str::password(40),
                'profil' => $profil,
                'statut' => StatutCompte::Actif,
                'agence_id' => $saisie['agence_id'] ?? null,
            ]);
            $utilisateur->forceFill(['email_verified_at' => now()])->saveQuietly();

            // « Un gestionnaire ne voit que ses résidences » (CdC § 9.5) : le rattachement se fait ici.
            if ($profil === Profil::Gestionnaire && ! empty($saisie['residences'])) {
                $ids = Residence::query()->whereIn('id', $saisie['residences'])->pluck('id');
                $utilisateur->residences()->sync($ids);
            }

            return $utilisateur;
        });

        try {
            Mail::to($utilisateur->email)->send(new BienvenuePersonnelMail($utilisateur));
        } catch (Throwable $e) {
            // Un envoi manqué ne bloque jamais l'opération (CdC § 13.2).
            Log::error('Courriel de bienvenue (personnel) non envoyé', ['user_id' => $utilisateur->id, 'erreur' => $e->getMessage()]);
        }

        return $utilisateur->load('agence', 'residences');
    }

    /** @param array<int, int> $residences */
    public function rattacherAuxResidences(User $gestionnaire, array $residences): User
    {
        if ($gestionnaire->profil !== Profil::Gestionnaire) {
            throw new ErreurMetier('Seul un gestionnaire se rattache à des résidences.', 'profil_sans_residences', 422);
        }

        $ids = Residence::query()->whereIn('id', $residences)->pluck('id');
        $gestionnaire->residences()->sync($ids);

        return $gestionnaire->refresh()->load('residences');
    }

    private function controlerLeDroitDeCreer(User $auteur, Profil $profil): void
    {
        if (! $profil->estPersonnel()) {
            throw new ErreurMetier('Ce profil n’est pas un profil du personnel.', 'profil_non_personnel', 422);
        }

        // Un administrateur ne crée pas de compte à privilège égal ou supérieur au sien.
        $privilegie = in_array($profil, [Profil::SuperAdministrateur, Profil::Administrateur], true);
        if ($privilegie && $auteur->profil !== Profil::SuperAdministrateur) {
            throw new ErreurMetier('Seul un super administrateur peut créer un compte administrateur.', 'privilege_insuffisant', 403);
        }
    }

    /** Prénom + nom, en minuscules et sans accent ; un suffixe numérique départage les doublons. */
    private function genererUnIdentifiant(string $nom, ?string $prenoms): string
    {
        $base = Str::slug(mb_substr((string) $prenoms, 0, 1).$nom, '');
        $base = $base !== '' ? $base : 'personnel';

        $identifiant = $base;
        $suffixe = 1;
        while (User::query()->where('identifiant', $identifiant)->exists()) {
            $suffixe++;
            $identifiant = $base.$suffixe;
        }

        return $identifiant;
    }
}
