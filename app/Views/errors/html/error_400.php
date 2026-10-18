<?php
/**
 * 400 — malformed request (bad input, broken payload, …).
 */
$kgCode    = '400';
$kgHeading = 'Bad request';
$kgText    = 'Something is wrong with your request. Please check your input and try again.';
$kgTone    = 'warning';

require __DIR__ . '/_kg_error.php';
