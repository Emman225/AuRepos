<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Contenu\Enums\StatutArticle;
use App\Domain\Contenu\Models\Article;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Sitemap XML (CdC § 12, P1-PUB-09) : pages statiques du site public + un lien par
 * logement publié + un lien par article de blog publié (page de lecture ajoutée
 * après coup — voir le journal de décisions, ne plus exclure le blog une fois
 * qu'une vraie page publique existe).
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = config('plateforme.url_du_site');

        $urls = [
            ['loc' => $base.'/', 'priorite' => '1.0'],
            ['loc' => $base.'/recherche', 'priorite' => '0.8'],
            ['loc' => $base.'/blog', 'priorite' => '0.5'],
            ['loc' => $base.'/conditions-generales', 'priorite' => '0.3'],
            ['loc' => $base.'/confidentialite', 'priorite' => '0.3'],
        ];

        Logement::query()
            ->where('etat_publication', EtatPublication::Publie)
            ->whereRelation('residence', 'active', true)
            ->orderBy('id')
            ->chunk(200, function ($logements) use (&$urls): void {
                foreach ($logements as $logement) {
                    $urls[] = [
                        'loc' => config('plateforme.url_du_site')."/logements/{$logement->reference}",
                        'priorite' => '0.7',
                    ];
                }
            });

        Article::query()
            ->where('statut', StatutArticle::Publie)
            ->orderBy('id')
            ->chunk(200, function ($articles) use (&$urls): void {
                foreach ($articles as $article) {
                    $urls[] = [
                        'loc' => config('plateforme.url_du_site')."/blog/{$article->slug}",
                        'priorite' => '0.4',
                    ];
                }
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
