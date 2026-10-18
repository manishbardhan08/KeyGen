<?php
/**
 * 403 — forbidden. Raised by CSRF failures (SecurityException) and by any
 * controller that refuses access. The exception message is never echoed (it can
 * hint at endpoints); the copy below is deliberately generic.
 */
$kgCode    = '403';
$kgHeading = 'Access denied';
$kgText    = 'You do not have permission to view this page. If you believe this is a mistake, sign in again and retry.';
$kgTone    = 'danger';

require __DIR__ . '/_kg_error.php';
