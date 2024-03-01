<?php

$function        = $attributes['function'] ?? false;
$args            = $attributes['args'] ?? [];
$valid_functions = [
	'the_posts_pagination',
	'the_archive_description'
];

// Validated by block.json enum, but duplicated here.
if ( $function && in_array( $function, $valid_functions ) && function_exists( $function ) ) {
	call_user_func_array( $function, $args );
}