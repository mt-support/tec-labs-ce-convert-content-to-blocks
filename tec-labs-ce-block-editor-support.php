<?php
/**
 * Plugin Name:       The Events Calendar: Community Events Extension: Block Editor Support
 * Plugin URI:
 * GitHub Plugin URI:
 * Description:       Add partial Block Editor "support" to The Events Calendar: Community Events. Experimental.
 * Version:           2.4.0
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tec-labs-basic-setup-tool
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


class CE_Block_Editor_Support {

	function __construct() {

		add_filter( 'format_for_editor', [ $this, 'tec_ce_remove_blocks_on_edit' ], 10, 2 );
		add_action( 'tribe_events_update_meta', [ $this, 'tec_ce_convert_content_to_blocks' ], 10, 3 );
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

		// WooCommerce Tickets
		// Check meta table for tickets
		$ttt = tribe( 'tickets.handler' );
		$ticket_ids = $ttt->get_tickets_ids( get_post( $post_id ) );
		// Display the blocks

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
}

new CE_Block_Editor_Support();
