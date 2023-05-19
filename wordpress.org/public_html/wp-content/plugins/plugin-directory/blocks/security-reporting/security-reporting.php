<?php

namespace WordPressdotorg\Plugin_Directory\Blocks\Security_Reporting;

add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Registers the block
 *
 * @codeCoverageIgnore
 */
function register_block() {
	register_block_type( __DIR__ . '/build' );
}
