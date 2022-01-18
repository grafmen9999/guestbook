<?php

namespace App\Tests\Controller;

use Symfony\Component\Panther\PantherTestCase;

class ConferenceControllerTest extends PantherTestCase
{
    public function testIndex(): void
    {
        $client = static::createPantherClient(['external_base_uri' => $_SERVER['SYMFONY_PROJECT_DEFAULT_ROUTE_URL']]);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Give your feedback');
    }

    public function testCommentSubmission(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conference/amsterdam-2022');
        $client->submitForm('Submit', [
            'comment_form[author]' => 'Fabien',
            'comment_form[text]' => 'Some feedback from an automated functional test',
            'comment_form[email]' => 'me@automat.ed',
            'comment_form[photo]' => dirname(__DIR__, 2) . '/public/images/under-construction.gif',
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorExists('div:contains("There are 2 comments")');
    }

    public function testConferencePage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertCount(2, $crawler->filter('h4'));

        $client->click($crawler->filter('h4 + p a')->link());

        $this->assertPageTitleContains('Amsterdam');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Amsterdam 2022');
        $this->assertSelectorExists('div:contains("There are 1 comments")');
    }
}
