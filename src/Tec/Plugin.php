<?php
/**
 * Plugin Class.
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\Autocreate_RSVP
 */

namespace Tribe\Extensions\Autocreate_RSVP;

/**
 * Class Plugin
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\Autocreate_RSVP
 */
class Plugin extends \tad_DI52_ServiceProvider {
	/**
	 * Stores the version for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Stores the base slug for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const SLUG = 'auto-create-rsvp';

	/**
	 * Stores the base slug for the extension.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const FILE = TRIBE_EXTENSION_AUTO_CREATE_RSVP_FILE;

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
		$this->container->singleton( 'extension.auto_create_rsvp', $this );
		$this->container->singleton( 'extension.auto_create_rsvp.plugin', $this );
		$this->container->register( PUE::class );

		if ( ! $this->check_plugin_dependencies() ) {
			// If the plugin dependency manifest is not met, then bail and stop here.
			return;
		}

		// Do the settings.
		// TODO: Remove if not using settings
		$this->get_settings();

		// Start binds.

		//add_action( 'tribe_events_update_meta', [ $this, 'add_custom_RSVP' ], 10, 3 );
		$this->get_started();

		// End binds.

		$this->container->register( Hooks::class );
		$this->container->register( Assets::class );
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
		$this->container->singleton( 'extension.auto_create_rsvp', $plugin_register );
	}

	/**
	 * Get this plugin's options prefix.
	 *
	 * Settings_Helper will append a trailing underscore before each option.
	 *
	 * @return string
     *
	 * @see \Tribe\Extensions\Autocreate_RSVP\Settings::set_options_prefix()
	 *
	 * TODO: Remove if not using settings
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tec-labs-auto-create-rsvp' );
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

	public function get_started() {
		$enabled = $this->get_option( 'acr-enable' );
		if ( isset( $enabled ) && ! empty( $enabled ) ) {
			add_action( 'tribe_events_update_meta', [ $this, 'add_custom_RSVP' ], 10, 3 );
		}
	}

	public function add_custom_RSVP( $event_id, $data, $event ) {
		$options = $this->get_all_options();

		$backend_enabled = (
			tribe_is_truthy( $options['acr-enable'] )
			|| 'backend' == $options['acr-enable']
			|| 'both' == $options['acr-enable']
		);

		$community_enabled = (
			'community' == $options['acr-enable']
			|| 'both' == $options['acr-enable']
		);

		// If we are publishing a draft or a pending, then bail.
		// RSVP has been created when the draft / pending was saved.
		// It's not the same as an update.
		if (
			(
				'draft' == $data['original_post_status']
				|| 'pending' == $data['original_post_status']
			)
			&& 'publish' == $data['post_status']
		) {
			return false;
		}

		// If it's a backend submission, and it's not enabled then bail.
		if (
			'community-events' != $data['EventOrigin']
			&& ! $backend_enabled
		) {
			return false;
		}

		// If it's a community submission, and it's not enabled then bail.
		if (
			'community-events' == $data['EventOrigin']
			&& ! $community_enabled
		) {
			return false;
		}

		$acr_remove_category = $options['acr-remove-category'];

		// If we're updating the post, then bail.
		if ( 'Update' == $data['save'] ) {
			if (
				// Option is not enabled.
				! tribe_is_truthy( $options['acr-enable-on-update'] )
				|| (
					tribe_is_truthy( $options['acr-enable-on-update'] )
					&& ! is_numeric( $options['acr-category'] )
				)
			) {
				return false;
			}
			else {
				$acr_remove_category = true;
			}
		}

		// If the required category is not selected, then bail.
		if ( isset( $options['acr-category'] ) && ! empty( $options['acr-category'] ) ) {
			if ( ! in_array( $options['acr-category'], $data['tax_input']['tribe_events_cat'] ) ) {
				return false;
			}
		}

		// Create an RSVP object.
		$rsvp = new \Tribe__Tickets__RSVP();

		if ( ! isset ( $options['acr-rsvp-name'] ) || empty( $options['acr-rsvp-name'] ) ) {
			$ticket_name = 'RSVP';
		}
		else {
			$ticket_name = $options['acr-rsvp-name'];
			$search = [
				'{{event-title}}',
				'{{event-start-date}}',
				'{{event-start-time}}',
				'{{event-end-date}}',
				'{{event-end-time}}',
			];
			$replace = [
				$data['post_title'],
				$this->format_date( $data['EventStartDate'] ),
				$this->format_time( $data['EventStartTime'] ),
				$this->format_date( $data['EventEndDate'] ),
				$this->format_time( $data['EventEndTime'] ),
			];
			$ticket_name = str_replace( $search, $replace, $ticket_name );
		}

		$custom_rsvp_data = [
			'ticket_name'             => $ticket_name,
			'ticket_description'      => $options['acr-rsvp-description'],
			'ticket_show_description' => $options['acr-rsvp-show-description'],
			'tribe-ticket'            => [
				'capacity' => $options['acr-rsvp-capacity'],
				'not_going' => $options['acr-rsvp-not-going'],
			],
			'ticket_provider'         => 'Tribe__Tickets__RSVP',
		];

		// Create the RSVP.
		if ( $rsvp->ticket_add( $event_id, $custom_rsvp_data ) ) {
			// Remove the category.
			if ( isset( $options['acr-category'] ) && $acr_remove_category ) {
				$x = wp_remove_object_terms( $event_id, (int) $options['acr-category'], 'tribe_events_cat' );
			}
			return true;
		}

		return false;

	}

	public function format_time( $time ) {
		$time = is_numeric( $time ) ? $time : strtotime( $time );
		$time_pattern = apply_filters( 'tec_labs_acr_time_pattern', get_option( 'time_format' ) );

		return date( $time_pattern, $time );
	}
	
	public function format_date( $date ) {
		$date = is_numeric( $date ) ? $date : strtotime( $date );
		$date_pattern = apply_filters( 'tec_labs_acr_date_pattern', get_option( 'date_format' ) );

		return date( $date_pattern, $date );
	}
}
