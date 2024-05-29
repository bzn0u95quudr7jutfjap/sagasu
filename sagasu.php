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
  $f = fn ($file) => file_put_contents($file, preg_replace(array_keys($r), $r, file_get_contents($file)));
  return _map($f)($g);
}

function contains($r, $g) {
  return _reduce(fn ($a, $f) => $f($a), $g)([
    _map(fn ($a) => preg_grep($r, explode("\n", file_get_contents($a)))),
    _filter(fn ($a) => count($a) > 0),
  ]);
}

// Main

$flags = getopt('hp:d:g:s:q');
if (array_key_exists('h', $flags)) {
  die(<<<'EOF'

        使い方:

          Stampa i file presenti in DIRECTORY il cui nome corrisponde a PATTERN.
          sagasu.php [-d <DIRECTORY>] [-p <PATTERN>]

          Stampa i file il contenuto corrisponde con REGEX
          sagasu.php -g <REGEX> [-q] [-d <DIRECTORY>] [-p <PATTERN>]

          Sostituisce REGEX con SUBS
          sagasu.php -g <REGEX> -s <SUBS> [-d <DIRECTORY>] [-p <PATTERN>]

          Stampa questo messaggio
          sagasu.php -h
          

      EOF);
}

$pattern = array_key_exists('p', $flags) ? $flags['p'] : '*';
$directory = array_key_exists('d', $flags) ? $flags['d'] : '.';
$quiet = array_key_exists('q', $flags) ? 'array_keys' : fn ($a) => $a;

if (!array_key_exists('g', $flags)) {
  print_r(array_keys(find($directory, $pattern)));
} else {
  if (!array_key_exists('s', $flags)) {
    print_r($quiet(contains("/{$flags['s']}/", find($directory, $pattern))));
  } else {
    $quiet(replace(["/{$flags['g']}/" => $flags['s']], find($directory, $pattern)));
  }
}
