<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

/**
 * Serves the demo run-sheet at /demo.
 *
 * The page is rendered from `docs/demo/run-sheet.html` rather than a Blade
 * view of its own, deliberately: that file has to keep working when opened
 * straight off disk (it is what gets handed to someone before a demo, and what
 * the published artifact is built from). Reading it here means one source, so
 * the web copy can't quietly drift from the file everyone else has.
 *
 * It is admin-only. The run-sheet names a real supplier whose prices we
 * currently have wrong, and admits to test data in the catalogue — honest and
 * useful internally, not something to serve to a logged-in customer.
 */
class DemoRunSheetController extends Controller
{
    private const SOURCE = 'docs/demo/run-sheet.html';

    public function __invoke(): View
    {
        $path = base_path(self::SOURCE);

        abort_unless(File::exists($path), 404);

        $html = File::get($path);

        // The fragment carries its own <title>, which belongs in the document
        // head rather than the body. Lift it out and pass it through.
        $title = 'Demo run-sheet';

        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches) === 1) {
            $title = trim(strip_tags($matches[1]));
            $html = preg_replace('/<title>.*?<\/title>/is', '', $html, 1) ?? $html;
        }

        return view('demo.run-sheet', [
            'title' => $title,
            'content' => $html,
        ]);
    }
}
