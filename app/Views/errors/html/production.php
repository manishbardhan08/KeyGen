<?php
/**
 * Generic "unexpected error" page (usually 500).
 *
 * CodeIgniter falls back to this view for every failing status that does not
 * have its own `error_<code>.php` file, so it must never leak internals. The
 * exception message is intentionally not used here at all — while developing
 * you get CodeIgniter's full trace page instead, because display_errors is on.
 */
$kgCode    = (string) ($code ?? 500);
$kgHeading = 'Something went wrong';
$kgText    = 'We hit an unexpected snag. Please try again in a moment — if it keeps happening, contact your administrator.';
$kgTone    = 'danger';

require __DIR__ . '/_kg_error.php';
