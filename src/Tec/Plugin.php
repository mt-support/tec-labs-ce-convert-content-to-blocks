<?php
/**
 * Plugin Class.
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\ConvertContentToBlocks
 */

namespace Tribe\Extensions\ConvertContentToBlocks;

use TEC\Common\Contracts\Service_Provider;
use WP_Post;

/**
 * Class Plugin
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\ConvertContentToBlocks
 */
class Plugin extends Service_Provider {
	/**
	 * Stores the version for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const VERSION = '2.5.0';

	/**
	 * Stores the base slug for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const SLUG = 'ce-convert-content-to-blocks';

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TRIBE_EXTENSION_CE_CONVERT_CONTENT_TO_BLOCKS_FILE;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin Directory.
	 */
	public $plugin_dir;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin path.
	 */
	public $plugin_path;

	/**
	 * @since 1.0.0
	 *
	 * @var string Plugin URL.
	 */
	public $plugin_url;

	/**
	 * @since 1.0.0
	 *
	 * @var Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private $settings;

	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Set up the plugin provider properties.
		$this->plugin_path = trailingslashit( dirname( static::FILE ) );
		$this->plugin_dir  = trailingslashit( basename( $this->plugin_path ) );
		$this->plugin_url  = plugins_url( $this->plugin_dir, $this->plugin_path );

		// Register this provider as the main one and use a bunch of aliases.
		$this->container->singleton( static::class, $this );
		$this->container->singleton( 'extension.ce_convert_content_to_blocks', $this );
		$this->container->singleton( 'extension.ce_convert_content_to_blocks.plugin', $this );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Do the settings.
		// TODO: Remove if not using settings
		$this->get_settings();

		// Start binds.

		add_filter( 'format_for_editor', [ $this, 'tec_ce_remove_blocks_on_edit' ], 10, 2 );
		add_action( 'tribe_events_update_meta', [ $this, 'tec_ce_convert_content_to_blocks' ], 10, 3 );

		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
	}

	/**
	 * Grab the original content for the content editor.
	 *
	 * @param string $text           The text to be formatted.
	 * @param string $default_editor The default editor for the current user.
	 *                               It is usually either 'html' or 'tinymce'
	 *
	 * @return string
	 */
	function tec_ce_remove_blocks_on_edit( string $text, string $default_editor ): string {

		global $post;

		$community_slug = tribe( 'community.main' )->getOption( 'communityRewriteSlug', 'community' );
		$edit_slug      = tribe( 'community.main' )->getOption( 'community-edit-slug', 'edit' );

		if ( strpos( $_SERVER['REQUEST_URI'], $community_slug . "/" . $edit_slug ) ) {

			// If it's an old post don't convert.
			if ( $post->post_date < $this->cutoff_date() ) {
				return $text;
			}

			$content = get_post_meta( $post->ID, '_original_content', true );

			if ( ! $content ) {
				return $this->maybe_hack_block_text( $text );
			} else {
				return $content;
			}
		}

		return $text;
	}

	/**
	 * Takes the content with block editor markup and tries to filter out the main content.
	 *
	 * @param string $text The text with block editor formatting.
	 *
	 * @return string The content without block editor markup.
	 */
	function maybe_hack_block_text( string $text ): string {
		$start     = 'wp:paragraph {"placeholder":"Add Description..."}';
		$end       = "/wp:paragraph";
		$start_pos = strpos( $text, $start );
		$end_pos   = strrpos( $text, $end );

		if ( $start_pos !== false && $end_pos !== false ) {
			// +8 is the length of "<-- " and " -->"
			$start_pos += strlen( $start ) + 8;
			// +8 is the length of "<-- "
			$end_pos -= 8;
			$length  = $end_pos - $start_pos;

			return substr( $text, $start_pos, $length );
		} else {
			return $text;
		}
	}

	/**
	 * Convert the submitted event data to blocks.
	 *
	 * @param int     $event_id The event ID we are modifying meta for.
	 * @param array   $data     The meta fields we want saved.
	 * @param WP_Post $event    The event itself.
	 *
	 * @return void
	 */
	function tec_ce_convert_content_to_blocks( int $event_id, array $data, WP_Post $event ): void {
		// Bail if it's not a Community Event edit.
		if ( ! isset( $_REQUEST['community-event'] ) ) {
			return;
		}

		// Bail if visual editor for Community Events is not enabled.
		if ( ! tribe( 'community.main' )->getOption( 'useVisualEditor' ) ) {
			return;
		}

		// Bail if post is an old one.
		if ( $event->post_date < $this->cutoff_date() ) {
			return;
		}

		// Save the original content in a postmeta
		update_post_meta( $event_id, '_original_content', $data['post_content'] );

		// Change the post content to block editor.
		$postarr = [
			'ID'           => $event_id,
			'post_content' => $this->convert_to_blocks( $data ),
		];

		wp_update_post( $postarr );
	}

	/**
	 * Helper function to transform the content into block editor format with a given pattern.
	 *
	 * @param array $data The submitted event data.
	 *
	 * @return string The content reformatted for block editor.
	 */
	function convert_to_blocks( array $data ): string {
		$content = str_replace( [ "\n\n", "\n" ], [ "</p><p>", "<br>" ], $data['post_content'] );

		$post_id = intval( $data['ID'] );
		// Get the custom fields
		$custom_fields = tribe_get_option( 'custom-fields', false );

		// Assemble code of blocks
		$blocks['datetime']       = '<!-- wp:tribe/event-datetime /-->';

		// Cost
		if ( ! empty( tribe_get_event_meta( $data['ID'], '_EventCost', true ) ) ) {
			$blocks['cost'] = '<!-- wp:tribe/event-price {"costDescription":"This is the price"} /-->';
		}

		// Featured image
		$blocks['featured_image'] = '<!-- wp:tribe/featured-image /-->';

		// Content
		$blocks['content_start']  = '<!-- wp:paragraph {"placeholder":"Add Description..."} -->';
		$blocks['content']        = '<p>' . $content . '</p>';
		$blocks['content_end']    = '<!-- /wp:paragraph -->';

		// Custom fields
		if ( $custom_fields ) {
			foreach ( $custom_fields as $field ) {
				$blocks[ $field['name'] ] = '<!-- wp:tribe/field-' . str_replace( '_', '', $field['name'] ) . ' /-->';
			}
		}

		// Organizers
		// Grabbing the organizers from the database so we also have the newly created ones.
		$organizers = tribe_get_organizers( false, - 1, true, [ 'event' => $data['ID'] ] );
		if ( ! empty( $organizers ) ) {
			foreach ( $organizers as $organizer ) {
				$block_name            = 'organizer_' . $organizer->ID;
				$blocks[ $block_name ] = '<!-- wp:tribe/event-organizer {"organizer":' . $organizer->ID . '} /-->';
			}
		}

		// Venue
		$blocks['venue']         = '<!-- wp:tribe/event-venue /-->';

		// Event website
		$blocks['event_website'] = '<!-- wp:tribe/event-website {"urlLabel":"Button text"} /-->';

		// Sharing / Subscribe / Add to Calendar
		$blocks['sharing']       = '<!-- wp:tribe/event-links /-->';

		// Related events
		$blocks['related']       = '<!-- wp:tribe/related-events /-->';

		// Comments
		$blocks['comments']      = '<!-- wp:post-comments-form /-->';

		// Tickets
		$default_ce_provider = tribe( 'community-tickets.main' )->get_option( 'default_provider_handler', 'TEC_Tickets_Commerce_Module' );
		if ( $default_ce_provider == 'Tribe__Tickets_Plus__Commerce__WooCommerce__Main' ) {
			$handler = 'tickets-plus.commerce.woo';
		}
		elseif ( $default_ce_provider == 'TEC_Tickets_Commerce_Module' ) {
			$handler = 'tickets.handler';
		}

		if ( isset( $handler ) ) {
			$ticket_ids = tribe( $handler )->get_tickets_ids( $post_id );

			// Display the blocks
			if ( ! empty( $ticket_ids ) ) {
				// Opening block
				$blocks['tickets'] = '<!-- wp:tribe/tickets -->
<div class="wp-block-tribe-tickets">';
				foreach ( $ticket_ids as $ticket_id ) {
					// Ticket block
					$blocks['tickets'] .= '<!-- wp:tribe/tickets-item {"hasBeenCreated":true,"ticketId":' . $ticket_id . '} -->
<div class="wp-block-tribe-tickets-item"></div>
<!-- /wp:tribe/tickets-item -->';
				}
				// Closing block
				$blocks['tickets'] .= '</div>
<!-- /wp:tribe/tickets -->';
			}
		}

		return implode( "\n", $blocks );
	}

	/**
	 * The cutoff date. Events published before this date should not be converted.
	 *
	 * @return string The cutoff date.
	 */
	protected function cutoff_date() {
		return "2023-06-10 00:00:00";
	}

	/**
	 * Checks whether the plugin dependency manifest is satisfied or not.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the plugin dependency manifest is satisfied or not.
	 */
	protected function check_plugin_dependencies() {
		$this->register_plugin_dependencies();

		return tribe_check_plugin( static::class );
	}

	/**
	 * Registers the plugin and dependency manifest among those managed by Tribe Common.
	 *
	 * @since 1.0.0
	 */
	protected function register_plugin_dependencies() {
		$plugin_register = new Plugin_Register();
		$plugin_register->register_plugin();

		$this->container->singleton( Plugin_Register::class, $plugin_register );
		$this->container->singleton( 'extension.ce_convert_content_to_blocks', $plugin_register );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @return string
     *
	 * @see \Tribe\Extensions\ConvertContentToBlocks\Settings::set_options_prefix()
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tec-labs-ce-convert-content-to-blocks' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->settings = new Settings( $this->get_options_prefix() );
		}

		return $this->settings;
	}

	/**
	 * Get all of this extension's options.
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_all_options() {
		$settings = $this->get_settings();

		return $settings->get_all_options();
	}

	/**
	 * Get a specific extension option.
	 *
	 * @param $option
	 * @param string $default
	 *
	 * @return array
	 *
	 * TODO: Remove if not using settings
	 */
	public function get_option( $option, $default = '' ) {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}
}
