<?php
/**
 * 404 — the requested page/route does not exist.
 *
 * CodeIgniter always routes PageNotFoundException here (even in development),
 * so this page stays deliberately generic: it never prints the route or the
 * exception message, which are of no use to a visitor anyway.
 */
$kgCode    = '404';
$kgHeading = 'Page not found';
$kgText    = 'The page you are looking for does not exist, or it has been moved.';
$kgTone    = 'primary';

require __DIR__ . '/_kg_error.php';
