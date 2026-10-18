<?php
/**
 * Generic "unexpected error" page (usually 500).
 *
 * CodeIgniter falls back to this view for every failing status that does not
 * have its own `error_<code>.php` file, so it must never leak internals: the
 * exception message is only passed through outside production. While developing
 * you will normally see the full stack trace (error_exception.php) instead,
 * because display_errors is on there.
 */
$kgCode    = (string) ($code ?? 500);
$kgHeading = 'Something went wrong';
$kgText    = 'We hit an unexpected snag. Please try again in a moment — if it keeps happening, contact your administrator.';
$kgTone    = 'danger';
$kgDetail  = (ENVIRONMENT !== 'production') ? ($message ?? null) : null;

require __DIR__ . '/_kg_error.php';
