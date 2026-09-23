<?php

it('pose les en-têtes de sécurité sur toute réponse de l’API', function (): void {
    $reponse = test()->getJson('/api/v1/configuration');

    $reponse->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()')
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
});

it('n’envoie pas de HSTS en HTTP simple (dev), mais l’envoie en HTTPS', function (): void {
    test()->getJson('/api/v1/configuration')->assertHeaderMissing('Strict-Transport-Security');

    // Il faut une URL explicitement https:// : Symfony déduit HTTPS du schéma de l'URL et
    // écraserait un simple withServerVariables(['HTTPS' => 'on']) avec le schéma http par défaut.
    test()->getJson('https://localhost/api/v1/configuration')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
