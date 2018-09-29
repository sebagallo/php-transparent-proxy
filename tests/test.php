<?php

function my_autoload($pClassName)
{
    include(__DIR__ . '/../src/' . $pClassName . '.php');
}

spl_autoload_extensions(".php");
spl_autoload_register('my_autoload');

$url = 'http://www.tuttosport.com';
$proxy = new Seba\Routing\TransparentProxy($url);
$result = $proxy->makeRequest();

echo($result);
