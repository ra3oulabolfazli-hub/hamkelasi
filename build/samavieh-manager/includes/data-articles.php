<?php
if (!defined('ABSPATH')) { exit; }
$json = file_get_contents(__DIR__ . '/data-articles.json');
$data = $json ? json_decode($json, true) : array();
return is_array($data) ? $data : array();
