<?php
/**
 * Settings Object.
 *
 * @package Tribe\Extensions\Autocreate_RSVP
 * @since   1.0.0
 *
 */

namespace Tribe\Extensions\Autocreate_RSVP;

use Tribe__Settings_Manager;

/**
 * Do the Settings.
 */
class Settings {

	/**
	 * The Settings Helper class.
	 *
	 * @var Settings_Helper
	 */
	protected $settings_helper;

	/**
	 * The prefix for our settings keys.
	 *
	 * @see get_options_prefix() Use this method to get this property's value.
	 *
	 * @var string
	 */
	private $options_prefix = '';

	/**
	 * Settings constructor.
	 *
	 * @param string $options_prefix Recommended: the plugin text domain, with hyphens converted to underscores.
	 */
	public function __construct( $options_prefix ) {
		$this->settings_helper = new Settings_Helper();

		$this->set_options_prefix( $options_prefix );

		// Add settings specific to OSM
		add_action( 'admin_init', [ $this, 'add_settings' ] );
	}

	/**
	 * Allow access to set the Settings Helper property.
	 *
	 * @see get_settings_helper()
	 *
	 * @param Settings_Helper $helper
	 *
	 * @return Settings_Helper
	 */
	public function set_settings_helper( Settings_Helper $helper ) {
		$this->settings_helper = $helper;

		return $this->get_settings_helper();
	}

	/**
	 * Allow access to get the Settings Helper property.
	 *
	 * @see set_settings_helper()
	 */
	public function get_settings_helper() {
		return $this->settings_helper;
	}

	/**
	 * Set the options prefix to be used for this extension's settings.
	 *
	 * Recommended: the plugin text domain, with hyphens converted to underscores.
	 * Is forced to end with a single underscore. All double-underscores are converted to single.
	 *
	 * @see get_options_prefix()
	 *
	 * @param string $options_prefix
	 */
	private function set_options_prefix( $options_prefix = '' ) {
		if ( empty( $opts_prefix ) ) {
			$opts_prefix = str_replace( '-', '_', 'tec-labs-auto-create-rsvp' ); // The text domain.
		}

		$opts_prefix = $opts_prefix . '_';

		$this->options_prefix = str_replace( '__', '_', $opts_prefix );
	}

	/**
	 * Get this extension's options prefix.
	 *
	 * @see set_options_prefix()
	 *
	 * @return string
	 */
	public function get_options_prefix() {
		return $this->options_prefix;
	}

	/**
	 * Given an option key, get this extension's option value.
	 *
	 * This automatically prepends this extension's option prefix so you can just do `$this->get_option( 'a_setting' )`.
	 *
	 * @see tribe_get_option()
	 *
	 * @param string $key
	 * @param string $default
	 *
	 * @return mixed
	 */
	public function get_option( $key = '', $default = '' ) {
		$key = $this->sanitize_option_key( $key );

		return tribe_get_option( $key, $default );
	}

	/**
	 * Get an option key after ensuring it is appropriately prefixed.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function sanitize_option_key( $key = '' ) {
		$prefix = $this->get_options_prefix();

		if ( 0 === strpos( $key, $prefix ) ) {
			$prefix = '';
		}

		return $prefix . $key;
	}

	/**
	 * Get an array of all of this extension's options without array keys having the redundant prefix.
	 *
	 * @return array
	 */
	public function get_all_options() {
		$raw_options = $this->get_all_raw_options();

		$result = [];

		$prefix = $this->get_options_prefix();

		foreach ( $raw_options as $key => $value ) {
			$abbr_key            = str_replace( $prefix, '', $key );
			$result[ $abbr_key ] = $value;
		}

		return $result;
	}

	/**
	 * Get an array of all of this extension's raw options (i.e. the ones starting with its prefix).
	 *
	 * @return array
	 */
	public function get_all_raw_options() {
		$tribe_options = Tribe__Settings_Manager::get_options();

		if ( ! is_array( $tribe_options ) ) {
			return [];
		}

		$result = [];

		foreach ( $tribe_options as $key => $value ) {
			if ( 0 === strpos( $key, $this->get_options_prefix() ) ) {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Given an option key, delete this extension's option value.
	 *
	 * This automatically prepends this extension's option prefix so you can just do `$this->delete_option( 'a_setting' )`.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function delete_option( $key = '' ) {
		$key = $this->sanitize_option_key( $key );

		$options = Tribe__Settings_Manager::get_options();

		unset( $options[ $key ] );

		return Tribe__Settings_Manager::set_options( $options );
	}

	/**
	 * Adds a new section of fields to Events > Settings > General tab, appearing after the "Map Settings" section
	 * and before the "Miscellaneous Settings" section.
	 */
	public function add_settings() {
		$event_categories = $this->get_event_categories();

		$fields0 = [
			'acr-heading' => [
				'type' => 'html',
				'html' => $this->get_acr_heading(),
			],
		];

		if ( class_exists( '\Tribe__Events__Community__Tickets__Main' ) ) {
			$fields1 = [
				'acr-enable' => [
					'type'            => 'dropdown',
					'label'           => esc_html__( 'Enable', 'tec-labs-default-ticket-fieldset' ),
					'tooltip'         => esc_html_x( 'Choose where you want to enable automatic RSVP creation.', 'Setting description', 'tec-labs-default-ticket-fieldset' ),
					'validation_type' => 'options',
					'options'         => $this->acr_enable_options(),
				],
			];
		} else {
			$fields1 = [
				'acr-enable' => [
					'type'            => 'checkbox_bool',
					'label'           => esc_html__( 'Enable', 'tec-labs-auto-create-rsvp' ),
					'tooltip'         => esc_html__( 'When enabled an RSVP will be automatically created for the event when the event is created.', 'tec-labs-auto-create-rsvp' ),
					'validation_type' => 'boolean',
					'default'         => false,
				],
			];
		}

		$fields2 = [
			'acr-category'               => [
				'type'            => 'dropdown',
				'label'           => esc_html__( 'Limit to category', 'tec-labs-default-ticket-fieldset' ),
				'tooltip'         => esc_html_x( 'You can limit adding the RSVP to events that are created in the above selected category only.', 'Setting description', 'tec-labs-default-ticket-fieldset' ),
				'validation_type' => 'options',
				'options'         => $event_categories,
			],
			'acr-remove-category'        => [
				'type'            => 'checkbox_bool',
				'label'           => esc_html__( 'Remove category after creation', 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => esc_html__( 'By default the category is not removed from the event after it is created. Enable this to remove the category.', 'tec-labs-auto-create-rsvp' ),
				'validation_type' => 'boolean',
				'default'         => false,
			],
			'acr-enable-on-update'       => [
				'type'            => 'checkbox_bool',
				'label'           => esc_html__( 'Create an RSVP on Event Update', 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => esc_html__( 'By default an RSVP is created only when a new event is created. With this option an RSVP will also be created when an event is updated. For safety, this option only works with category limitation.', 'tec-labs-auto-create-rsvp' ),
				'validation_type' => 'boolean',
				'default'         => false,
			],
			'acr-divider'                => [
				'type' => 'html',
				'html' => '<hr>',
			],
			'acr-default-values-heading' => [
				'type' => 'html',
				'html' => '<p>' . esc_html__( 'You can define the values for the automatically created RSVP here.', 'tec-labs-auto-create-rsvp' ) . '</p>',
			],
			'acr-rsvp-name'              => [
				'type'            => 'text',
				'label'           => esc_html__( 'RSVP name', 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => $this->acr_rsvp_name_tooltip(),
				'validation_type' => 'html',
				'size'            => 'large',
				'can_be_empty'    => true,
			],
			'acr-rsvp-description'       => [
				'type'            => 'textarea',
				'label'           => esc_html__( 'Description', 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => esc_html__( 'This is the description of your RSVP.', 'tec-labs-auto-create-rsvp' ),
				'validation_type' => 'textarea',
			],
			'acr-rsvp-show-descripition' => [
				'type'            => 'checkbox_bool',
				'label'           => esc_html__( 'Show description', 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => esc_html__( 'Show description of frontend ticket form.', 'tec-labs-auto-create-rsvp' ),
				'validation_type' => 'boolean',
				'default'         => true,
			],
			'acr-rsvp-capacity'          => [
				'type'            => 'text',
				'label'           => esc_html__( 'Capacity', 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => esc_html__( 'Leave blank for unlimited', 'tec-labs-auto-create-rsvp' ),
				'validation_type' => 'positive_int',
				'size'            => 'small',
				'can_be_empty'    => true,
			],
			'acr-rsvp-not-going'         => [
				'type'            => 'checkbox_bool',
				'label'           => esc_html__( "Can't Go", 'tec-labs-auto-create-rsvp' ),
				'tooltip'         => esc_html__( 'Enable "Can\'t Go" responses', 'tec-labs-auto-create-rsvp' ),
				'validation_type' => 'boolean',
				'default'         => false,
			],
		];

		$fields = array_merge( $fields0, $fields1, $fields2 );

		$this->settings_helper->add_fields(
			$this->prefix_settings_field_keys( $fields ),
			'event-tickets',
			'ticket-attendee-page-id',
			false
		);
	}

	/**
	 * Add the options prefix to each of the array keys.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	private function prefix_settings_field_keys( array $fields ) {
		$prefixed_fields = array_combine(
			array_map(
				function ( $key ) {
					return $this->get_options_prefix() . $key;
				}, array_keys( $fields )
			),
			$fields
		);

		return (array) $prefixed_fields;
	}

	/**
	 * Here is an example of getting some HTML for the Settings Header.
	 *
	 * @return string
	 */
	private function get_acr_heading() {
		$result = '<h3 id="tec-labs-auto-create-rsvp-settings">' . esc_html_x( 'Auto-create RSVP', 'Settings header', 'tec-labs-auto-create-rsvp' ) . '</h3>';
		$result .= '<div style="margin-left: 20px;">';
		$result .= '<p>';
		$result .= esc_html_x( 'Enable this if you would like to add an RSVP automatically to an event, when the event is created.', 'Setting section description', 'tec-labs-auto-create-rsvp' );
		$result .= '</p>';
		$result .= '</div>';

		return $result;
	}

	private function acr_enable_options() {
		$dropdown = [
			''          => 'Disable',
			'backend'   => 'Backend only',
			'community' => 'Community submissions only',
			'both'      => 'Backend and Community submissions',
		];

		return $dropdown;
	}

	private function acr_rsvp_name_tooltip() {
		$html = esc_html__( 'This is the name of your RSVP. It is displayed on the frontend of your website and within RSVP emails. If left empty "RSVP" will be used.', 'tec-labs-auto-create-rsvp' );
		$html .= '<br>' . esc_html__( 'You can also use the following placeholders:', '' ) . '<br>';
		$html .= '<code>{{event-title}}</code>, ';
		$html .= '<code>{{event-start-date}}</code>, ';
		$html .= '<code>{{event-start-time}}</code>, ';
		$html .= '<code>{{event-end-date}}</code>, ';
		$html .= '<code>{{event-end-time}}</code>';

		return $html;
	}

	private function get_event_categories() {
		$args       = [
			'taxonomy'   => 'tribe_events_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];
		$categories = get_categories( $args );

		$dropdown = [ '' => '(All events)' ];

		foreach ( $categories as $category ) {
			$dropdown[ $category->cat_ID ] = $category->cat_name;
		}

		return $dropdown;
	}

}
