<?php

declare(strict_types=1);

it('serves the run-sheet without a login', function () {
    $this->get('/demo')
        ->assertOk()
        // Rendered from the docs file, so a wine the demo depends on proves
        // the real content arrived rather than an empty wrapper.
        ->assertSee('Bricco Pernice')
        ->assertSee('trade@cellaros.test');
});

it('keeps the run-sheet out of search results', function () {
    // It's public for convenience while we're pre-launch, and it names a
    // supplier whose prices we currently have wrong — it should not be found
    // by anyone who wasn't given the link.
    $this->get('/demo')->assertSee('noindex', false);
});

it('lifts the fragment title into the document head', function () {
    // Exactly one <title>, in the head — the docs fragment carries its own,
    // which would otherwise end up loose in the body.
    $content = $this->get('/demo')->getContent();

    expect(substr_count($content, '<title>'))->toBe(1)
        ->and($content)->toContain('<title>CellarOS Run-Sheet — CellarOS</title>');
});
