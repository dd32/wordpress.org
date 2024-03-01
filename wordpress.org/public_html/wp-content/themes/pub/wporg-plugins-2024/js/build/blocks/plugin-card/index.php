<?php
/**
 * Block Name: Single Plugin
 * Description: The content that is displayed on the single plugin page
 *
 * @package wporg
 */

namespace WordPressdotorg\Theme\Plugins_2024\PluginCard;

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type( __DIR__ . '/../../../js/build/blocks/plugin-card' );
}

// TODO: Figure out how to add a post_class for wrapping a block...
add_filter( 'render_block_core/post-template', function( $html, $block ) {
	// If the post-template has the 'plugin-cards' class, add the 'plugin-card' class to the child blocks
	if ( ! empty( $block['attrs']['className'] ) && str_contains( $block['attrs']['className'], 'plugin-cards' ) ) {
		$html = str_replace( 'class="wp-block-post ', 'class="wp-block-post plugin-card ', $html );
	}

	return $html;
}, 10, 2 );