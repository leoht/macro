<?php
require_once 'src/Macro/Macro.php';

use Macro\Macro;


Macro::register('hello', function($name = 'World'){

	return "Hello $name !";

});

echo Macro::parse("What did you said ? - I said : @hello('you') ");

echo Macro::parseFile('example.html');