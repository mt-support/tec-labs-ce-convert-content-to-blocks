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
	const VERSION = '2.5.1-dev';

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
		$content = $data['post_content'];

		$post_id = intval( $data['ID'] );

		// Assemble code of blocks
		$blocks['datetime']       = '<!-- wp:tribe/event-datetime /-->';

		// Featured image
		$blocks['featured_image'] = '<!-- wp:tribe/featured-image /-->';

		// Content
		$blocks['content']        = $this->convert_content_to_blocks( $content );

		// Cost
		if ( ! empty( $data['EventCost'] ) || ! empty( tribe_get_event_meta( $post_id, '_EventCost', true ) ) ) {
			$blocks['cost'] = '<!-- wp:tribe/event-price {"costDescription":"This is the price"} /-->';
		}

		// Event website
		$blocks['event_website'] = '<!-- wp:tribe/event-website {"urlLabel":"Button text"} /-->';

		// Organizers
		$organizer_blocks = $this->fetch_organizers( $post_id );
		$blocks = array_merge( $blocks, $organizer_blocks );

		// Venue
		$venue_blocks = $this->fetch_venues( $post_id );
		$blocks = array_merge( $blocks, $venue_blocks );

		// Get the custom fields
		if ( class_exists( 'Tribe__Events__Pro__Main' ) ) {
			$custom_fields = tribe_get_option( 'custom-fields', false );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					$blocks[ $field['name'] ] = '<!-- wp:tribe/field-' . str_replace( '_', '', $field['name'] ) . ' /-->';
				}
			}
		}

		// RSVP
		if (
			class_exists( 'Tribe__Tickets__Main' )
		     && class_exists( 'Tribe__Events__Community__Tickets__Main' )
		) {
			$blocks['rsvp'] = '<!-- wp:tribe/rsvp /-->';
		}

		// Tickets
		if (
			class_exists( 'Tribe__Tickets__Main' )
			&& class_exists( 'Tribe__Tickets_Plus__Main' )
			&& class_exists( 'Tribe__Events__Community__Tickets__Main' )
		) {
			$default_ce_provider = tribe( 'community-tickets.main' )->get_option( 'default_provider_handler', 'TEC_Tickets_Commerce_Module' );

			// Choose handler
			// @todo Check if Tickets Commerce is right.
			if ( $default_ce_provider == 'Tribe__Tickets_Plus__Commerce__WooCommerce__Main' ) {
				$handler = 'tickets-plus.commerce.woo';
			} elseif ( $default_ce_provider == 'TEC_Tickets_Commerce_Module' ) {
				$handler = 'tickets.handler';
			}

			if ( isset( $handler ) ) {
				$ticket_ids = tribe( $handler )->get_tickets_ids( $post_id );

				// Display the blocks
				if ( ! empty( $ticket_ids ) ) {
					// Opening block
					$blocks['tickets'] = '<!-- wp:tribe/tickets --><div class="wp-block-tribe-tickets">';
					foreach ( $ticket_ids as $ticket_id ) {
						// Ticket block
						$blocks['tickets'] .= '<!-- wp:tribe/tickets-item {"hasBeenCreated":true,"ticketId":' . $ticket_id . '} --><div class="wp-block-tribe-tickets-item"></div><!-- /wp:tribe/tickets-item -->';
					}
					// Closing block
					$blocks['tickets'] .= '</div><!-- /wp:tribe/tickets -->';
				}
			}
		}

		// Sharing / Subscribe / Add to Calendar
		$blocks['sharing']       = '<!-- wp:tribe/event-links /-->';

		// Related events
		$blocks['related']       = '<!-- wp:tribe/related-events /-->';

		// Comments
		$blocks['comments']      = '<!-- wp:post-comments-form /-->';

		return implode( "\n", $blocks );
	}

	/**
	 * Grabbing the organizers from the database, so we also have the newly created ones.
	 *
	 * @param int $post_id The post ID of the event that is being created/modified.
	 *
	 * @return array An array of block formatted content.
	 */
	public function fetch_organizers( int $post_id ): array {
		$blocks = [];
		$organizer_ids = get_post_meta( $post_id, '_EventOrganizerID', false );

		if ( ! empty( $organizer_ids ) ) {
			foreach ( $organizer_ids as $organizer_id ) {
				$block_name            = 'organizer_' . $organizer_id;
				$blocks[ $block_name ] = '<!-- wp:tribe/event-organizer {"organizer":' . $organizer_id . '} /-->';
			}
		}

		return $blocks;
	}

	/**
	 * Grabbing the venues from the database, so we also have the newly created ones.
	 *
	 * @param int $post_id The post ID of the event that is being created/modified.
	 *
	 * @return array An array of block formatted content.
	 */
	public function fetch_venues( int $post_id ): array {
		$blocks = [];
		$venue_ids = get_post_meta( $post_id, '_EventVenueID', false );

		if ( ! empty( $venue_ids ) ) {
			foreach ( $venue_ids as $venue_id ) {
				$block_name            = 'venue_' . $venue_id;
				$blocks[ $block_name ] = '<!-- wp:tribe/event-venue {"venue":' . $venue_id . '} /-->';
			}
		}

		return $blocks;
	}

	/**
	 * The cutoff date. Events published before this date should not be converted.
	 *
	 * @todo Make this a setting.
	 *
	 * @return string The cutoff date.
	 */
	protected function cutoff_date() {
		return "2023-06-10 00:00:00";
	}

	/**
	 * Convert content to blocks.
	 *
	 * @param string $content The submitted content.
	 *
	 * @return string The content with block markup.
	 */
	public function convert_content_to_blocks( string $content ) : string {

		// Add a '#$@' separator to the HTML tags for easier splitting.
		$search  = [
			'<p>',
			'<ul>',
			'<ol>',
			'<li>',
			'<code>',
			'<blockquote>',
			'</ul>',
			'</ol>',
			'</li>',
			'</code>',
			'</blockquote>',
		];
		$replace = [
			'#$@<p>',
			'#$@<ul>',
			'#$@<ol>',
			'#$@<li>',
			'#$@<code>',
			'#$@<blockquote>',
			'</ul>#$@',
			'</ol>#$@',
			'</li>#$@',
			'</code>#$@',
			'</blockquote>#$@',
		];

		$content = str_replace( $search, $replace, $content );

		// Add a '#$@' separator to the HTML heading tags for easier splitting.
		// Opening tags
		$pattern = '/<h([1-6])>/';
		$replacement = '#$@<h$1>';
		$content = preg_replace($pattern, $replacement, $content);

		// Closing tags
		$pattern = '/<\/h([1-6])>/';
		$replacement = '</h$1>#$@';
		$content = preg_replace($pattern, $replacement, $content);

		// Split content string into an array based on the '#$@' separator.
		$content_array = explode( "#$@", $content );
		$new_content = [];

		// Iterate through the array and process each item based on the starting/ending tag.
		foreach ( $content_array as $item ) {

			// Trim whitespaces
			$item = trim( $item );

			// Skip empty lines, which come from two separators next to each other.
			if ( empty( $item ) ) {
				continue;
			}

			$start_replacements = [
				'<ul>'         => '<!-- wp:list --><ul>',
				'<ol>'         => '<!-- wp:list {"ordered":true} --><ol>',
				'<li>'         => '<!-- wp:list-item --><li>',
				'<p>'          => '<!-- wp:paragraph --><p>',
				'<h1>'         => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">',
				'<h2>'         => '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">',
				'<h3>'         => '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">',
				'<h4>'         => '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">',
				'<h5>'         => '<!-- wp:heading {"level":5} --><h5 class="wp-block-heading">',
				'<h6>'         => '<!-- wp:heading {"level":6} --><h6 class="wp-block-heading">',
				'<blockquote>' => '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>',
				'<code>'       => '<!-- wp:code --><pre class="wp-block-code"><code>',
			];

			// Go through each replacement
			foreach ( $start_replacements as $search => $replace ) {
				if ( str_starts_with( $item, $search ) ) {
					$item = str_replace( $search, $replace, $item );
					// For paragraphs and blockquotes replace line breaks.
					if ( $search == '<p>' || $search == '<blockquote>' ) {
						$item = $this->maybe_replace_linebreaks( $item );
					}
					break;
				}
			}

			$end_replacements = [
				'</li>'         => '</li><!-- /wp:list-item -->',
				'</p>'          => '</p><!-- /wp:paragraph -->',
				'</ul>'         => '</ul><!-- /wp:list -->',
				'</ol>'         => '</ol><!-- /wp:list -->',
				'</h1>'         => '</h1><!-- /wp:heading -->',
				'</h2>'         => '</h2><!-- /wp:heading -->',
				'</h3>'         => '</h3><!-- /wp:heading -->',
				'</h4>'         => '</h4><!-- /wp:heading -->',
				'</h5>'         => '</h5><!-- /wp:heading -->',
				'</h6>'         => '</h6><!-- /wp:heading -->',
				'</blockquote>' => '</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
				'</code>'       => '</code></pre><!-- /wp:code -->',
			];

			// Go through each replacement
			foreach ( $end_replacements as $search => $replace ) {
				if ( str_ends_with( $item, $search ) ) {
					$item = str_replace( $search, $replace, $item );
					break;
				}
			}

			// If it's not any kind of block, make it a paragraph.
			if (
				! str_starts_with( $item, '<!-- wp:' )
				&& ! str_ends_with( $item, '-->' )
			) {
				$item = '<!-- wp:paragraph --><p>' . $item . '</p><!-- /wp:paragraph -->';
				$item = $this->maybe_replace_linebreaks( $item );
			}

			$new_content[] = $item;
		}

		// Assemble the new content with the block code.
		$content = implode( "\n", $new_content );

		return $content;
	}

	/**
	 * Try to replace the line breaks with paragraphs or <br>s.
	 *
	 * @param string $string The string to check for line breaks.
	 *
	 * @return string The string with block markup.
	 */
	function maybe_replace_linebreaks( string $string ) : string {
		return str_replace( [ "\r\n\r\n", "\r\n" ], [ "</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>", "<br>" ], $string );
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
