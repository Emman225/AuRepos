<?php

use App\Domain\Notifications\Enums\CanalNotification;
use App\Domain\Notifications\Enums\EtatNotification;
use App\Domain\Notifications\Enums\ModeleDeMessage;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Passerelles\PasserelleEssai;
use App\Domain\Notifications\Services\Notificateur;
use App\Domain\Notifications\Services\RenduDeModele;
use App\Jobs\EnvoyerNotification;
use App\Mail\NotificationGeneriqueMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------- rendu du modèle

it('remplace les jetons {{ ... }} par leurs valeurs', function (): void {
    $rendu = RenduDeModele::rendre('Bonjour {{ client }}, votre séjour {{reference}} est confirmé.', [
        'client' => 'Awa', 'reference' => 'SEJ-000001',
    ]);

    expect($rendu)->toBe('Bonjour Awa, votre séjour SEJ-000001 est confirmé.');
});

// ---------------------------------------------------------------- préparation et envoi

it('compose et journalise une notification avant tout envoi', function (): void {
    Queue::fake();

    $notification = app(Notificateur::class)->preparer(
        ModeleDeMessage::Confirmation, CanalNotification::Email, 'awa@exemple.ci',
        ['client' => 'Awa', 'reference' => 'SEJ-000001', 'logement' => 'Appartement A12', 'arrivee' => '10/11/2026', 'depart' => '13/11/2026', 'lien' => 'https://exemple.ci/mon-espace'],
    );

    expect($notification->etat)->toBe(EtatNotification::EnAttente)
        ->and($notification->canal)->toBe(CanalNotification::Email)
        ->and($notification->destinataire)->toBe('awa@exemple.ci')
        ->and($notification->sujet)->toContain('SEJ-000001')
        ->and($notification->corps)->toContain('Awa')->and($notification->corps)->toContain('SEJ-000001');

    Queue::assertPushed(EnvoyerNotification::class, fn (EnvoyerNotification $job) => $job->notificationId === $notification->id);
});

it('envoie un courriel via la Mailable générique, avec le sujet et le corps déjà composés', function (): void {
    Mail::fake();

    $notification = app(Notificateur::class)->preparer(
        ModeleDeMessage::DemandeAvisJ1, CanalNotification::Email, 'awa@exemple.ci',
        ['client' => 'Awa', 'logement' => 'Appartement A12', 'lien' => 'https://exemple.ci/avis'],
    );

    expect($notification->refresh()->etat)->toBe(EtatNotification::Envoyee)
        ->and($notification->envoyee_le)->not->toBeNull();
    Mail::assertSent(NotificationGeneriqueMail::class, fn (NotificationGeneriqueMail $m) => $m->hasTo('awa@exemple.ci') && str_contains($m->corps, 'Awa'));
});

it('envoie un SMS et un WhatsApp via la passerelle d’essai', function (): void {
    $sms = app(Notificateur::class)->preparer(ModeleDeMessage::RappelJ1, CanalNotification::Sms, '+2250700000000', ['client' => 'Awa', 'logement' => 'A12', 'reference' => 'SEJ-1', 'arrivee' => '10/11/2026', 'lien' => 'x']);
    $whatsapp = app(Notificateur::class)->preparer(ModeleDeMessage::RappelJ1, CanalNotification::Whatsapp, '+2250700000000', ['client' => 'Awa', 'logement' => 'A12', 'reference' => 'SEJ-1', 'arrivee' => '10/11/2026', 'lien' => 'x']);

    expect($sms->refresh()->etat)->toBe(EtatNotification::Envoyee)
        ->and($whatsapp->refresh()->etat)->toBe(EtatNotification::Envoyee);
});

it('journalise l’échec sans jamais relancer l’exception (CdC § 13.2)', function (): void {
    PasserelleEssai::simulerUnEchecPour('sms', '+2250700000000', 'Numéro invalide selon la passerelle.');

    $notification = app(Notificateur::class)->preparer(ModeleDeMessage::RappelJ1, CanalNotification::Sms, '+2250700000000', ['client' => 'Awa', 'logement' => 'A12', 'reference' => 'SEJ-1', 'arrivee' => '10/11/2026', 'lien' => 'x']);

    expect($notification->refresh()->etat)->toBe(EtatNotification::Echouee)
        ->and($notification->erreur)->toBe('Numéro invalide selon la passerelle.')
        ->and($notification->tentatives)->toBe(1)
        ->and($notification->envoyee_le)->toBeNull();
});

it('n’envoie pas deux fois une notification déjà envoyée', function (): void {
    $notification = app(Notificateur::class)->preparer(ModeleDeMessage::RappelJ1, CanalNotification::Sms, '+2250700000000', ['client' => 'Awa', 'logement' => 'A12', 'reference' => 'SEJ-1', 'arrivee' => '10/11/2026', 'lien' => 'x']);
    expect($notification->refresh()->tentatives)->toBe(1);

    app(Notificateur::class)->envoyer($notification->refresh());

    expect($notification->refresh()->tentatives)->toBe(1); // pas de seconde tentative
});

// ---------------------------------------------------------------- reprise planifiée

it('la reprise planifiée relance les échecs récents et respecte le nombre maximal de tentatives', function (): void {
    config(['notifications.reprise_minutes' => 0, 'notifications.tentatives_max' => 2]);

    $echouee = Notification::create([
        'canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+2250711111111',
        'corps' => 'Un message.', 'etat' => EtatNotification::Echouee, 'tentatives' => 1,
    ]);
    $epuisee = Notification::create([
        'canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+2250722222222',
        'corps' => 'Un autre message.', 'etat' => EtatNotification::Echouee, 'tentatives' => 2,
    ]);
    $dejaEnvoyee = Notification::create([
        'canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+2250733333333',
        'corps' => 'Déjà partie.', 'etat' => EtatNotification::Envoyee, 'tentatives' => 1, 'envoyee_le' => now(),
    ]);

    $repris = app(Notificateur::class)->reprendre();

    expect($repris)->toBe(1)
        ->and($echouee->refresh()->etat)->toBe(EtatNotification::Envoyee)
        ->and($epuisee->refresh()->tentatives)->toBe(2)        // pas repris : au max
        ->and($dejaEnvoyee->refresh()->tentatives)->toBe(1);   // pas repris : déjà envoyée
});
