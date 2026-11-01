<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sans ces pages, les clientes voyaient « Not Found » ou « Server Error » en
 * anglais, sans aucun moyen de revenir à la boutique.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_an_unknown_page_is_explained_in_french_with_a_way_back(): void
    {
        $this->get('/une-page-qui-nexiste-pas')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('Retour à la boutique')
            ->assertDontSee('Not Found');
    }

    public function test_each_error_has_its_french_page(): void
    {
        $pages = [
            403 => 'Accès refusé',
            405 => 'Action impossible',
            413 => 'Une erreur est survenue',
            419 => 'Page expirée',
            429 => 'Trop de tentatives',
            503 => 'Site en maintenance',
        ];

        foreach ($pages as $code => $title) {
            Route::get("/essai-erreur-{$code}", fn () => abort($code));

            $this->get("/essai-erreur-{$code}")->assertStatus($code)->assertSee($title);
        }
    }

    public function test_a_server_crash_shows_the_french_page_and_no_technical_detail(): void
    {
        Route::get('/essai-panne', fn () => throw new \RuntimeException('secret-technique'));

        $this->get('/essai-panne')
            ->assertStatus(500)
            ->assertSee('Une erreur est survenue')
            ->assertDontSee('secret-technique')
            ->assertDontSee('Server Error');
    }

    public function test_the_error_pages_do_not_need_the_database(): void
    {
        // Si la base est en panne, la page d'erreur doit quand même s'afficher.
        DB::enableQueryLog();

        foreach ([403, 404, 405, 419, 429, 500, 503] as $code) {
            view("errors.{$code}")->render();
        }

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_the_api_keeps_answering_in_json(): void
    {
        $this->getJson('/api/v1/une-adresse-absente')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
