<?php

use App\Domain\Comptes\Models\User;

// Limiteur global « api » (P1-API-08) : 60 req/min par IP pour un visiteur, 300 req/min
// par utilisateur pour un compte connecté. En plus des throttle:x,1 déjà posés route par
// route sur les opérations sensibles (voir CorsTest et les routes elles-mêmes).

it('bloque un visiteur au-delà de 60 requêtes par minute sur une route sans limite spécifique', function (): void {
    for ($i = 0; $i < 60; $i++) {
        test()->getJson('/api/v1/configuration')->assertOk();
    }

    test()->getJson('/api/v1/configuration')->assertStatus(429);
});

it('accorde à un utilisateur connecté un quota bien plus large que celui d’un visiteur', function (): void {
    $client = User::factory()->create();
    test()->withToken(auth('api')->login($client));

    // 65 requêtes : dépasserait le quota d'un visiteur (60/min), mais reste bien sous
    // le quota par utilisateur (300/min) — preuve que la clé du compteur est bien distincte.
    for ($i = 0; $i < 65; $i++) {
        test()->getJson('/api/v1/client/devis')->assertOk();
    }
});
