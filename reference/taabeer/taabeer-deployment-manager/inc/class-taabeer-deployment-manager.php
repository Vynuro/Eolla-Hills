<?php
/**
 * GitHub release deployment manager.
 *
 * @package TaabeerDeployment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Taabeer_Deployment_Manager {
	const OPTION_SETTINGS = 'taabeer_deployment_settings';
	const OPTION_TOKEN    = 'taabeer_deployment_github_token';
	const OPTION_LAST     = 'taabeer_deployment_last_release';
	const OPTION_BASELINE = 'taabeer_deployment_theme_baseline';
	const TRANSIENT_CHECK = 'taabeer_deployment_release_check';
	const CRON_HOOK       = 'taabeer_deployment_scheduled_check';
	const THEME_SLUG      = 'taabeer';

	/** @var self|null */
	private static $instance = null;

	/**
	 * Return the shared manager instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_taabeer_deployment_save', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_taabeer_deployment_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_taabeer_deployment_check', array( $this, 'handle_check' ) );
		add_action( 'admin_post_taabeer_deployment_install', array( $this, 'handle_install' ) );
		add_action( self::CRON_HOOK, array( $this, 'scheduled_check' ) );
		add_action( 'admin_notices', array( $this, 'update_available_notice' ) );
		add_action( 'admin_init', array( $this, 'ensure_theme_baseline' ), 5 );
		add_action( 'admin_init', array( $this, 'complete_initial_setup' ), 30 );
	}

	/** Schedule quiet background checks. */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', self::CRON_HOOK );
		}
		self::instance()->ensure_theme_baseline();
	}

	/** Remove scheduled work when the plugin is disabled. */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function register_admin_page() {
		add_management_page(
			__( 'TAABEER Updates', 'taabeer-deployment' ),
			__( 'TAABEER Updates', 'taabeer-deployment' ),
			'update_themes',
			'taabeer-updates',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Repository settings. Constants override database values for production.
	 */
	private function settings() {
		$saved = wp_parse_args(
			(array) get_option( self::OPTION_SETTINGS, array() ),
			array(
				'repository'  => '',
				'release_tag' => 'client-preview',
			)
		);

		if ( defined( 'TAABEER_UPDATER_REPOSITORY' ) ) {
			$saved['repository'] = (string) TAABEER_UPDATER_REPOSITORY;
		}
		if ( defined( 'TAABEER_UPDATER_RELEASE_TAG' ) ) {
			$saved['release_tag'] = (string) TAABEER_UPDATER_RELEASE_TAG;
		}

		return $saved;
	}

	private function token() {
		if ( defined( 'TAABEER_UPDATER_GITHUB_TOKEN' ) ) {
			return trim( (string) TAABEER_UPDATER_GITHUB_TOKEN );
		}
		$stored = (string) get_option( self::OPTION_TOKEN, '' );
		return $stored ? $this->decrypt_token( $stored ) : '';
	}

	private function encrypt_token( $token ) {
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 'sodium:' . base64_encode( $nonce . sodium_crypto_secretbox( $token, $nonce, $key ) );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $token, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false === $cipher ? '' : 'openssl:' . base64_encode( $iv . $tag . $cipher );
		}
		return '';
	}

	private function decrypt_token( $stored ) {
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		if ( str_starts_with( $stored, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$decoded = base64_decode( substr( $stored, 7 ), true );
			if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
			return false === $plain ? '' : trim( $plain );
		}
		if ( str_starts_with( $stored, 'openssl:' ) && function_exists( 'openssl_decrypt' ) ) {
			$decoded = base64_decode( substr( $stored, 8 ), true );
			if ( false === $decoded || strlen( $decoded ) <= 28 ) {
				return '';
			}
			$plain = openssl_decrypt( substr( $decoded, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $decoded, 0, 12 ), substr( $decoded, 12, 16 ) );
			return false === $plain ? '' : trim( $plain );
		}
		return '';
	}

	private function github_headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'TAABEER-Deployment-Manager/' . TAABEER_DEPLOYMENT_VERSION,
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( $this->token() ) {
			$headers['Authorization'] = 'Bearer ' . $this->token();
		}
		return $headers;
	}

	private function sanitize_repository( $repository ) {
		$repository = trim( sanitize_text_field( $repository ) );
		if ( preg_match( '~^https?://github\.com/([^/]+/[^/?#]+)~i', $repository, $match ) ) {
			$repository = $match[1];
		}
		$repository = preg_replace( '/\.git$/i', '', rtrim( $repository, '/' ) );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository ) ? $repository : '';
	}

	/**
	 * Check a fixed GitHub release for the signed theme manifest.
	 *
	 * @param bool $force Ignore the short cache.
	 * @return array|WP_Error
	 */
	public function check_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::TRANSIENT_CHECK );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$settings   = $this->settings();
		$repository = $this->sanitize_repository( $settings['repository'] );
		$tag        = sanitize_text_field( $settings['release_tag'] );
		if ( ! $repository || ! $tag ) {
			return new WP_Error( 'taabeer_repository_missing', __( 'Add the GitHub repository and release channel first.', 'taabeer-deployment' ) );
		}

		$release_url = sprintf(
			'https://api.github.com/repos/%1$s/releases/tags/%2$s',
			$repository,
			rawurlencode( $tag )
		);
		$response = wp_safe_remote_get(
			$release_url,
			array(
				'headers'     => $this->github_headers(),
				'timeout'     => 20,
				'redirection' => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) ) {
			$message = is_array( $data ) && ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : __( 'GitHub did not return the configured release.', 'taabeer-deployment' );
			return new WP_Error( 'taabeer_github_release', $message );
		}

		$manifest_asset = $this->find_asset( $data, 'taabeer-update.json' );
		if ( ! $manifest_asset ) {
			return new WP_Error( 'taabeer_manifest_missing', __( 'The release does not contain taabeer-update.json.', 'taabeer-deployment' ) );
		}

		$manifest_response = wp_safe_remote_get(
			$manifest_asset['url'],
			array(
				'headers'     => $this->github_headers( 'application/octet-stream' ),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);
		if ( is_wp_error( $manifest_response ) ) {
			return $manifest_response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $manifest_response ) ) {
			return new WP_Error( 'taabeer_manifest_download', __( 'The update manifest could not be downloaded.', 'taabeer-deployment' ) );
		}

		$manifest = $this->validate_manifest( json_decode( wp_remote_retrieve_body( $manifest_response ), true ) );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		$package_asset = $this->find_asset( $data, $manifest['package_asset'] );
		if ( ! $package_asset ) {
			return new WP_Error( 'taabeer_package_missing', __( 'The release package named in the manifest is missing.', 'taabeer-deployment' ) );
		}

		$theme                  = wp_get_theme( self::THEME_SLUG );
		$result                 = $manifest;
		$result['package_url']  = esc_url_raw( $package_asset['url'] );
		$result['checked_at']   = current_time( 'mysql' );
		$result['current']      = $theme->exists() ? $theme->get( 'Version' ) : '0.0.0';
		$result['available']    = version_compare( $result['version'], $result['current'], '>' );
		$result['release_name'] = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $tag;

		set_site_transient( self::TRANSIENT_CHECK, $result, 15 * MINUTE_IN_SECONDS );
		return $result;
	}

	private function find_asset( $release, $name ) {
		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			if ( isset( $asset['name'], $asset['url'] ) && hash_equals( (string) $name, (string) $asset['name'] ) ) {
				return $asset;
			}
		}
		return null;
	}

	private function validate_manifest( $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return new WP_Error( 'taabeer_manifest_json', __( 'The update manifest is not valid JSON.', 'taabeer-deployment' ) );
		}
		foreach ( array( 'version', 'theme', 'package_asset', 'sha256' ) as $field ) {
			if ( empty( $manifest[ $field ] ) || ! is_string( $manifest[ $field ] ) ) {
				return new WP_Error( 'taabeer_manifest_field', sprintf( __( 'The manifest is missing %s.', 'taabeer-deployment' ), $field ) );
			}
		}
		if ( self::THEME_SLUG !== $manifest['theme'] ) {
			return new WP_Error( 'taabeer_manifest_theme', __( 'The manifest targets a different theme.', 'taabeer-deployment' ) );
		}
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $manifest['version'] ) ) {
			return new WP_Error( 'taabeer_manifest_version', __( 'The manifest version is invalid.', 'taabeer-deployment' ) );
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/i', $manifest['sha256'] ) ) {
			return new WP_Error( 'taabeer_manifest_hash', __( 'The manifest checksum is invalid.', 'taabeer-deployment' ) );
		}
		$manifest['version']       = sanitize_text_field( $manifest['version'] );
		$manifest['theme']         = sanitize_key( $manifest['theme'] );
		$manifest['package_asset'] = sanitize_file_name( $manifest['package_asset'] );
		$manifest['sha256']        = strtolower( sanitize_text_field( $manifest['sha256'] ) );
		$manifest['notes']         = isset( $manifest['notes'] ) ? sanitize_textarea_field( $manifest['notes'] ) : '';
		$manifest['commit']        = isset( $manifest['commit'] ) ? sanitize_text_field( $manifest['commit'] ) : '';
		$manifest['built_at']      = isset( $manifest['built_at'] ) ? sanitize_text_field( $manifest['built_at'] ) : '';
		return $manifest;
	}

	public function scheduled_check() {
		$this->check_release( true );
	}

	public function handle_save_settings() {
		$this->authorize( 'taabeer_deployment_save' );
		$repository = isset( $_POST['repository'] ) ? $this->sanitize_repository( wp_unslash( $_POST['repository'] ) ) : '';
		$tag        = isset( $_POST['release_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['release_tag'] ) ) : 'client-preview';
		if ( ! $repository ) {
			$this->redirect_with_message( 'error', __( 'Use the repository format owner/repository.', 'taabeer-deployment' ) );
		}
		update_option( self::OPTION_SETTINGS, array( 'repository' => $repository, 'release_tag' => $tag ?: 'client-preview' ) );
		$token = isset( $_POST['github_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) ) : '';
		if ( $token && ! defined( 'TAABEER_UPDATER_GITHUB_TOKEN' ) ) {
			$encrypted = $this->encrypt_token( $token );
			if ( ! $encrypted ) {
				$this->redirect_with_message( 'error', __( 'This server cannot securely encrypt the GitHub token. Configure it in wp-config.php instead.', 'taabeer-deployment' ) );
			}
			update_option( self::OPTION_TOKEN, $encrypted, false );
		}
		delete_site_transient( self::TRANSIENT_CHECK );
		$this->redirect_with_message( 'success', __( 'GitHub connection saved. Use Check GitHub to verify it.', 'taabeer-deployment' ) );
	}

	public function handle_disconnect() {
		$this->authorize( 'taabeer_deployment_disconnect' );
		if ( defined( 'TAABEER_UPDATER_REPOSITORY' ) || defined( 'TAABEER_UPDATER_GITHUB_TOKEN' ) ) {
			$this->redirect_with_message( 'error', __( 'The connection is defined in wp-config.php and must be removed there.', 'taabeer-deployment' ) );
		}
		delete_option( self::OPTION_SETTINGS );
		delete_option( self::OPTION_TOKEN );
		delete_site_transient( self::TRANSIENT_CHECK );
		$this->redirect_with_message( 'success', __( 'GitHub was disconnected. Installed theme files and site content were not changed.', 'taabeer-deployment' ) );
	}

	public function handle_check() {
		$this->authorize( 'taabeer_deployment_check' );
		$result = $this->check_release( true );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'error', $result->get_error_message() );
		}
		$message = $result['available']
			? sprintf( __( 'TAABEER %s is ready to install.', 'taabeer-deployment' ), $result['version'] )
			: __( 'The installed theme is up to date.', 'taabeer-deployment' );
		$this->redirect_with_message( 'success', $message );
	}

	public function handle_install() {
		$this->authorize( 'taabeer_deployment_install' );
		$result = $this->check_release( true );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'error', $result->get_error_message() );
		}
		if ( empty( $result['available'] ) ) {
			$this->redirect_with_message( 'error', __( 'There is no newer theme version to install.', 'taabeer-deployment' ) );
		}

		$package = $this->download_package( $result );
		if ( is_wp_error( $package ) ) {
			$this->redirect_with_message( 'error', $package->get_error_message() );
		}

		$validation = $this->validate_package( $package, $result );
		if ( is_wp_error( $validation ) ) {
			@unlink( $package );
			$this->redirect_with_message( 'error', $validation->get_error_message() );
		}

		$conflicts = $this->find_local_theme_conflicts( $package );
		if ( is_wp_error( $conflicts ) ) {
			@unlink( $package );
			$this->redirect_with_message( 'error', $conflicts->get_error_message() );
		}
		if ( $conflicts ) {
			@unlink( $package );
			$this->redirect_with_message(
				'error',
				sprintf(
					__( 'Update stopped because it would overwrite live theme-file changes: %s. Add those changes to the repository first.', 'taabeer-deployment' ),
					implode( ', ', array_slice( $conflicts, 0, 5 ) )
				)
			);
		}

		$installed_theme = wp_get_theme( self::THEME_SLUG );
		$first_install   = ! $installed_theme->exists();
		$backup          = '';
		if ( ! $first_install ) {
			$backup = $this->backup_current_theme( $result['current'] );
			if ( is_wp_error( $backup ) ) {
				@unlink( $package );
				$this->redirect_with_message( 'error', $backup->get_error_message() );
			}
		}

		$was_active = self::THEME_SLUG === get_stylesheet();
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$installed = $upgrader->install(
			$package,
			array(
				'clear_update_cache' => true,
				'overwrite_package'  => true,
			)
		);
		@unlink( $package );

		if ( is_wp_error( $installed ) ) {
			$this->redirect_with_message( 'error', $installed->get_error_message() );
		}
		if ( ! $installed ) {
			$this->redirect_with_message( 'error', __( 'WordPress could not install the theme package. The backup was retained.', 'taabeer-deployment' ) );
		}
		if ( $was_active || $first_install ) {
			switch_theme( self::THEME_SLUG );
		}
		$this->record_theme_baseline( $result['version'] );
		if ( $first_install ) {
			update_option( 'taabeer_pending_initial_setup', 'yes' );
		}

		update_option(
			self::OPTION_LAST,
			array(
				'from'       => $result['current'],
				'to'         => $result['version'],
				'commit'     => $result['commit'],
				'installed'  => current_time( 'mysql' ),
				'backup'     => $backup ? basename( $backup ) : '',
			)
		);
		update_option( 'taabeer_pending_code_update', $result['version'] );
		delete_site_transient( self::TRANSIENT_CHECK );
		do_action( 'taabeer_deployment_completed', $result['current'], $result['version'] );
		$this->redirect_with_message( 'success', sprintf( __( 'TAABEER %s was installed. Elementor content and WordPress data were preserved.', 'taabeer-deployment' ), $result['version'] ) );
	}

	/**
	 * Complete a fresh installation after WordPress has loaded the new theme.
	 */
	public function complete_initial_setup() {
		if ( 'yes' !== get_option( 'taabeer_pending_initial_setup' ) || self::THEME_SLUG !== get_stylesheet() ) {
			return;
		}
		if ( function_exists( 'taabeer_run_demo_import' ) ) {
			taabeer_run_demo_import();
			delete_option( 'taabeer_pending_initial_setup' );
		}
	}

	private function download_package( $release ) {
		$temp = wp_tempnam( $release['package_asset'] );
		if ( ! $temp ) {
			return new WP_Error( 'taabeer_temp_file', __( 'WordPress could not create a temporary update file.', 'taabeer-deployment' ) );
		}
		$response = wp_safe_remote_get(
			$release['package_url'],
			array(
				'headers'     => $this->github_headers( 'application/octet-stream' ),
				'timeout'     => 120,
				'redirection' => 5,
				'stream'      => true,
				'filename'    => $temp,
			)
		);
		if ( is_wp_error( $response ) ) {
			@unlink( $temp );
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			@unlink( $temp );
			return new WP_Error( 'taabeer_package_download', __( 'The theme package could not be downloaded from GitHub.', 'taabeer-deployment' ) );
		}
		if ( ! hash_equals( strtolower( $release['sha256'] ), strtolower( hash_file( 'sha256', $temp ) ) ) ) {
			@unlink( $temp );
			return new WP_Error( 'taabeer_package_checksum', __( 'The downloaded package failed checksum verification.', 'taabeer-deployment' ) );
		}
		return $temp;
	}

	private function validate_package( $package, $release ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'taabeer_zip_missing', __( 'The server needs the PHP Zip extension before remote deployment can be used.', 'taabeer-deployment' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $package ) ) {
			return new WP_Error( 'taabeer_zip_open', __( 'The downloaded theme ZIP could not be opened.', 'taabeer-deployment' ) );
		}

		$required = array( 'taabeer/style.css', 'taabeer/functions.php', 'taabeer/theme.json' );
		foreach ( $required as $file ) {
			if ( false === $zip->locateName( $file, ZipArchive::FL_NOCASE ) ) {
				$zip->close();
				return new WP_Error( 'taabeer_zip_structure', __( 'The package is not a valid TAABEER theme archive.', 'taabeer-deployment' ) );
			}
		}
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = (string) $zip->getNameIndex( $index );
			if ( str_contains( $name, '../' ) || str_starts_with( $name, '/' ) || ! str_starts_with( $name, 'taabeer/' ) ) {
				$zip->close();
				return new WP_Error( 'taabeer_zip_path', __( 'The package contains an unsafe file path.', 'taabeer-deployment' ) );
			}
		}
		$style = (string) $zip->getFromName( 'taabeer/style.css' );
		$zip->close();
		if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*([^\r\n]+)/mi', $style, $match ) || trim( $match[1] ) !== $release['version'] ) {
			return new WP_Error( 'taabeer_zip_version', __( 'The package version does not match the update manifest.', 'taabeer-deployment' ) );
		}
		return true;
	}

	/**
	 * Record the current theme files so later live code edits can be detected.
	 */
	public function ensure_theme_baseline() {
		if ( get_option( self::OPTION_BASELINE ) ) {
			return;
		}
		$theme = wp_get_theme( self::THEME_SLUG );
		if ( $theme->exists() ) {
			$this->record_theme_baseline( $theme->get( 'Version' ) );
		}
	}

	private function record_theme_baseline( $version ) {
		$directory = get_theme_root( self::THEME_SLUG ) . '/' . self::THEME_SLUG;
		$files     = $this->theme_file_manifest( $directory );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		update_option(
			self::OPTION_BASELINE,
			array(
				'version'     => sanitize_text_field( (string) $version ),
				'recorded_at' => current_time( 'mysql' ),
				'files'       => $files,
			),
			false
		);
		return true;
	}

	private function theme_file_manifest( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return new WP_Error( 'taabeer_manifest_directory', __( 'The installed TAABEER theme directory could not be read.', 'taabeer-deployment' ) );
		}
		$files    = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$path     = $file->getRealPath();
			$relative = str_replace( '\\', '/', substr( $path, strlen( $directory ) + 1 ) );
			if ( str_starts_with( $relative, '.git/' ) || '.DS_Store' === basename( $relative ) ) {
				continue;
			}
			$hash = hash_file( 'sha256', $path );
			if ( false === $hash ) {
				return new WP_Error( 'taabeer_manifest_file', sprintf( __( 'The theme file %s could not be read.', 'taabeer-deployment' ), $relative ) );
			}
			$files[ $relative ] = $hash;
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Return live file edits that are not present in the incoming package.
	 *
	 * A changed live file is safe when the incoming repository package contains
	 * the exact same content, allowing a developer to commit the live correction
	 * before deploying the next release.
	 */
	private function find_local_theme_conflicts( $package ) {
		$theme = wp_get_theme( self::THEME_SLUG );
		if ( ! $theme->exists() ) {
			return array();
		}

		$baseline = get_option( self::OPTION_BASELINE, array() );
		if ( empty( $baseline['files'] ) || ! is_array( $baseline['files'] ) ) {
			$recorded = $this->record_theme_baseline( $theme->get( 'Version' ) );
			return is_wp_error( $recorded ) ? $recorded : array();
		}

		$current = $this->theme_file_manifest( $theme->get_stylesheet_directory() );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $package ) ) {
			return new WP_Error( 'taabeer_conflict_zip', __( 'The incoming package could not be inspected for live-file conflicts.', 'taabeer-deployment' ) );
		}

		$paths     = array_unique( array_merge( array_keys( $baseline['files'] ), array_keys( $current ) ) );
		$conflicts = array();
		foreach ( $paths as $relative ) {
			$before = $baseline['files'][ $relative ] ?? null;
			$now    = $current[ $relative ] ?? null;
			if ( $before === $now ) {
				continue;
			}

			$incoming = $zip->getFromName( self::THEME_SLUG . '/' . $relative );
			if ( null === $now ) {
				if ( false !== $incoming ) {
					$conflicts[] = $relative;
				}
				continue;
			}
			if ( false === $incoming || ! hash_equals( $now, hash( 'sha256', $incoming ) ) ) {
				$conflicts[] = $relative;
			}
		}
		$zip->close();
		return $conflicts;
	}

	private function backup_current_theme( $version ) {
		$theme = wp_get_theme( self::THEME_SLUG );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'taabeer_theme_missing', __( 'The TAABEER theme is not installed.', 'taabeer-deployment' ) );
		}
		$directory = WP_CONTENT_DIR . '/taabeer-update-backups';
		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'taabeer_backup_directory', __( 'The protected backup directory could not be created.', 'taabeer-deployment' ) );
		}
		if ( ! file_exists( $directory . '/index.php' ) ) {
			file_put_contents( $directory . '/index.php', "<?php\n// Silence is golden.\n" );
			file_put_contents( $directory . '/.htaccess', "Deny from all\n" );
		}

		$backup = sprintf(
			'%1$s/taabeer-%2$s-%3$s.zip',
			$directory,
			preg_replace( '/[^A-Za-z0-9_.-]/', '-', (string) $version ),
			gmdate( 'Ymd-His' )
		);
		return $this->zip_directory( $theme->get_stylesheet_directory(), $backup );
	}

	private function zip_directory( $source, $destination ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'taabeer_backup_zip', __( 'The PHP Zip extension is required to create the safety backup.', 'taabeer-deployment' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'taabeer_backup_open', __( 'The theme backup archive could not be created.', 'taabeer-deployment' ) );
		}
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$path = $file->getRealPath();
			$zip->addFile( $path, self::THEME_SLUG . '/' . substr( $path, strlen( $source ) + 1 ) );
		}
		$zip->close();
		return $destination;
	}

	private function authorize( $nonce_action ) {
		if ( ! current_user_can( 'update_themes' ) ) {
			wp_die( esc_html__( 'You are not allowed to deploy theme updates.', 'taabeer-deployment' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private function redirect_with_message( $type, $message ) {
		set_transient(
			'taabeer_deployment_message_' . get_current_user_id(),
			array( 'type' => $type, 'message' => sanitize_text_field( $message ) ),
			60
		);
		wp_safe_redirect( admin_url( 'tools.php?page=taabeer-updates' ) );
		exit;
	}

	private function output_message() {
		$key     = 'taabeer_deployment_message_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! $message ) {
			return;
		}
		delete_transient( $key );
		$class = 'success' === $message['type'] ? 'notice-success' : 'notice-error';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message['message'] ) );
	}

	public function update_available_notice() {
		if ( ! current_user_can( 'update_themes' ) || isset( $_GET['page'] ) && 'taabeer-updates' === $_GET['page'] ) {
			return;
		}
		$release = get_site_transient( self::TRANSIENT_CHECK );
		if ( is_array( $release ) && ! empty( $release['available'] ) ) {
			printf(
				'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html( sprintf( __( 'TAABEER theme %s is ready.', 'taabeer-deployment' ), $release['version'] ) ),
				esc_url( admin_url( 'tools.php?page=taabeer-updates' ) ),
				esc_html__( 'Review and install', 'taabeer-deployment' )
			);
		}
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'update_themes' ) ) {
			return;
		}
		$settings = $this->settings();
		$release  = get_site_transient( self::TRANSIENT_CHECK );
		$theme    = wp_get_theme( self::THEME_SLUG );
		$last     = get_option( self::OPTION_LAST, array() );
		$this->output_message();
		?>
		<div class="wrap taabeer-deployment">
			<h1><?php esc_html_e( 'TAABEER Updates', 'taabeer-deployment' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Deploy reviewed theme releases from GitHub without replacing Elementor content or WordPress data.', 'taabeer-deployment' ); ?></p>

			<div class="taabeer-deployment__grid">
				<section class="taabeer-deployment__card">
					<p class="taabeer-deployment__eyebrow"><?php esc_html_e( 'Installed theme', 'taabeer-deployment' ); ?></p>
					<h2><?php echo esc_html( $theme->exists() ? 'TAABEER ' . $theme->get( 'Version' ) : __( 'Not installed', 'taabeer-deployment' ) ); ?></h2>
					<p><?php esc_html_e( 'Pages, Elementor layouts, products, orders and settings remain in the database. Direct live theme-file edits are checked before code is replaced.', 'taabeer-deployment' ); ?></p>
					<?php if ( $last ) : ?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Last deployment: %1$s to %2$s on %3$s.', 'taabeer-deployment' ), $last['from'], $last['to'], $last['installed'] ) ); ?></p>
					<?php endif; ?>
				</section>

				<section class="taabeer-deployment__card">
					<p class="taabeer-deployment__eyebrow"><?php esc_html_e( 'Release status', 'taabeer-deployment' ); ?></p>
					<?php if ( is_array( $release ) ) : ?>
						<h2><?php echo esc_html( $release['available'] ? sprintf( __( 'Version %s available', 'taabeer-deployment' ), $release['version'] ) : __( 'Up to date', 'taabeer-deployment' ) ); ?></h2>
						<?php if ( ! empty( $release['notes'] ) ) : ?><p><?php echo nl2br( esc_html( $release['notes'] ) ); ?></p><?php endif; ?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Checked %s. Commit %s.', 'taabeer-deployment' ), $release['checked_at'], $release['commit'] ? substr( $release['commit'], 0, 12 ) : '—' ) ); ?></p>
					<?php else : ?>
						<h2><?php esc_html_e( 'Not checked yet', 'taabeer-deployment' ); ?></h2>
						<p><?php esc_html_e( 'Save the repository, then check the configured release channel.', 'taabeer-deployment' ); ?></p>
					<?php endif; ?>

					<div class="taabeer-deployment__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="taabeer_deployment_check">
							<?php wp_nonce_field( 'taabeer_deployment_check' ); ?>
							<button class="button" type="submit"><?php esc_html_e( 'Check GitHub', 'taabeer-deployment' ); ?></button>
						</form>
						<?php if ( is_array( $release ) && ! empty( $release['available'] ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="taabeer_deployment_install">
								<?php wp_nonce_field( 'taabeer_deployment_install' ); ?>
						<button class="button button-primary" type="submit"><?php echo esc_html( $theme->exists() ? sprintf( __( 'Back up and install %s', 'taabeer-deployment' ), $release['version'] ) : sprintf( __( 'Install and activate %s', 'taabeer-deployment' ), $release['version'] ) ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				</section>
			</div>

			<section class="taabeer-deployment__settings">
				<h2><?php esc_html_e( 'GitHub connection', 'taabeer-deployment' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="taabeer_deployment_save">
					<?php wp_nonce_field( 'taabeer_deployment_save' ); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><label for="taabeer-repository"><?php esc_html_e( 'Repository link', 'taabeer-deployment' ); ?></label></th><td><input class="regular-text" id="taabeer-repository" name="repository" value="<?php echo esc_attr( $settings['repository'] ); ?>" placeholder="https://github.com/owner/repository" <?php disabled( defined( 'TAABEER_UPDATER_REPOSITORY' ) ); ?> required><p class="description"><?php esc_html_e( 'Paste the full GitHub link or use owner/repository.', 'taabeer-deployment' ); ?></p></td></tr>
						<tr><th scope="row"><label for="taabeer-release-tag"><?php esc_html_e( 'Release channel', 'taabeer-deployment' ); ?></label></th><td><input class="regular-text" id="taabeer-release-tag" name="release_tag" value="<?php echo esc_attr( $settings['release_tag'] ); ?>" placeholder="client-preview" <?php disabled( defined( 'TAABEER_UPDATER_RELEASE_TAG' ) ); ?>></td></tr>
						<tr><th scope="row"><label for="taabeer-github-token"><?php esc_html_e( 'GitHub authentication', 'taabeer-deployment' ); ?></label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="taabeer-github-token" name="github_token" value="" placeholder="<?php echo esc_attr( $this->token() ? __( 'Connected — enter a token only to replace it', 'taabeer-deployment' ) : __( 'Fine-grained GitHub access token', 'taabeer-deployment' ) ); ?>" <?php disabled( defined( 'TAABEER_UPDATER_GITHUB_TOKEN' ) ); ?>><p class="description"><?php echo esc_html( $this->token() ? __( 'Authentication is configured. The saved token is encrypted and never displayed.', 'taabeer-deployment' ) : __( 'For a private repository, use a fine-grained token with read-only Contents access to this repository.', 'taabeer-deployment' ) ); ?></p></td></tr>
					</table>
					<?php submit_button( __( 'Save connection', 'taabeer-deployment' ) ); ?>
				</form>
				<?php if ( $settings['repository'] || $this->token() ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect GitHub from this WordPress site?', 'taabeer-deployment' ) ); ?>');">
						<input type="hidden" name="action" value="taabeer_deployment_disconnect">
						<?php wp_nonce_field( 'taabeer_deployment_disconnect' ); ?>
						<button class="button-link-delete" type="submit"><?php esc_html_e( 'Disconnect GitHub', 'taabeer-deployment' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
		</div>
		<style>
		.taabeer-deployment{max-width:1120px}.taabeer-deployment>.description{max-width:760px;font-size:16px}.taabeer-deployment__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin:28px 0}.taabeer-deployment__card,.taabeer-deployment__settings{padding:28px;border:1px solid #dcdcde;background:#fff}.taabeer-deployment__card h2{margin:.3em 0 .6em;font-family:Georgia,serif;font-size:28px;font-weight:400}.taabeer-deployment__eyebrow{margin:0;color:#6f5f37;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}.taabeer-deployment__actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.taabeer-deployment__settings{margin-bottom:40px}.taabeer-deployment code{word-break:break-all}
		</style>
		<?php
	}
}
