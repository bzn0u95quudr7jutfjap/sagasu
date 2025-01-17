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

function array_get_else($key, $array, $else) {
  return array_key_exists($key, $array) ? $array[$key] : $else;
}

// Corpo principale

function find($directory, $pattern, $maxdepth = 256) {
  $depth = fn ($f, $d, $a) => $d > 0 ? $f($f, $d - 1, "{,*/$a}") : $a;
  $glob  = "$directory/" . $depth($depth, $maxdepth, '') . "/$pattern";
  $files = glob($glob, GLOB_BRACE);
  $files = array_filter($files,fn ($a) => !is_dir($a));
  $files = array_map(fn ($a) => str_replace('//', '/', $a),$files);
  $files = array_combine($files, $files);
  return $files;
}

function replace($regexes, $files) {
  $f = function ($file) use ($regexes) {
    $content = file_get_contents($file);
    $content = preg_replace(array_keys($regexes), $regexes, $content);
    return file_put_contents($file, $content);
  };
  return array_map($f,$files);
}

function contains($regex, $files) {
  $files = array_filter($files,fn ($a) => count($a) > 0);
  return array_reduce(fn ($a, $f) => $f($a), $files)([
    _map(fn ($a) => preg_grep($regex, explode("\n", file_get_contents($a)))),
    _filter(),
  ]);
}

// Main

//$flags = getopt('hqp:d:g:s:i:f:');
//if (array_key_exists('h', $flags)) {
//  die(<<<'EOF'
//
//        使い方:
//
//          Stampa i file presenti in DIRECTORY il cui nome corrisponde a PATTERN.
//          sagasu.php [-d <DIRECTORY>] [-p <PATTERN>]
//
//          Stampa i file il contenuto corrisponde con REGEX
//          sagasu.php -g <REGEX> [-q] [-d <DIRECTORY>] [-p <PATTERN>]
//
//          Sostituisce REGEX con SUBST
//          sagasu.php -g <REGEX> -s <SUBST> [-d <DIRECTORY>] [-p <PATTERN>]
//
//          Stampa questo messaggio
//          sagasu.php -h
//
//          Esegui codice personalizzato
//          sagasu.php -e <ENTRYPOINT> -r <REQUIRE_ONCE>
//          sagasu.php -e <ENTRYPOINT> -f <STRING_CODE>
//          
//
//      EOF);
//}

const ABSN = 1;
const PRES = 2;
const BOTH = 3;

const HELP   = 'HELP' ;
const LISTF  = 'LIST' ;
const QUIET  = 'QUIET';
const GREP   = 'GREP' ;
const SUBST  = 'SUBST';
const FILE   = 'FILE' ;
const CODE   = 'CODE' ;
const ERROR  = 'ERROR';

const MODES = [
  HELP  => [ 'h' => PRES, 'p' => BOTH, 'd' => BOTH, 'q' => BOTH, 'g' => BOTH, 's' => BOTH, 'i' => BOTH, 'f' => BOTH, ],
  LISTF => [ 'h' => ABSN, 'p' => BOTH, 'd' => BOTH, 'q' => ABSN, 'g' => ABSN, 's' => ABSN, 'i' => ABSN, 'f' => ABSN, ],
  QUIET => [ 'h' => ABSN, 'p' => BOTH, 'd' => BOTH, 'q' => PRES, 'g' => BOTH, 's' => ABSN, 'i' => ABSN, 'f' => ABSN, ],
  GREP  => [ 'h' => ABSN, 'p' => BOTH, 'd' => BOTH, 'q' => ABSN, 'g' => PRES, 's' => ABSN, 'i' => ABSN, 'f' => ABSN, ],
  SUBST => [ 'h' => ABSN, 'p' => BOTH, 'd' => BOTH, 'q' => ABSN, 'g' => PRES, 's' => PRES, 'i' => ABSN, 'f' => ABSN, ],
  CODE  => [ 'h' => ABSN, 'p' => BOTH, 'd' => BOTH, 'q' => ABSN, 'g' => BOTH, 's' => ABSN, 'i' => PRES, 'f' => ABSN, ],
  FILE  => [ 'h' => ABSN, 'p' => BOTH, 'd' => BOTH, 'q' => ABSN, 'g' => BOTH, 's' => ABSN, 'i' => ABSN, 'f' => PRES, ],
];

function validate_opts($m,$o){
  $n = array_map(fn ($k, $v) => (array_key_exists($k, $o) ? PRES : ABSN) & $v, array_keys($m), $m);
  $b = array_reduce($n,fn ($a,$b) => $a && $b, true);
  return $b;
}

$opts = getopt('ihqp:d:g:s:f:');
$matches = array_map(fn ($m) => validate_opts($m,$opts),MODES);
$matches = array_filter($matches, fn ($a) => $a);
$mode = (1 == count($matches)) ? array_keys($matches)[0] : ERROR;
$directory  = array_key_exists('d', $opts) ? $opts['d'] : '.';
$pattern    = array_key_exists('p', $opts) ? $opts['p'] : '*';
$grepptn    = array_key_exists('g', $opts) ? $opts['g'] : false;
$files = find($directory, $pattern);
$filesgrepped = false !== $grepptn ? contains($grepptn, $files) : $files;
$argv = $filesgrepped;

switch($mode) {
  case HELP:
  break;
  case LISTF:
    print_r($files);
  break;
  case QUIET:
    print_r(array_keys($filesgrepped));
  break;
  case GREP:
    print_r(filesgrepped);
  break;
  case SUBST:
    replace([$opts['g'] => $opts['s']], $files);
  break;
  case FILE:
    require_once $opts['f'];
  break;
  case CODE:
    $code = stream_get_contents(STDIN);
    eval($code);
  break;
  default:
    echo "ARGOMENTI INVALIDI\n";
    print_r($matches);
    die(2);
  break;
}
