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

function _find($directory) {
  $depth = 12;
  $glob = $directory . '/' . str_repeat('{,*/', $depth) . str_repeat('}', $depth) . '*';
  $glob = _filter(fn ($a) => !is_dir($a))(glob($glob, GLOB_BRACE));
  $glob = array_combine($glob, $glob);
  return $glob;
}

function findcontains($r, $g) {
  return _reduce(fn ($a, $f) => $f($a), $g)([
    _map(fn ($a) => preg_grep("/.*$r.*/", explode("\n", file_get_contents($a))),),
    _filter(fn ($a) => count($a) > 0),
  ]);
}

// Main

$a = getopt('p:d:', ['pattern:', 'directory:',]);

print_r(
  findcontains($a['p'], _find(array_key_exists('d', $a) ? $a['d'] : '.'))
);
