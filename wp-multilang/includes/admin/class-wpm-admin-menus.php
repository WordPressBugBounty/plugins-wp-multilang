<?php
/**
 * Setup menus in WP admin.
 *
 * @author   Valentyn Riaboshtan
 * @category Admin
 * @package  WPM/Includes/Admin
 */

namespace WPM\Includes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPM_Admin_Menus {

	/**
	 * WPM_Admin_Menus constructor.
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );

		// Add endpoints custom URLs in Appearance > Menus > Pages.
		add_action( 'admin_head-nav-menus.php', array( $this, 'add_nav_menu_meta_boxes' ) );
		add_filter( 'wp_edit_nav_menu_walker', array( $this, 'filter_walker' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'menu_item_languages_setting' ), 5, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'update_nav_menu_item' ), 10, 2 );
	}


	/**
	 * Replace default menu editor walker with ours
	 *
	 * We don't actually replace the default walker. We're still using it and
	 * only injecting some HTMLs.
	 *
	 * @wp_hook filter wp_edit_nav_menu_walker
	 *
	 * @param $class_name
	 *
	 * @return  string Walker class name
	 */
	public static function filter_walker( $class_name ) {
		if ( ! has_action( 'wp_nav_menu_item_custom_fields') ) {
			return 'WPM\Includes\Libraries\WPM_Walker_Nav_Menu_Edit';
		}

		return $class_name;
	}

	/**
	 * Add language switcher to admin
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ) {

		if ( ! get_current_user_id() ) {
			return;
		}

		$installed_translations = wpm_get_installed_languages();

		if ( count( $installed_translations ) <= 1 ) {
			return;
		}

		$user_language          = wpm_get_user_language();
		$languages              = wpm_get_languages();
		$available_translations = wpm_get_available_translations();

		if ( isset( $languages[ $user_language ] ) ) {
			$wp_admin_bar->add_menu( array(
				'id'     => 'wpm-language-switcher',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon">' .
							// phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Reason Using built in function doesn't work in our case, so created custom function
				            ( $languages ? '<img src="' . esc_url( wpm_get_flag_url( $languages[ $user_language ]['flag'] ) ) . '"/>' : '' ) . '</span><span class="ab-label">' . $available_translations[ get_locale() ]['native_name'] . '</span>',
			) );
		}

		foreach ( $installed_translations as $locale ) {

			if ( get_locale() === $locale ) {
				continue;
			}

			$code     = '';
			$language = array();
			$add = false;

			foreach ( $languages as $code => $language ) {
				if ( isset( $language['translation'] ) && ( $language['translation'] == $locale ) ) {
					$add = true;
					break;
				}
			}

			if ( $add ) {
				$native_name 		=	'';
				if( ! empty( $available_translations[$locale]['native_name'] ) ) {
					$native_name 	=	$available_translations[$locale]['native_name'];	
				}

				$wp_admin_bar->add_menu( array(
					'parent' => 'wpm-language-switcher',
					'id'     => 'wpm-language-' . $code,
					// phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Reason Using built in function doesn't work in our case, so created custom function
					'title'  => '<span class="ab-icon">' . '<img src="' . esc_url( wpm_get_flag_url( $language['flag'] ) ) . '" />' . '</span>' . '<span class="ab-label">' . esc_html( $native_name ) . '</span>',
					'href'   => wpm_translate_current_url( $code ),
				) );
			}
		}
	}


	/**
	 * Add menu item.
	 */
	public function settings_menu() {
		$settings_page = add_menu_page( __( 'WP Multilang Settings', 'wp-multilang' ), __( 'WP Multilang', 'wp-multilang' ), 'manage_options', 'wpm-settings', array( $this, 'settings_page' ), 'dashicons-admin-site-alt3' );

		add_action( 'load-' . $settings_page, array( $this, 'settings_page_init' ) );
	}

	/**
	 * Load settings.
	 */
	public function settings_page_init() {
		global $current_tab, $current_section;

		// Include settings pages
		WPM_Admin_Settings::get_settings_pages();

		// Get current tab/section
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Reason unslash not needed because data is not getting stored in database, it's just being used.
		$current_tab     = empty( $_GET['tab'] ) ? 'general' : sanitize_title( $_GET['tab'] );
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Reason unslash not needed because data is not getting stored in database, it's just being used.
		$current_section = empty( $_REQUEST['section'] ) ? '' : sanitize_title( $_REQUEST['section'] );

		// Save settings if data has been posted
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST ) ) {
			WPM_Admin_Settings::save();
		}

		// Add any posted messages
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['wpm_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason unslash not needed because data is not getting stored in database, it's just being used. 
			WPM_Admin_Settings::add_error( stripslashes( $_GET['wpm_error'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['wpm_message'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Reason unslash not needed because data is not getting stored in database, it's just being used. 
			WPM_Admin_Settings::add_message( stripslashes( $_GET['wpm_message'] ) );
		}
	}

	/**
	 * Init the settings page.
	 */
	public function settings_page() {
		WPM_Admin_Settings::output();
	}


	/**
	 * Add custom nav meta box.
	 *
	 * Adapted from http://www.johnmorrisonline.com/how-to-add-a-fully-functional-custom-meta-box-to-wordpress-navigation-menus/.
	 */
	public function add_nav_menu_meta_boxes() {
		add_meta_box( 'wpm_endpoints_nav_link', __( 'Languages', 'wp-multilang' ), array( $this, 'nav_menu_links' ), 'nav-menus', 'side', 'low' );
	}

	/**
	 * Output menu link.
	 */
	public function nav_menu_links() {
		global $_nav_menu_placeholder;
		$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
		?>
		<div id="posttype-wpm-languages" class="posttypediv">
			<div id="tabs-panel-wpm-languages" class="tabs-panel tabs-panel-active">
				<ul id="wpm-languages-checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-object-id]" value="<?php echo esc_attr( $_nav_menu_placeholder ); ?>" /> <?php esc_html_e( 'Languages', 'wp-multilang' ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-type]" value="custom" />
						<input type="hidden" class="menu-item-title" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-title]" value="<?php esc_html_e( 'Languages', 'wp-multilang' ); ?>" />
						<input type="hidden" class="menu-item-url" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-url]" value="#wpm-languages" />
						<input type="hidden" class="menu-item-classes" name="menu-item[<?php echo esc_attr( $_nav_menu_placeholder ); ?>][menu-item-classes]" value="wpm-languages" />
					</li>
				</ul>
			</div>
			<p class="button-controls">
				<span class="add-to-menu">
					<input type="submit" class="button-secondary submit-add-to-menu right" value="<?php echo esc_html__( 'Add to menu', 'wp-multilang' ); ?>" name="add-post-type-menu-item" id="submit-posttype-wpm-languages">
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Add custom fields to menu item.
	 *
	 * @param int $item_id
	 * @param object $item
	 */
	public function menu_item_languages_setting( $item_id, $item ) {

		if ( '#wpm-languages' !== $item->url ) {
			return;
		}

		$_key  = 'languages_type';
		$key   = sprintf( 'menu-item-%s', $_key );
		$id    = sprintf( 'edit-%s-%s', $key, $item_id );
		$name  = sprintf( '%s[%s]', $_key, $item_id );
		$value = get_post_meta( $item_id, '_menu_item_languages_type', true );
		$class = sprintf( 'field-%s', $_key );

		$type_options = array(
			'inline'   => esc_html__( 'Inline', 'wp-multilang' ),
			'single'   => esc_html__( 'Single', 'wp-multilang' ),
			'dropdown' => esc_html__( 'Dropdown', 'wp-multilang' ),
		);
		?>
		<p class="description description-wide <?php echo esc_attr( $class ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Languages menu item type', 'wp-multilang' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( $type_options as $val => $name ) { ?>
					<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $val, $value ) ?>><?php echo esc_html( $name ); ?></option>
				<?php } ?>
			</select>
		</p>
		<?php
		$_key  = 'languages_show';
		$key   = sprintf( 'menu-item-%s', $_key );
		$id    = sprintf( 'edit-%s-%s', $key, $item_id );
		$name  = sprintf( '%s[%s]', $_key, $item_id );
		$value = get_post_meta( $item_id, '_menu_item_languages_show', true );
		$class = sprintf( 'field-%s', $_key );

		$show_options = array(
			'both' => esc_html__( 'Both', 'wp-multilang' ),
			'flag' => esc_html__( 'Flag', 'wp-multilang' ),
			'name' => esc_html__( 'Name', 'wp-multilang' ),
		);
		?>
		<p class="description description-wide <?php echo esc_attr( $class ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Show', 'wp-multilang' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( $show_options as $val => $name ) { ?>
					<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $val, $value ) ?>><?php echo esc_html( $name ); ?></option>
				<?php } ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Add language settings params
	 *
	 * @param $menu_id
	 * @param $menu_item_db_id
	 */
	public function update_nav_menu_item( $menu_id, $menu_item_db_id ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Reason unslash not needed because data is not getting stored in database, it's just being used. 
		if( 'update' !== $_REQUEST['action'] ) {
			return;
		}

		check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

		if ( empty( $_POST['menu-item-url'][ $menu_item_db_id ] ) || '#wpm-languages' !== $_POST['menu-item-url'][ $menu_item_db_id ] ) {
			return;
		}

		if( isset( $_POST['languages_type'] ) && isset( $_POST['languages_type'][ $menu_item_db_id ] ) ) {
			$languages_type = sanitize_text_field( wp_unslash( $_POST['languages_type'][ $menu_item_db_id ] ) );
			update_post_meta( $menu_item_db_id, '_menu_item_languages_type', $languages_type );
		}

		if( isset( $_POST['languages_show'] ) && isset( $_POST['languages_show'][ $menu_item_db_id ] ) ) {
			$languages_show = sanitize_text_field( wp_unslash( $_POST['languages_show'][ $menu_item_db_id ] ) );
			update_post_meta( $menu_item_db_id, '_menu_item_languages_show', $languages_show );
		}
	}
}
