<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\ControleDesTarifs;
use App\Domain\Catalogue\Services\PublicationDeLogement;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\LogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Publication d'un logement, côté administration (CdC § 7.1). */
final class PublicationLogementController extends Controller
{
    public function __construct(
        private readonly PublicationDeLogement $publication,
        private readonly ControleDesTarifs $controleDesTarifs,
    ) {}

    /** Où en est le logement, et ce qui l'empêche encore d'être mis en ligne. */
    public function afficher(Residence $residence, Logement $logement): JsonResponse
    {
        return ReponseApi::succes([
            'etat' => $logement->etat_publication->value,
            'etat_libelle' => $logement->etat_publication->libelle(),
            'motif' => $logement->getAttribute('motif_refus'),
            'motifs_refus_champs' => $logement->motifs_refus_champs,
            'obstacles' => $this->publication->obstacles($logement),
            // Signal pour l'administrateur qui valide ou refuse — « tarif hors médiane » est un des motifs possibles (CdC § 7.1).
            'controle_mediane' => $this->controleDesTarifs->ecart($logement),
        ]);
    }

    public function agir(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'action' => ['required', Rule::in(['soumettre', 'publier', 'refuser', 'suspendre', 'reactiver'])],
            'motif' => ['nullable', 'string', 'min:5', 'max:255', 'required_if:action,refuser,suspendre'],
            // Refus par champ (CdC § 7.1), en plus du motif global : { "description": "Trop courte" }.
            'motifs_champs' => ['sometimes', 'array'],
            'motifs_champs.*' => ['string', 'min:5', 'max:255'],
        ], ['motif.required_if' => 'Cette action doit être motivée.'], ['action' => 'action', 'motif' => 'motif']);

        /** @var User $auteur */
        $auteur = $request->user();
        $motif = (string) ($saisie['motif'] ?? '');

        match ($saisie['action']) {
            'soumettre' => $this->publication->soumettre($logement, $auteur),
            'publier' => $this->publication->publier($logement, $auteur),
            'refuser' => $this->publication->refuser($logement, $auteur, $motif, $saisie['motifs_champs'] ?? []),
            'suspendre' => $this->publication->suspendre($logement, $auteur, $motif),
            default => $this->publication->reactiver($logement, $auteur),
        };

        return ReponseApi::succes(
            new LogementResource($logement->refresh()->load(['type', 'equipements'])),
            'Logement : '.mb_strtolower($logement->etat_publication->libelle()).'.',
        );
    }
}
