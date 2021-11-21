<?php
/**
 * Plugin Class.
 *
 * @since 1.0.0
 *
 * @package Tribe\Extensions\Autocreate_RSVP
 */

namespace Tribe\Extensions\Autocreate_RSVP;
use Tribe__Date_Utils as Date;

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
		$this->get_settings();

		// Start binds.
		$this->get_started();

		$this->start_bulk_actions();
		add_action( 'admin_notices', [ $this, 'bulk_add_rsvp_admin_notice' ] );
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
	 */
	private function get_options_prefix() {
		return (string) str_replace( '-', '_', 'tec-labs-auto-create-rsvp' );
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
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
	 */
	public function get_option( $option, $default = '' ) {
		$settings = $this->get_settings();

		return $settings->get_option( $option, $default );
	}

	/**
	 * Check if functionality is enabled and run if yes.
	 */
	public function get_started() {
		$enabled = $this->get_option( 'acr-enable' );
		if ( isset( $enabled ) && ! empty( $enabled ) ) {
			add_action( 'tribe_events_update_meta', [ $this, 'add_custom_RSVP' ], 10, 3 );
		}
	}

	/**
	 * Add and RSVP option to an event.
	 *
	 * @param $event_id integer The post ID of the event.
	 * @param $data     array   Event data.
	 * @param $event    object  The Event object.
	 *
	 * @return bool
	 */
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

		// If we're updating the post, then bail, unless a category is set.
		// (RSVP has been created when the post was first saved.)
		// RSVP creation on update is allowed IF the editor chooses the required category.
		// In this case the category is going to be removed from the post to prevent further automatic creation.
		if ( 'Update' == $data['save'] ) {
			if (
				// Option is not enabled.
				! tribe_is_truthy( $options['acr-enable-on-update'] )
				|| (
					// Option is enabled but there is no category selected.
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

		// If it's a bulk action, then we skip this (so if it's not a bulk action, then we do this),
		// assuming the editor wants to create the RSVP in any case.
		if (
			! $data['bulk_action']
			&& isset( $options['acr-category'] )
			&& ! empty( $options['acr-category'] )
		) {
			// If the required category is not selected for the post, then bail.
			if ( ! in_array( $options['acr-category'], $data['tax_input']['tribe_events_cat'] ) ) {
				return false;
			}
		}

		// Create an RSVP object.
		$rsvp = new \Tribe__Tickets__RSVP();

		// Set the name of the RSVP.
		if (
			! isset ( $options['acr-rsvp-name'] )
			|| empty( $options['acr-rsvp-name'] )
		) {
			$ticket_name = 'RSVP';
		} else {
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

		/**
		 * Filter for the RSVP name.
		 *
		 * @param $ticket_name string The title of the RSVP
		 * @param $data        array  Event data
		 *
		 * @since 1.0.0
		 */
		$ticket_name = apply_filters( 'tec_labs_acr_rsvp_title', $ticket_name, $data );

		// Compile the RSVP data in an array.
		$custom_rsvp_data = [
			'ticket_name'             => esc_html( $ticket_name ),
			'ticket_description'      => $options['acr-rsvp-description'],
			'ticket_show_description' => $options['acr-rsvp-show-description'],
			'tribe-ticket'            => [
				'capacity'  => $options['acr-rsvp-capacity'],
				'not_going' => $options['acr-rsvp-not-going'],
			],
			'ticket_provider'         => 'Tribe__Tickets__RSVP',
		];

		// Create the RSVP.
		if ( $rsvp->ticket_add( $event_id, $custom_rsvp_data ) ) {
			// Add an RSVP block to the post if it doesn't have one yet.
			$this->add_rsvp_block_to_post( $event_id );

			// Remove the category.
			if ( isset( $options['acr-category'] ) && $acr_remove_category ) {
				wp_remove_object_terms( $event_id, (int) $options['acr-category'], 'tribe_events_cat' );
			}

			return true;
		}

		return false;
	}

	/**
	 * Add the markup for the RSVP block to the post.
	 *
	 * @param $post_id string The post ID.
	 *
	 * @return false|int|\WP_Error
	 */
	public function add_rsvp_block_to_post( $post_id ) {
		$content = get_the_content( null, null, $post_id );

		$search = '<!-- wp:tribe/rsvp /-->';

		// Do we have an RSVP block already setup? (we should)
		if ( true === strpos( $content, $search ) ) {
			return false;
		}

		$content .= "\n\r" . $search;

		return wp_update_post( [
			'ID' => $post_id,
			'post_content' => $content,
		] );
	}

	/**
	 * Format the time according to the WordPress setting.
	 *
	 * @param $time string
	 *
	 * @return false|string
	 */
	public function format_time( $time ) {
		$time = is_numeric( $time ) ? $time : strtotime( $time );

		/**
		 * Filter the time format.
		 *
		 * @since 1.0.0
		 */
		$time_pattern = apply_filters( 'tec_labs_acr_time_pattern', get_option( 'time_format' ) );

		return date( $time_pattern, $time );
	}

	/**
	 * Format the date according to the WordPress setting.
	 *
	 * @param $date string
	 *
	 * @return false|string
	 */
	public function format_date( $date ) {
		$date = is_numeric( $date ) ? $date : strtotime( $date );

		/**
		 * Filter the date format.
		 *
		 * @since 1.0.0
		 */
		$date_pattern = apply_filters( 'tec_labs_acr_date_pattern', get_option( 'date_format' ) );

		return date( $date_pattern, $date );
	}

	/**
	 * Add bulk action for the post types selected in the Events > Settings > Tickets settings
	 *
	 * @return bool
	 */
	public function start_bulk_actions() {
		$post_types = tribe_get_option( 'ticket-enabled-post-types' );

		if ( empty( $post_types ) ) {
			return false;
		}

		foreach ( $post_types as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, [ $this, 'bulk_add_rsvp' ] );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, [ $this, 'handle_bulk_add_rsvp' ], 10, 3 );
		}

		return true;
	}

	/**
	 * Add an option to the bulk actions dropdown.
	 *
	 * @param $bulk_actions array The list of the bulk actions.
	 *
	 * @return array
	 */
	function bulk_add_rsvp( $bulk_actions ) {
		$bulk_actions['add_rsvp'] = sprintf(
			__( 'Add %s', 'tec-labs-auto-create-rsvp' ),
			tribe_get_rsvp_label_singular()
		);

		return $bulk_actions;
	}

	/**
	 * Executing the bulk action: add RSVPs to the selected posts.
	 *
	 * @param $redirect_url string The url where the page will redirect after completing the action.
	 * @param $action       string The action.
	 * @param $post_ids     array  A list of the selected post IDs.
	 *
	 * @return string
	 */
	function handle_bulk_add_rsvp( $redirect_url, $action, $post_ids ) {
		if ( $action == 'add_rsvp' ) {
			foreach ( $post_ids as $post_id ) {
				$event_meta = tribe_get_event_meta( $post_id, false, false );
				
				$data                   = [];
				$data['post_title']     = get_the_title( $post_id );
				$data['EventStartDate'] = Date::date_only( $event_meta['_EventStartDate'][0] );
				$data['EventStartTime'] = Date::time_only( $event_meta['_EventStartDate'][0] );
				$data['EventEndDate']   = Date::date_only( $event_meta['_EventEndDate'][0] );
				$data['EventEndTime']   = Date::time_only( $event_meta['_EventEndDate'][0] );
				$data['bulk_action']    = true;

				$this->add_custom_RSVP( $post_id, $data, null );
			}
			$redirect_url = add_query_arg( 'add_rsvp', count( $post_ids ), $redirect_url );
		}

		return $redirect_url;
	}

	/**
	 * Prints and admin notice after completing the bulk action.
	 */
	function bulk_add_rsvp_admin_notice() {
		if ( ! empty( $_REQUEST['add_rsvp'] ) ) {
			// Get the post type object.
			$obj = get_post_type_object( $_REQUEST['post_type'] );

			// Get the labels of the post type.
			$singular_label = $obj->labels->singular_name;
			$plural_label   = $obj->labels->name;

			// Get how many posts were changed.
			$num_changed = (int) $_REQUEST['add_rsvp'];

			$post_label = ( 1 === $num_changed ) ? $singular_label : $plural_label;
			printf(
				'<div id="message" class="updated notice is-dismissible"><p>' .
				/* Translators: %d: Number of posts; %s: Singular or plural label of the post type; */
				__( 'Added RSVP to %d %s.', 'tec-labs-auto-create-rsvp' )
				. '</p></div>',
				$num_changed,
				strtolower( $post_label )
			);
		}
	}

}
