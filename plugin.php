<?php
/**
 * Plugin Name:       The Events Calendar: Community Events Extension: Convert Submitted Content to Blocks
 * Plugin URI:        https://theeventscalendar.com/extensions/ce-convert-content-to-blocks
 * GitHub Plugin URI: https://github.com/mt-support/tec-labs-ce-convert-content-to-blocks
 * Description:       Convert the event content submitted through Community Events to block editor format. 
 * Version:           2.6.0-dev
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tec-labs-ce-convert-content-to-blocks
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

/**
 * Define the base file that loaded the plugin for determining plugin path and other variables.
 *
 * @since 1.0.0
 *
 * @var string Base file that loaded the plugin.
 */
define( 'TRIBE_EXTENSION_CE_CONVERT_CONTENT_TO_BLOCKS_FILE', __FILE__ );

/**
 * Register and load the service provider for loading the extension.
 *
 * @since 1.0.0
 */
function tribe_extension_ce_convert_content_to_blocks() {
	// When we don't have autoloader from common we bail.
	if ( ! class_exists( 'Tribe__Autoloader' ) ) {
		return;
	}

	// Register the namespace so we can the plugin on the service provider registration.
	Tribe__Autoloader::instance()->register_prefix(
		'\\Tribe\\Extensions\\ConvertContentToBlocks\\',
		__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Tec',
		'ce-convert-content-to-blocks'
	);

	// Deactivates the plugin in case of the main class didn't autoload.
	if ( ! class_exists( '\Tribe\Extensions\ConvertContentToBlocks\Plugin' ) ) {
		tribe_transient_notice(
			'ce-convert-content-to-blocks',
			'<p>' . esc_html__( 'Couldn\'t properly load "The Events Calendar: Community Events Extension: Convert Submitted Content to Blocks" the extension was deactivated.', 'tec-labs-ce-convert-content-to-blocks' ) . '</p>',
			[],
			// 1 second after that make sure the transient is removed.
			1
		);

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		remove_activation_timestamp();

		deactivate_plugins( __FILE__, true );
		return;
	}

	tribe_register_provider( '\Tribe\Extensions\ConvertContentToBlocks\Plugin' );
}


/**
 *  Function to save activation timestamp in the options table if it doesn't exist.
 *
 * @return void
 */
function save_activation_timestamp() {
	// Check if the option already exists.
	$existing_timestamp = get_option( 'tec_ce_convert_content_to_blocks_cutoff_date' );

	// If the option doesn't exist, save the activation timestamp.
	if ( empty( $existing_timestamp ) ) {
		// Get the current PHP timestamp.
		$activation_timestamp = time();

		// Format the timestamp as "YYYY-MM-DD HH:SS".
		$formatted_timestamp = date( 'Y-m-d H:i:s', $activation_timestamp );

		// Save the activation timestamp in the options table.
		update_option( 'tec_ce_convert_content_to_blocks_cutoff_date', $formatted_timestamp );
	}
}

/**
 * Removing activation timestamp in case the extension fails to be activated.
 * @return void
 */
function remove_activation_timestamp() {
	delete_option( 'tec_ce_convert_content_to_blocks_cutoff_date' );
}

// Loads on plugin activation.
register_activation_hook( __FILE__, 'save_activation_timestamp' );

// Loads after common is already properly loaded.
add_action( 'tribe_common_loaded', 'tribe_extension_ce_convert_content_to_blocks' );
