<?php
/** Directly reused from TAABEER Deployment Manager 1.0.1, GPL-2.0-or-later.
 * Package target adapted from a theme to the Eola Hills plugin. */
defined( 'ABSPATH' ) || exit;
trait Eola_Hills_Taabeer_Deployment {
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

	private function record_plugin_baseline( $version ) {
		$directory = WP_PLUGIN_DIR . '/' . self::PACKAGE_SLUG;
		$files     = $this->plugin_file_manifest( $directory );
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

	private function plugin_file_manifest( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return new WP_Error( 'eola_hills_manifest_directory', __( 'The installed Eola Hills plugin directory could not be read.', 'eola-hills-updates' ) );
		}
		$files    = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( $file->isLink() ) { return new WP_Error( 'symlink', 'Plugin directory contains a symlink.' ); }
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
				return new WP_Error( 'eola_hills_manifest_file', sprintf( __( 'The plugin file %s could not be read.', 'eola-hills-updates' ), $relative ) );
			}
			$files[ $relative ] = $hash;
		}
		ksort( $files );
		return $files;
	}

	private function find_local_plugin_conflicts( $package ) {
		$theme = $this->installed_plugin();
		if ( ! $theme->exists() ) {
			return array();
		}

		$baseline = get_option( self::OPTION_BASELINE, array() );
		if ( empty( $baseline['files'] ) || ! is_array( $baseline['files'] ) ) {
			$recorded = $this->record_plugin_baseline( $theme->get( 'Version' ) );
			return is_wp_error( $recorded ) ? $recorded : array();
		}

		$current = $this->plugin_file_manifest( $theme->get_stylesheet_directory() );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $package ) ) {
			return new WP_Error( 'eola_hills_conflict_zip', __( 'The incoming package could not be inspected for live-file conflicts.', 'eola-hills-updates' ) );
		}

		$paths     = array_unique( array_merge( array_keys( $baseline['files'] ), array_keys( $current ) ) );
		$conflicts = array();
		foreach ( $paths as $relative ) {
			$before = $baseline['files'][ $relative ] ?? null;
			$now    = $current[ $relative ] ?? null;
			if ( $before === $now ) {
				continue;
			}

			$incoming = $zip->getFromName( self::PACKAGE_SLUG . '/' . $relative );
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

	private function zip_directory( $source, $destination ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'eola_hills_backup_zip', __( 'The PHP Zip extension is required to create the safety backup.', 'eola-hills-updates' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'eola_hills_backup_open', __( 'The theme backup archive could not be created.', 'eola-hills-updates' ) );
		}
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$path = $file->getRealPath();
			$zip->addFile( $path, self::PACKAGE_SLUG . '/' . substr( $path, strlen( $source ) + 1 ) );
		}
		$zip->close();
		return $destination;
	}
}
