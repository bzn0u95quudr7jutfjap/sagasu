#!/bin/php
<?php

// Errori belli

set_error_handler(function ($severity, $message, $file, $line) {
  throw new \ErrorException($message, $severity, $severity, $file, $line);
});

set_exception_handler(function (Throwable $exception) {
  $a = explode("\n", "{$exception->getMessage()}\n\n{$exception->getTraceAsString()}");
  $format = '%-' . max(array_map('strlen', $a)) . 's' . "        <br>\n";
  $a = array_map(fn ($a) => sprintf($format, $a), $a);
  $a = implode("", $a);
  die("\n" . $a . "\n");
});

// Utils

function _filter($f) {
  return fn ($a) => array_filter($a, $f);
}

function _map($f) {
  return fn ($a) => array_map($f, $a);
}

function _reduce($f, $i) {
  return fn ($a) => array_reduce($a, $f, $i);
}

// Corpo principale

function find($directory, $pattern) {
  $depth = fn ($f, $d, $a) => $d > 0 ? $f($f, $d - 1, "{,*/$a}") : $a;
  $files = glob("$directory/{$depth($depth, 12, '')}/$pattern", GLOB_BRACE);
  $files = _filter(fn ($a) => !is_dir($a))($files);
  $files = _map(fn ($a) => str_replace('//', '/', $a))($files);
  $files = array_combine($files, $files);
  return $files;
}

function replace($r, $g) {
  $rk = array_keys($r);
  foreach ($g as $file) {
    file_put_contents($file, preg_replace($rk, $r, file_get_contents($file)));
  }
}

function contains($r, $g) {
  return _reduce(fn ($a, $f) => $f($a), $g)([
    _map(fn ($a) => preg_grep($r, explode("\n", file_get_contents($a)))),
    _filter(fn ($a) => count($a) > 0),
  ]);
}

// Main

$flags = getopt('p:d:r:q');
if (!array_key_exists('r', $flags)) {
  die("使い方: sagasu.php -r <REGEX> [-p <PATTERN>] [-d '<DIRECTORY>'] [-q]\n");
}
$pattern = array_key_exists('p', $flags) ? $flags['p'] : '*';
$directory = array_key_exists('d', $flags) ? $flags['d'] : '.';
$quiet = array_key_exists('q', $flags) ? 'array_keys' : fn ($a) => $a;
$regex = $flags['r'];
if (strpos('s/', $regex) == 0) {
  $regex = explode(';', $regex);
  $regex = _filter(fn ($a) => strlen($a) > 0)($regex);
  $regex_old = $regex;
  $regex = _map(function ($a) {
    preg_match_all('/s(\\/.*?[^\\\\]?\\/)(.*?[^\\\\])?\\//', $a, $matches);
    return $matches;
  })($regex);
  $regex = _map(fn ($a) => [$a[1][0], $a[2][0]])($regex);
  $regex = array_combine(array_column($regex, 0), array_column($regex, 1));
  if (count($regex) == count($regex_old)) {
    die("エラー: Regex invalida\n");
  }
  $function = 'replace';
} else {
  $function = 'contains';
}

print_r(
  $quiet($function($regex, find($directory, $pattern)))
);
