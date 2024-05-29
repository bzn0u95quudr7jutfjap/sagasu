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

function array_get_else($key, $array, $else) {
  return array_key_exists($key, $array) ? $array[$key] : $else;
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

          Sostituisce REGEX con SUBST
          sagasu.php -g <REGEX> -s <SUBST> [-d <DIRECTORY>] [-p <PATTERN>]

          Stampa questo messaggio
          sagasu.php -h
          

      EOF);
}

$files = find(array_get_else('d', $flags, '.'), array_get_else('p', $flags, '*'));

match (false) {
  $grep = array_get_else('g', $flags, false) => print_r(array_keys($files)),
  $subst = array_get_else('s', $flags, false) => print_r((array_key_exists('q', $flags) ? 'array_keys' : fn ($a) => $a)(contains("/$grep/", $files))),
  default => replace(["/$grep/" => $subst], $files),
};
