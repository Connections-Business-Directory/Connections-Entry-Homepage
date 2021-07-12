<?php
/**
 * An extension for the Connections Business Directory which add a field which can be used to define the directory homepage of an entry.
 *
 * @package   Connections Business Directory Extension - Entry Homepage
 * @category  Extension
 * @author    Steven A. Zahm
 * @license   GPL-2.0+
 * @link      https://connections-pro.com
 * @copyright 2021 Steven A. Zahm
 *
 * @wordpress-plugin
 * Plugin Name:       Connections Business Directory Extension - Entry Homepage
 * Plugin URI:        https://connections-pro.com
 * Description:       An extension for the Connections Business Directory which add a field which can be used to define the directory homepage of an entry.
 * Version:           1.0
 * Author:            Steven A. Zahm
 * Author URI:        https://connections-pro.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       connections-entry-homepage
 * Domain Path:       /languages
 */

if ( ! class_exists( 'Connections_Entry_Homepage' ) ) {

	final class Connections_Entry_Homepage {

		const VERSION = '1.0';

		/**
		 * @var string The absolute path this this file.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $file = '';

		/**
		 * @var string The URL to the plugin's folder.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $url = '';

		/**
		 * @var string The absolute path to this plugin's folder.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $path = '';

		/**
		 * @var string The basename of the plugin.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $basename = '';

		/**
		 * Stores the instance of this class.
		 *
		 * @var $instance Connections_Entry_Homepage
		 *
		 * @access private
		 * @static
		 * @since  1.0
		 */
		private static $instance;

		/**
		 * A dummy constructor to prevent the class from being loaded more than once.
		 *
		 * @access public
		 * @since  1.0
		 */
		public function __construct() { /* Do nothing here */ }

		/**
		 * The main plugin instance.
		 *
		 * @access  private
		 * @static
		 * @since   1.0
		 * @return object self
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Connections_Entry_Homepage ) ) {

				self::$file       = __FILE__;
				self::$url        = plugin_dir_url( self::$file );
				self::$path       = plugin_dir_path( self::$file );
				self::$basename   = plugin_basename( self::$file );

				self::$instance = new Connections_Entry_Homepage;

				// This should run on the `plugins_loaded` action hook. Since the extension loads on the
				// `plugins_loaded action hook, call immediately.
				self::loadTextdomain();

				// Register the metabox and fields.
				add_action( 'cn_metabox', array( __CLASS__, 'registerMetabox') );

				// Entry Homepage uses a custom field type, so let's add the action to add it.
				add_action( 'cn_meta_field-entry_homepage', array( __CLASS__, 'field' ), 10, 2 );

				// Since we're using a custom field, we need to add our own sanitization method.
				add_filter( 'cn_meta_sanitize_field-entry_homepage', array( __CLASS__, 'sanitize') );

				add_filter( 'cn_permalink', array( __CLASS__, 'permalink' ), 10, 2 );

				// Set the shortcode `home_id` option to the value saved for the entry or use the default value.
				//add_filter( 'cn_entry_directory_homepage', array( __CLASS__, 'setShortcodeOptionValue' ), 10, 2 );
				add_filter( 'cn_list_atts_permitted', array( __CLASS__, 'setShortcodeOptionValue' ), 10 );
			}

			return self::$instance;
		}

		/**
		 * Load the plugin translation.
		 *
		 * Credit: Adapted from Ninja Forms / Easy Digital Downloads.
		 *
		 * @access private
		 * @since  1.0
		 */
		public static function loadTextdomain() {

			// Plugin textdomain. This should match the one set in the plugin header.
			$domain = 'connections-entry-homepage';

			// Set filter for plugin's languages directory
			$languagesDirectory = apply_filters( "cn_{$domain}_languages_directory", CN_DIR_NAME . '/languages/' );

			// Traditional WordPress plugin locale filter
			$locale   = apply_filters( 'plugin_locale', get_locale(), $domain );
			$fileName = sprintf( '%1$s-%2$s.mo', $domain, $locale );

			// Setup paths to current locale file
			$local  = $languagesDirectory . $fileName;
			$global = WP_LANG_DIR . "/{$domain}/" . $fileName;

			if ( file_exists( $global ) ) {

				// Look in global `../wp-content/languages/{$domain}/` folder.
				load_textdomain( $domain, $global );

			} elseif ( file_exists( $local ) ) {

				// Look in local `../wp-content/plugins/{plugin-directory}/languages/` folder.
				load_textdomain( $domain, $local );

			} else {

				// Load the default language files
				load_plugin_textdomain( $domain, FALSE, $languagesDirectory );
			}
		}

		public static function registerMetabox() {

			$atts = array(
				'id'       => 'entry-homepage',
				'title'    => __( 'Entry Directory Homepage', 'connections-entry-homepage' ),
				'context'  => 'side',
				'priority' => 'core',
				'fields'   => array(
					array(
						'id'    => 'entry_homepage',
						'type'  => 'entry_homepage',
					),
				),
			);

			cnMetaboxAPI::add( $atts );
		}

		public static function field( $field, $value ) {

			?>
			<div class="cn-wp-page-select">
				<style type="text/css" scoped>
					select.cn-wp-page-select {
						width: 100%;
					}
				</style>
			<?php
			wp_dropdown_pages(
				array(
					'name'                  => 'entry_homepage',
					'class'                 => 'cn-wp-page-select',
					'echo'                  => 1,
					'show_option_none'      => __( 'Global Directory Homepage', 'connections-entry-homepage' ),
					'option_none_value'     => '0',
					'show_option_no_change' => '',
					'selected'              => $value,
				)
			);

			printf(
				'<p class="description"> %1$s</p>',
				esc_html__(
					'Choose the page to be used as the directory homepage for this entry (ie. page URL used when the Back to Directory link is clicked on the single entry profile page). This will override the directory homepage set on the Settings page.',
					'connections-entry-homepage'
				)
			);

			?>
			</div>
			<?php
		}

		/**
		 * Sanitize the times as a text input using the cnSanitize class.
		 *
		 * @access private
		 * @since  1.0
		 *
		 * @param int $value
		 *
		 * @return int
		 */
		public static function sanitize( $value ) {

			return absint( $value );
		}

		/**
		 * Callback for the `cn_permalink` filter.
		 *
		 * @todo This should hook into the cn_entry_permalink filter instead but core need updated to use the cnEntry::permalink() helper function first.
		 * @todo Add option to select an internal or eternal page. When internal page display a page list drop down.
		 * @todo Add option to choose whether the link should be open in same window or new.
		 * @todo These options will require a couple filters be add to core. One in each of cnEntry::getPermalink(), cnOutput::getNameBlock() and cnOutput::permalink().
		 *
		 * @param string $permalink
		 * @param array  $atts
		 *
		 * @return string
		 */
		public static function permalink( $permalink, $atts ) {

			if ( ! isset( $atts['type'] ) || 'name' !== $atts['type'] ) {

				return $permalink;
			}

			$entry = Connections_Directory()->retrieve->entry( $atts['slug'] );

			if ( FALSE != $entry ) {

				$meta = cnMeta::get( 'entry', $entry->id, 'entry_homepage', TRUE );

				if ( ! empty( $meta ) ) {

					$maybePermalink = get_permalink( $meta );

					if ( is_string( $maybePermalink ) ) {

						$permalink = $maybePermalink;
					}
				}
			}

			return $permalink;
		}

		/**
		 * Callback for the `cn_list_atts_permitted` filter.
		 *
		 * @access private
		 * @since  1.0
		 *
		 * @param array   $atts
		 * @param cnEntry $entry
		 *
		 * @return mixed
		 */
		public static function setShortcodeOptionValue( $atts/*, $entry*/ ) {

			if ( ! $slug = cnQuery::getVar( 'cn-entry-slug' ) ) {

				return $atts;
			}

			$entry = Connections_Directory()->retrieve->entry( $slug );

			if ( FALSE != $entry ) {

				$pageID = cnMeta::get( 'entry', $entry->id, 'entry_homepage', TRUE );

				if ( 0 < $pageID ) {

					$atts['home_id'] = $pageID;
				}
			}

			// home_id=1464
			//$pageID = $entry->getMeta(
			//	array(
			//		'key'    => 'entry_homepage',
			//		'single' => TRUE,
			//	)
			//);

			//if ( 0 < $pageID ) {
			//
			//	$atts['page_id'] = $pageID;
			//}

			return $atts;
		}
	}

	/**
	 * Start up the extension.
	 *
	 * @access                public
	 * @since                 1.0
	 * @return mixed (object)|(bool)
	 */
	function Connections_Entry_Homepage() {

		if ( class_exists( 'connectionsLoad' ) ) {

			return Connections_Entry_Homepage::instance();

		} else {

			add_action(
				'admin_notices',
				function() {
					echo '<div id="message" class="error"><p><strong>ERROR:</strong> Connections must be installed and active in order use Connections Entry Homepage.</p></div>';
				}
			);

			return FALSE;
		}
	}

	/**
	 * We'll load the extension on `plugins_loaded` so we know Connections will be loaded and ready first.
	 */
	add_action( 'plugins_loaded', 'Connections_Entry_Homepage' );
}
