<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\CanalNotification;
use App\Domain\Notifications\Enums\EtatNotification;
use App\Domain\Notifications\Enums\ModeleDeMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une notification envoyée (ou à envoyer) — CdC § 13.2 : journalisée et relançable.
 *
 * @property int $id
 * @property CanalNotification $canal
 * @property ModeleDeMessage $modele
 * @property string $destinataire
 * @property string|null $sujet
 * @property string $corps
 * @property array<string, mixed>|null $donnees
 * @property EtatNotification $etat
 * @property int $tentatives
 * @property string|null $erreur
 * @property Carbon|null $envoyee_le
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'en_attente', 'tentatives' => 0];

    protected function casts(): array
    {
        return [
            'canal' => CanalNotification::class, 'modele' => ModeleDeMessage::class, 'etat' => EtatNotification::class,
            'donnees' => 'array', 'tentatives' => 'integer', 'envoyee_le' => 'datetime',
        ];
    }

    /** Une notification déjà envoyée ne se relance pas ; une échouée peut l'être un nombre limité de fois. */
    public function estRelancable(int $maxTentatives): bool
    {
        return $this->etat !== EtatNotification::Envoyee && $this->tentatives < $maxTentatives;
    }

    public function libelleAudit(): string
    {
        return $this->canal->libelle().' « '.$this->modele->libelle().' » à '.$this->destinataire;
    }
}
