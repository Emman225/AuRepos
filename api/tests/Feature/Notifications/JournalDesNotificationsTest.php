<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Notifications\Enums\CanalNotification;
use App\Domain\Notifications\Enums\EtatNotification;
use App\Domain\Notifications\Enums\ModeleDeMessage;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Passerelles\PasserelleEssai;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->profil(Profil::Administrateur)->create();
    test()->withToken(auth('api')->login($this->admin));
});

it('liste les notifications, filtrables par canal et par état', function (): void {
    Notification::create(['canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+22501', 'corps' => 'a', 'etat' => EtatNotification::Envoyee]);
    Notification::create(['canal' => CanalNotification::Email, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => 'b@exemple.ci', 'corps' => 'b', 'etat' => EtatNotification::Echouee]);

    test()->getJson('/api/v1/backoffice/notifications?canal=sms')->assertOk()->assertJsonCount(1, 'data.elements');
    test()->getJson('/api/v1/backoffice/notifications?etat=echouee')->assertOk()->assertJsonCount(1, 'data.elements');
});

it('relance manuellement une notification échouée', function (): void {
    $notification = Notification::create([
        'canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+22501',
        'corps' => 'Un message.', 'etat' => EtatNotification::Echouee, 'tentatives' => 1, 'erreur' => 'Panne réseau',
    ]);

    test()->postJson("/api/v1/backoffice/notifications/{$notification->id}/relance")
        ->assertOk()->assertJsonPath('data.etat', 'envoyee')->assertJsonPath('data.tentatives', 2);
});

it('refuse de relancer une notification déjà envoyée', function (): void {
    $notification = Notification::create([
        'canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+22501',
        'corps' => 'Un message.', 'etat' => EtatNotification::Envoyee, 'tentatives' => 1, 'envoyee_le' => now(),
    ]);

    test()->postJson("/api/v1/backoffice/notifications/{$notification->id}/relance")
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'notification_deja_envoyee');
});

it('la commande planifiée relance les notifications éligibles', function (): void {
    PasserelleEssai::simulerUnEchecPour('sms', '+22509', 'échec simulé');
    $notification = Notification::create([
        'canal' => CanalNotification::Sms, 'modele' => ModeleDeMessage::RappelJ1, 'destinataire' => '+22509',
        'corps' => 'Un message.', 'etat' => EtatNotification::EnAttente, 'tentatives' => 0,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('notifications:reprendre')->assertExitCode(0);

    expect($notification->refresh()->etat)->toBe(EtatNotification::Echouee)->and($notification->tentatives)->toBe(1);
});
