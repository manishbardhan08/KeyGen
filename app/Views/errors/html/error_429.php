<?php
/**
 * 429 — too many requests. Raised when a rate-limiter rejects a request
 * (the API guard, or the panel write-throttle when it is not redirecting).
 */
$kgCode    = '429';
$kgHeading = 'Too many requests';
$kgText    = 'You have made too many requests in a short time. Please wait a moment and try again.';
$kgTone    = 'warning';

require __DIR__ . '/_kg_error.php';
