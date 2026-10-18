<?php
/**
 * 404 — the requested page/route does not exist.
 *
 * CodeIgniter always routes PageNotFoundException here (even in development),
 * so the raw message is only surfaced when ENVIRONMENT is not production.
 */
$kgCode    = '404';
$kgHeading = 'Page not found';
$kgText    = 'The page you are looking for does not exist, or it has been moved.';
$kgTone    = 'primary';
$kgDetail  = $message ?? null;

require __DIR__ . '/_kg_error.php';
