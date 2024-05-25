#!/bin/php
<?php

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

function _find($directory) {
  $depth = 12;
  $glob = $directory . '/' . str_repeat('{,*/', $depth) . str_repeat('}', $depth) . '*';
  $glob = array_filter(glob($glob, GLOB_BRACE), fn ($a) => !is_dir($a));
  $glob = array_combine($glob, $glob);
  return $glob;
}

function preg($r, $s) {
  $a = [];
  preg_match_all("/.*$r.*/", $s, $a);
  return $a;
}

function findcontains($r, $g) {
  return array_filter(
    array_map(
      fn ($a) => preg_grep("/.*$r.*/", explode("\n", file_get_contents($a))),
      $g
    ),
    fn ($a) => count($a) > 0,
  );
}

$a = getopt('p:d:', ['pattern:', 'directory:',]);

print_r(
  findcontains($a['p'], _find(array_key_exists('d', $a) ? $a['d'] : '.'))
);
