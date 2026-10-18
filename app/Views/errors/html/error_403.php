<?php
/**
 * 403 — forbidden. Raised by CSRF failures (SecurityException) and by any
 * controller that refuses access. The exception message is deliberately not
 * echoed in production (it can hint at endpoints); dev shows it.
 */
$kgCode    = '403';
$kgHeading = 'Access denied';
$kgText    = 'You do not have permission to view this page. If you believe this is a mistake, sign in again and retry.';
$kgTone    = 'danger';
$kgDetail  = $message ?? null;

require __DIR__ . '/_kg_error.php';
