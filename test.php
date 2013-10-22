<?php

date_default_timezone_set('Europe/Prague');

require_once dirname(__FILE__).'/fio.api.php';

$fio = new FioApi('..token..');


$fio->reset();

$transactions = $fio->getData();

var_dump($transactions);