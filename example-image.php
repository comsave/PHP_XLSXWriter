<?php

require_once('xlsxwriter.class.php');
require_once('xlsxwriterplus.class.php');

$writer = new XLSWriterPlus();

$writer->writeSheetRow('Sheet1', ['test line']);

$writer->addImage(realpath('./image.jpg'), 1, [
    'startColNum' => 0,
    'startRowNum' => 0,
]);

$writer->writeToFile('example-image.xlsx');