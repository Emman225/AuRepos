<?php

// FRONT_URLS=http://localhost:5173 en test (phpunit.xml) : seul ce domaine doit passer.

it('autorise le domaine du front déclaré dans FRONT_URLS', function (): void {
    test()->withHeaders(['Origin' => 'http://localhost:5173'])
        ->getJson('/api/v1/configuration')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});

it('ne reflète jamais l’origine de l’appelant : seul le domaine déclaré est renvoyé', function (): void {
    // Un seul domaine configuré : le paquet CORS renvoie toujours CETTE valeur fixe, jamais
    // celle envoyée par l'appelant — c'est au navigateur de comparer et de bloquer en JS
    // si la page appelante n'est pas http://localhost:5173. Un curl/serveur n'est jamais
    // « bloqué » par CORS (ce n'est pas son rôle), mais l'en-tête ne renvoie jamais un domaine
    // pirate tel quel.
    test()->withHeaders(['Origin' => 'https://un-domaine-pirate.example'])
        ->getJson('/api/v1/configuration')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});
