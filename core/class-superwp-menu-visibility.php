<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HELPER COMMENT START
 * 
 * This is the main class that is responsible for registering
 * the core functions, including the files and setting up all features. 
 * 
 * To add a new class, here's what you need to do: 
 * 1. Add your new class within the following folder: core/includes/classes
 * 2. Create a new variable you want to assign the class to (as e.g. public $helpers)
 * 3. Assign the class within the instance() function ( as e.g. self::$instance->helpers = new Superwp_Menu_Visibility_Helpers();)
 * 4. Register the class you added to core/includes/classes within the includes() function
 * 
 * HELPER COMMENT END
 */

if ( ! class_exists( 'Superwp_Menu_Visibility' ) ) :

	/**
	 * Main Superwp_Menu_Visibility Class.
	 *
	 * @package		SUPERWPMEN
	 * @subpackage	Classes/Superwp_Menu_Visibility
	 * @since		1.0.05
	 * @author		Thiarara
	 */
	final class Superwp_Menu_Visibility {

		/**
		 * The real instance
		 *
		 * @access	private
		 * @since	1.0.05
		 * @var		object|Superwp_Menu_Visibility
		 */
		private static $instance;

		/**
		 * SUPERWPMEN helpers object.
		 *
		 * @access	public
		 * @since	1.0.05
		 * @var		object|Superwp_Menu_Visibility_Helpers
		 */
		public $helpers;

		/**
		 * SUPERWPMEN settings object.
		 *
		 * @access	public
		 * @since	1.0.05
		 * @var		object|Superwp_Menu_Visibility_Settings
		 */
		public $settings;

		/**
		 * Throw error on object clone.
		 *
		 * Cloning instances of the class is forbidden.
		 *
		 * @access	public
		 * @since	1.0.05
		 * @return	void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'You are not allowed to clone this class.', 'superwp-menu-visibility' ), '1.0.05' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @access	public
		 * @since	1.0.05
		 * @return	void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'You are not allowed to unserialize this class.', 'superwp-menu-visibility' ), '1.0.05' );
		}

		/**
		 * Main Superwp_Menu_Visibility Instance.
		 *
		 * Insures that only one instance of Superwp_Menu_Visibility exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @access		public
		 * @since		1.0.05
		 * @static
		 * @return		object|Superwp_Menu_Visibility	The one true Superwp_Menu_Visibility
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Superwp_Menu_Visibility ) ) {
				self::$instance					= new Superwp_Menu_Visibility;
				self::$instance->base_hooks();
				self::$instance->includes();
				self::$instance->helpers		= new Superwp_Menu_Visibility_Helpers();
				self::$instance->settings		= new Superwp_Menu_Visibility_Settings();

				//Fire the plugin logic
				new Superwp_Menu_Visibility_Run();

				/**
				 * Fire a custom action to allow dependencies
				 * after the successful plugin setup
				 */
				do_action( 'SUPERWPMEN/plugin_loaded' );
			}

			return self::$instance;
		}

		/**
		 * Include required files.
		 *
		 * @access  private
		 * @since   1.0.05
		 * @return  void
		 */
		private function includes() {
			require_once SUPERWPMEN_PLUGIN_DIR . 'core/includes/classes/class-superwp-menu-visibility-helpers.php';
			require_once SUPERWPMEN_PLUGIN_DIR . 'core/includes/classes/class-superwp-menu-visibility-settings.php';

			require_once SUPERWPMEN_PLUGIN_DIR . 'core/includes/classes/class-superwp-menu-visibility-run.php';
		}

		/**
		 * Add base hooks for the core functionality
		 *
		 * @access  private
		 * @since   1.0.05
		 * @return  void
		 */
		private function base_hooks() {
			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @access  public
		 * @since   1.0.05
		 * @return  void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'superwp-menu-visibility', FALSE, dirname( plugin_basename( SUPERWPMEN_PLUGIN_FILE ) ) . '/languages/' );
		}

	}
endif; // End if class_exists check.