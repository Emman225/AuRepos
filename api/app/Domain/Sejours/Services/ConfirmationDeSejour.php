<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Services\Missions;
use App\Domain\Notifications\Enums\CanalNotification;
use App\Domain\Notifications\Enums\ModeleDeMessage;
use App\Domain\Notifications\Services\Notificateur;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\BonDeMiseADispositionMail;
use App\Mail\SejourConfirmeMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Confirmer une réservation (CdC § 6.1) : vérifier que le paiement le permet, vérifier la
 * disponibilité, affecter l'agent d'accueil, valider. La plateforme crée alors le bon de mise
 * à disposition, notifie le propriétaire et envoie au client son code d'arrivée, l'adresse et
 * les consignes. Crée aussi la mission de ménage « avant arrivée » (P2-MEN-01, CdC § 6.4).
 */
final class ConfirmationDeSejour
{
    public const CODE_D_ARRIVEE = 'arrivee';

    public function __construct(
        private readonly CycleDuSejour $cycle,
        private readonly Calendrier $calendrier,
        private readonly SoldeDesSejours $soldes,
        private readonly CodesSecrets $codes,
        private readonly Notificateur $notificateur,
        private readonly Missions $missions,
    ) {}

    /**
     * Ce qui empêche de confirmer, en clair — pour l'écran « Réservations en attente ».
     *
     * @return list<string>
     */
    public function obstacles(Sejour $sejour): array
    {
        $solde = $this->soldes->de($sejour);
        $residence = $sejour->logement->residence;

        return array_values(array_filter([
            $sejour->etat === EtatDuSejour::Demande ? null : 'Ce séjour est déjà « '.$sejour->etat->libelle().' ».',
            // Seul un règlement EFFECTUÉ compte : une tranche saisie mais non finalisée ne confirme rien.
            $sejour->mode_reglement === 'a_terme' || $solde['acompte_atteint'] ? null : sprintf(
                'L’acompte de %s F n’est pas encaissé (%s F effectués%s).',
                number_format($sejour->acompte_exige, 0, ',', ' '), number_format($solde['encaisse'], 0, ',', ' '),
                $solde['en_cours'] > 0 ? ', '.number_format($solde['en_cours'], 0, ',', ' ').' F encore dans le circuit de preuve' : '',
            ),
            $residence->active && $residence->disponibilite === Disponibilite::Disponible ? null : 'La résidence est fermée (« Occupée ») ou désactivée.',
        ]));
    }

    public function confirmer(Sejour $sejour, User $auteur, ?int $agentAccueilId = null): Sejour
    {
        $obstacles = $this->obstacles($sejour);
        if ($obstacles !== []) {
            throw new ErreurMetier(implode(' ', $obstacles), 'confirmation_impossible', 422);
        }

        if ($agentAccueilId !== null && ! User::query()->whereKey($agentAccueilId)->where('profil', Profil::AgentTerrain)->exists()) {
            throw new ErreurMetier('L’agent d’accueil doit être un agent de terrain.', 'agent_invalide', 422);
        }

        $code = DB::transaction(function () use ($sejour, $auteur, $agentAccueilId): string {
            // La disponibilité est revérifiée par la base : l'occupation est (re)posée dans cette transaction.
            $this->calendrier->occuper($sejour);

            $this->cycle->passer($sejour, EtatDuSejour::Confirme, $auteur, [
                'confirme_le' => now(), 'confirme_par' => $auteur->id, 'agent_accueil_id' => $agentAccueilId,
                'expire_le' => null, // un séjour confirmé n'expire plus
            ]);

            $this->creerLeBon($sejour);
            $this->missions->creerAvantArrivee($sejour);

            return $this->codes->generer($sejour, self::CODE_D_ARRIVEE);
        });

        // Après la transaction : un envoi manqué ne défait JAMAIS la confirmation (CdC § 13.2).
        $sejour->load(['client', 'logement.residence.proprietaire.utilisateur', 'logement.residence.quartier.commune']);
        if ($sejour->client !== null) {
            $this->envoyer($sejour->client->email, new SejourConfirmeMail($sejour, $code), 'confirmation au client');
            $this->codes->noterLEnvoi($sejour, self::CODE_D_ARRIVEE);
            // SMS et WhatsApp, en plus du courriel riche ci-dessus : jamais le code secret, juste
            // un renvoi vers l'espace client où il se lit (CdC § 6.7 ; § 11 sur les codes).
            $this->notifierParSmsEtWhatsapp($sejour);
        }
        $proprietaire = $sejour->logement->residence->proprietaire;
        if (! $proprietaire->interne) {
            $this->envoyer($proprietaire->utilisateur->email, new BonDeMiseADispositionMail($sejour), 'bon au propriétaire');
        }

        return $sejour->refresh();
    }

    /** Le gestionnaire RENVOIE le code au client, sans jamais le voir (repris de Mon Gravier). */
    public function renvoyerLeCode(Sejour $sejour): void
    {
        $code = $this->codes->lirePourLeClient($sejour, self::CODE_D_ARRIVEE);
        if ($code === null || $sejour->client === null || ! in_array($sejour->etat, [EtatDuSejour::Confirme], true)) {
            throw new ErreurMetier('Il n’y a pas de code d’arrivée à renvoyer pour ce séjour.', 'code_absent', 422);
        }

        $sejour->load(['client', 'logement.residence.quartier.commune']);
        $this->envoyer($sejour->client->email, new SejourConfirmeMail($sejour, $code), 'renvoi du code');
        $this->codes->noterLEnvoi($sejour, self::CODE_D_ARRIVEE);
    }

    /** Code verrouillé ou compromis : un administrateur en émet un nouveau, envoyé au client. L'ancien ne vaut plus rien. */
    public function emettreUnNouveauCode(Sejour $sejour): void
    {
        if ($sejour->etat !== EtatDuSejour::Confirme || $sejour->client === null) {
            throw new ErreurMetier('Un nouveau code ne s’émet que pour un séjour confirmé.', 'code_absent', 422);
        }

        $code = $this->codes->generer($sejour, self::CODE_D_ARRIVEE);
        $sejour->load(['client', 'logement.residence.quartier.commune']);
        $this->envoyer($sejour->client->email, new SejourConfirmeMail($sejour, $code), 'nouveau code');
        $this->codes->noterLEnvoi($sejour, self::CODE_D_ARRIVEE);
    }

    private function creerLeBon(Sejour $sejour): void
    {
        $proprietaire = $sejour->logement->residence->proprietaire;
        // Validation automatique si le mandat le prévoit, et toujours pour le compte interne de l'entreprise.
        $automatique = $proprietaire->interne || $proprietaire->bons_valides_automatiquement;

        BonDeMiseADisposition::create([
            'sejour_id' => $sejour->id, 'proprietaire_id' => $proprietaire->id, 'nuitees' => $sejour->nombreDeNuits(),
            'prix_proprietaire_par_nuit' => $sejour->prix_proprietaire_par_nuit,
            'etat' => $automatique ? 'valide' : 'en_attente', 'valide_automatiquement' => $automatique, 'valide_le' => $automatique ? now() : null,
        ]);
    }

    /** Client sans téléphone, ou sans passerelle configurée : silencieux, le courriel suffit. */
    private function notifierParSmsEtWhatsapp(Sejour $sejour): void
    {
        $client = $sejour->client;
        if ($client === null || blank($client->telephone)) {
            return;
        }

        $donnees = [
            'client' => $client->prenoms ?: $client->nom,
            'reference' => $sejour->reference,
            'logement' => $sejour->logement->nom,
            'arrivee' => $sejour->arrivee->format('d/m/Y'),
            'depart' => $sejour->depart->format('d/m/Y'),
            'lien' => config('plateforme.url_du_site').'/mon-espace/sejours/'.$sejour->reference,
        ];

        foreach ([CanalNotification::Sms, CanalNotification::Whatsapp] as $canal) {
            try {
                $this->notificateur->preparer(ModeleDeMessage::Confirmation, $canal, $client->telephone, $donnees);
            } catch (Throwable $e) {
                Log::error('Préparation de notification manquée', ['canal' => $canal->value, 'erreur' => $e->getMessage()]);
            }
        }
    }

    private function envoyer(string $adresse, Mailable $courriel, string $objet): void
    {
        try {
            Mail::to($adresse)->send($courriel);
        } catch (Throwable $e) {
            Log::error("Envoi manqué ({$objet})", ['erreur' => $e->getMessage()]);
        }
    }
}
