<?php

$url = 'https://developers.google.com/adwords/api/docs/appendix/currencycodes.csv';

$csv = array_map('str_getcsv', file($url));
$headers = array_shift($csv);

$meta = array();
$i = 0;

foreach ($csv as $cur) {
  foreach ($headers as $col => $header) {
    $meta[$i][$header] = htmlentities(preg_replace("/\"/", '', $cur[$col]), ENT_COMPAT | ENT_HTML_401, 'UTF-8');
  }
  $i++;
}

var_dump($meta);
