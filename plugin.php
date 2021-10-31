<?php
/**
 * Plugin Name:       Event Tickets Extension: Auto-create RSVP
 * Plugin URI:        https://theeventscalendar.com/extensions/auto-create-rsvp/
 * GitHub Plugin URI: https://github.com/mt-support/tec-labs-auto-create-rsvp
 * Description:       Automatically add an RSVP to an event when it is created.
 * Version:           1.0.0
 * Author:            The Events Calendar
 * Author URI:        https://evnt.is/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tec-labs-auto-create-rsvp
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
define( 'TRIBE_EXTENSION_AUTO_CREATE_RSVP_FILE', __FILE__ );

/**
 * Register and load the service provider for loading the extension.
 *
 * @since 1.0.0
 */
function tribe_extension_auto_create_rsvp() {
	// When we don't have autoloader from common we bail.
	if ( ! class_exists( 'Tribe__Autoloader' ) ) {
		return;
	}

	// Register the namespace so we can the plugin on the service provider registration.
	Tribe__Autoloader::instance()->register_prefix(
		'\\Tribe\\Extensions\\Autocreate_RSVP\\',
		__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Tec',
		'auto-create-rsvp'
	);

	// Deactivates the plugin in case of the main class didn't autoload.
	if ( ! class_exists( '\Tribe\Extensions\Autocreate_RSVP\Plugin' ) ) {
		tribe_transient_notice(
			'auto-create-rsvp',
			'<p>' . esc_html__( 'Couldn\'t properly load "Event Tickets Extension: Auto-create RSVP" the extension was deactivated.', 'tec-labs-auto-create-rsvp' ) . '</p>',
			[],
			// 1 second after that make sure the transient is removed.
			1
		);

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		deactivate_plugins( __FILE__, true );
		return;
	}

	tribe_register_provider( '\Tribe\Extensions\Autocreate_RSVP\Plugin' );
}

// Loads after common is already properly loaded.
add_action( 'tribe_common_loaded', 'tribe_extension_auto_create_rsvp' );
