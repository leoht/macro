<?php
namespace Macro;


/**
 * The Macro class allows to register
 * PHP functions as macros, to use them in
 * a parsed content (such as HTML or anything else).
 *
 * @author Léonard Hetsch
 */

class Macro
{

	/**
	* @var array
	*/
	protected static $macros;


	/**
	* Registers a PHP callable as a macro.
	*
	* @param string $name The macro name (must be unique)
	* @param callable $macroCallable A valid PHP callable
	*/
	public static function register($name, $macroCallable)
	{
		if (!is_callable($macroCallable)) {
			throw new \InvalidArgumentException(sprintf("Argument 2 passed to Macro::register must be a valid PHP callable"));
		}

		self::$macros[$name] = $macroCallable;
	}


	/**
	* Checks if a macro if registered.
	*
	* @param string $name
	* @return boolean
	*/
	public static function has($name)
	{
		return array_key_exists($name, self::$macros);
	}


	/**
	* Gets the macro callable.
	*
	* @param string $name
	* @return callable
	*/
	public static function get($name)
	{
		return self::$macros[$name];
	}


    /**
    * Parses a file content.
    *
    * @param string $path The file path.
    * @return string
    * @see Macro\Macro::parse
    */
    public static function parseFile($path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf("Couldn't find file '%s'.", $path));
        }

        $content = file_get_contents($path);

        return self::parse($content);
    }


	/**
	* Parses a content to execute macros in it.
	*
	* @param string $content The content to parse.
	* @return string The parsed content
	*/
	public static function parse( $content )
	{
		$line = strtok($content, "\r\n");
    	$line_num = 1; // for debugging
		$is_php_section = false;
		$new_content = '';

		while ($line !== false) {

            // This code is to avoid from parsing macros in PHP code, which is clearly nonsense and buggy.
    		if (false !== strpos($line, '<?php') && !$is_php_section) {
    			$is_php_section = true;
    		}
    		if (false !== strpos($line, '?>') && $is_php_section) {
    			$is_php_section = false;
    		}



    		if (!$is_php_section) {

    			$new_line = '';

            	$fragmented_line = explode('@', $line);

            	foreach ($fragmented_line as $index => $line_part) {

                	if ($index != 0) {
                    	$line_part = '@'.$line_part;
                	}

                    // Abort parsing if there is a "*" after the macro tag.
               		if (1 === strpos($line_part, '*')) {
                    	$new_line .= str_replace('*', '', $line_part);
                	}

                    // Is the macro tag here ?
    		    	if (false !== $begin_macro = strpos($line_part,'@')) {

    					$end_macro = strpos($line_part, ')', $begin_macro);

                        // If no closing bracket is found
    					if (false === $end_macro) {
            				throw new \RuntimeException(sprintf("Error while parsing macro (near '%s'), no closing bracket ')' was found.", substr($line_part, $begin_macro, 15)));
        				}

                        // The whole macro
        				$macro_string = substr($line_part, $begin_macro, $begin_macro+$end_macro+1);

    					$macro_result = self::parseMacro($macro_string);

    					$line_part = str_replace($macro_string, $macro_result, $line_part);

    					$new_line .= $line_part;

    					
    				
    				} else {
                    	$new_line .= $line_part;
               		}    			
    			}

            
    		}

        	$new_content .= $new_line."\r\n";

        	$line_num++;

            // go to the next line
    		$line = strtok("\r\n");
		}

		return $new_content;
	}


	/**
	* Parses one single macro.
	*
	* @param string $macro the macro string (i.e. '@hello("World")'' ).
	* @return string the result of the called macro.
	*/
	public static function parseMacro($macro)
	{

		$begin_macro_arguments = strpos($macro, '(') + 1;
    	$end_macro_arguments = strpos($macro, ')', $begin_macro_arguments);
        $macro_name = substr($macro, 1, $begin_macro_arguments-2);

    	$macro_arguments_string = substr($macro, $begin_macro_arguments, $end_macro_arguments - $begin_macro_arguments);

        // If the macro is registered
		if (self::has($macro_name)) {
            // If no argument is provided, then $arguments is an empty array and we don't bother with explode() return value.
    		$arguments = strlen($macro_arguments_string) > 0 ? explode(',', $macro_arguments_string) : array();

            foreach($arguments as &$arg) {
                // Is the argument an quoted string ?
                if (preg_match('/"/', $arg)) {
                    $arg = str_replace('"', '', $arg);
                } 
                else if (preg_match('/\'/', $arg)) {
                    $arg = str_replace('\'', '', $arg);
                }
                else {
                    // Is the argument a numeric value ?
                	if (preg_match('#([0-9\.\-]+)#', $arg)) {
                        continue;
                    }
                    // Otherwise the argument is linked to a PHP variable
                    else if (!isset(${$arg})) {
                        throw new \RuntimeException(sprintf("Error while parsing macro '%s' : unknown variable '%s'.", $macro_name, $arg));
                    }
                    $arg = ${$arg};
                }
             }

            // Get the PHP callable from the registry
    		$callable = self::get($macro_name);

    		$fn = new \ReflectionFunction($callable);

    		$parameters = $fn->getParameters();

            // If there is only one parameter which is an array, then send all arguments in this array.
    		if (1 == count($parameters) && $parameters[0]->isArray()) {
    			return $fn->invokeArgs(array($arguments));
    		}

            // Otherwise, we check all required parameters are sent to the callable.
    		foreach($parameters as $i => $param) {
    			if (!array_key_exists($i, $arguments) && !$param->isOptional()) {
    				throw new \RuntimeException(sprintf("Missing required argument %s '%s' for macro '%s'.", $i+1, $param->getName(), $macro_name));
    			}
    		}

            // And finally, invoke the PHP callable !
            return $fn->invokeArgs($arguments);
		}

	}

}