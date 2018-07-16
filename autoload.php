<?php

/**
 * Class autoloader.
 *
 * From:
 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md#class-example
 *
 * @param string $class The fully-qualified class name.
 *
 * @return void
 */
function epg_autoloader( $class ) {

	$maps = array(
		'Ethereumico\\Epg\\' => __DIR__ . '/src/',
	);

	foreach ( $maps as $prefix => $base_dir ) {
	    // does the class use the namespace prefix?
	    $len = strlen( $prefix );
	    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
	        // no, move to the next registered autoloader
	        continue;
	    }
	    // Get the relative class name.
	    $relative_class = substr( $class, $len );

	    // Replace the namespace prefix with the base directory, replace namespace
	    // separators with directory separators in the relative class name, append
	    // with .php
	    $file = $base_dir . str_replace( '\\', '/', strtolower( $relative_class ) ) . '.php';

	    // if the file exists, require it
	    if ( file_exists( $file ) ) {
	        require $file;
	        return;
	    }
	}
}
spl_autoload_register('epg_autoloader');
