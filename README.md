#Macro

This class allows a developer to register PHP callables, like anonymous functions, as "macros", to use them later in a HTML page for example.
This content is parsed as macros can be called as the registered PHP callables. Since an example talks a lot more than anything else, here is one :

```php

require_once 'src/Macro/Macro.php';

use Macro/Macro;

Macro::register('hello', function($name){
	return 'Hello '+$name+' !';
});

echo Macro::parse('@hello("Foo")'); // will output 'Hello foo !'

```

If you don't know how many arguments your macro will receive, you can build your PHP function so it will accept only one
array argument :

```php

Macro::register('add', function(array $numbers){
	$result = 0;

	foreach($numbers as $n) {
		$result += $n;
	}

	return $result;
});

echo Macro::parse('@add(2,2,2)'); // will output '6' (oh, really ?)

```

Of course, these macros would be more useful in HTML templates. That's why the `parseFile()` method is here, it accepts one argument : the path to the file
you want to parse.

```php

echo Macro::parseFile('templates/my_template.html');

```
