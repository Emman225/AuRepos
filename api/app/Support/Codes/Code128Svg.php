<?php

namespace App\Support\Codes;

use InvalidArgumentException;

/**
 * Générateur Code 128 minimal (jeu B), en SVG — pour le PDF de l'état des lieux (P2-SEJ-02),
 * qui doit porter un code-barres de la référence du séjour. Aucune bibliothèque de code-barres
 * n'était déjà une dépendance de ce projet (vérifié dans `composer.json` : dompdf, Intervention
 * Image et `simple-qrcode` seulement, ce dernier pour du QR, pas du 1D) ; plutôt que d'en
 * ajouter une, un encodeur autonome suffit largement à l'échelle de cette plateforme.
 *
 * Le jeu B couvre l'ASCII 32-126 (chiffres, lettres majuscules et minuscules, ponctuation) :
 * largement assez pour une référence du type « SEJ-000123 ». Les motifs de barres (11 modules
 * par symbole, 13 pour STOP) sont la table STANDARD du Code 128 — reprise à l'identique d'une
 * bibliothèque open-source de référence (JsBarcode), pas retapée à la main : une seule valeur
 * fausse dans 107 lignes rendrait le code imprimable mais illisible par un lecteur réel.
 */
final class Code128Svg
{
    /**
     * Motifs de barres des 107 symboles du Code 128 (valeurs 0 à 106), en modules noir (1) /
     * blanc (0) : 11 modules pour les symboles 0 à 105, 13 pour le symbole d'arrêt (106).
     *
     * @var list<string>
     */
    private const BARRES = [
        '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
        '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
        '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
        '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
        '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
        '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
        '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
        '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
        '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
        '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
        '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
        '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
        '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
        '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
        '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
        '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
        '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
        '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
        '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
        '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
        '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
        '11010011100', '1100011101011',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /** Valeur du symbole ASCII le plus bas et le plus haut couverts par le jeu B. */
    private const ASCII_MIN = 32;

    private const ASCII_MAX = 126;

    /**
     * @param  string  $texte  ASCII imprimable uniquement (32-126) — une référence de séjour convient toujours
     * @param  int  $largeurModule  largeur d'un module, en unités SVG
     */
    public static function svg(string $texte, int $largeurModule = 2, int $hauteur = 60): string
    {
        if ($texte === '') {
            throw new InvalidArgumentException('Le texte du code-barres ne peut pas être vide.');
        }

        $valeurs = [self::START_B];
        foreach (str_split($texte) as $caractere) {
            $code = ord($caractere);
            if ($code < self::ASCII_MIN || $code > self::ASCII_MAX) {
                throw new InvalidArgumentException("Caractère « {$caractere} » hors du jeu B du Code 128.");
            }
            $valeurs[] = $code - self::ASCII_MIN;
        }

        $somme = self::START_B;
        foreach (array_slice($valeurs, 1) as $position => $valeur) {
            $somme += $valeur * ($position + 1);
        }
        $valeurs[] = $somme % 103;
        $valeurs[] = self::STOP;

        $modules = implode('', array_map(fn (int $v): string => self::BARRES[$v], $valeurs));
        $largeurTotale = strlen($modules) * $largeurModule;

        $barres = '';
        $x = 0;
        foreach (str_split($modules) as $module) {
            if ($module === '1') {
                $barres .= sprintf('<rect x="%d" y="0" width="%d" height="%d" fill="#000000"/>', $x, $largeurModule, $hauteur);
            }
            $x += $largeurModule;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">%s</svg>',
            $largeurTotale, $hauteur, $largeurTotale, $hauteur, $barres,
        );
    }
}
