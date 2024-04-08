<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('WPINC') || die;

/**
 * API REST Controller for core CMS information
 *
 * @since  1.0.0
 */
class Panopticon_Core extends WP_REST_Controller
{
	/**
	 * Registers the API route handlers provided by this controller.
	 *
	 * @since  1.0.0
	 */
	public function register_routes()
	{
		$namespace = \PanopticonPlugin::getApiPrefix();

		register_rest_route(
			$namespace, '/core/update', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'getUpdate'],
					'permission_callback' => [$this, 'ensureCanUpdateCore'],
					'args'                => [
						'force'       => [
							'default'           => false,
							'sanitize_callback' => function ($x) {
								return boolval($x);
							},
						],
						'check_files' => [
							'default'           => false,
							'sanitize_callback' => function ($x) {
								return boolval($x);
							},
						],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'installUpdate'],
					'permission_callback' => [$this, 'ensureCanUpdateCore'],
					'args'                => [
						'reinstall' => [
							'default'           => false,
							'sanitize_callback' => function ($x) {
								return boolval($x);
							},
						],
						'version'   => [
							'required'          => false,
							'validate_callback' => function ($x) {
								try
								{
									$dummy = \z4kn4fein\SemVer\Version::parse($x);
								}
								catch (\z4kn4fein\SemVer\VersionFormatException $e)
								{
									return false;
								}

								return true;
							},
						],
						'locale'    => [
							'default' => null,
						],
					],
				],
			]
		);

		register_rest_route(
			$namespace, '/core/updatedb', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'updateDatabase'],
					'permission_callback' => [$this, 'ensureCanUpdateCore'],
				],
			]
		);
	}

	/**
	 * Returns the WordPress update information, plus some other useful information we need.
	 *
	 * @param   WP_REST_Request  $request
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.0.0
	 */
	public function getUpdate(WP_REST_Request $request)
	{
		if (!function_exists('get_preferred_from_update_core'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if (!class_exists('WP_Site_Health_Auto_Updates'))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health-auto-updates.php';
		}

		// Get the parameters.
		/** @var bool $force */
		$force               = boolval($request['force']);
		$checkWriteableFiles = boolval($request['check_files']);

		// Forcibly check for updates, if we were asked to.
		if ($force)
		{
			wp_version_check([], true);
		}

		$ourPlugin = PanopticonPlugin::getInstance();

		// This returns object or false. Hello, 1998! We have to unwrap it the VERY hard way.
		$updateInfo    = get_preferred_from_update_core() ?: new stdClass();
		$latestVersion = $updateInfo->version ?? null;
		$needsUpdate   = ($updateInfo->response ?? '') === 'upgrade';

		// This is the only reliable way to get the current version.
		$currentVersion = get_bloginfo('version');

		// If there was no known latest version pretend this is the latest version.
		if (empty($latestVersion))
		{
			$latestVersion = $currentVersion;
			$needsUpdate   = false;
		}

		// Return something sensible and predictable our code can query easily.
		return new WP_REST_Response(
			[
				'current'             => $currentVersion,
				'currentStability'    => $this->detectStability($currentVersion),
				'latest'              => $latestVersion,
				'latestStability'     => $this->detectStability($latestVersion),
				'minimumStability'    => $this->getMinimumStability(),
				'needsUpdate'         => $needsUpdate,
				'packages'            => $updateInfo->packages ?? new stdClass(),
				'lastUpdateTimestamp' => null,
				'dismissed'           => $updateInfo->dismissed ?? false,
				'minPhpVersion'       => $updateInfo->php_version ?? '0.0',
				'minMySQLVersion'     => $updateInfo->mysql_version ?? '0.0',
				'newBundled'          => $updateInfo->new_bundled ?? $currentVersion,
				'phpVersion'          => PHP_VERSION,
				'panopticon'          => [
					'version' => $ourPlugin->getVersion(),
					'date'    => $ourPlugin->getReleaseDate(),
					'api'     => $ourPlugin->getApiLevel(),
				],
				'sanityChecks'        => [
					// False when the WP_AUTO_UPDATE_CORE constant allows auto-updates
					'constants'             => $this->getMinimumStability() === null,
					// False when a plugin has disabled wp_version_check()
					'version_check_allowed' => $this->isWpVersionCheckAllowed(),
					// False when ALL automatic updates are disabled by the automatic_updater_disabled filter
					'updater_enabled'       => $this->isAutomaticUpdaterEnabled(),
					// False when the site needs to be manually updated by providing FTP credentials
					'ftp_free'              => $this->isFTPFree(),
					// True when all core files are writeable
					'all_writeable'         => !$checkWriteableFiles || $this->allFilesWriteable(),
				],
				'admintools'          => $this->getAdminToolsInformation(),
				'serverInfo'          => (new Panopticon_Server_Info())(),
			], 200
		);
	}

	/**
	 * Installs an update to WordPress itself
	 *
	 * @param   WP_REST_Request  $request
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.0.0
	 */
	public function installUpdate(WP_REST_Request $request)
	{
		if (function_exists('error_reporting'))
		{
			error_reporting(0);
		}

		if (function_exists('ini_set'))
		{
			ini_set('display_errors', false);
			ini_set('max_execution_time', 86400);
			ini_set('memory_limit', 2147483648);
		}

		// Get the parameters from the URL
		$version   = $request['version'];
		$reinstall = $request['reinstall'];
		$locale    = $request['locale'] ?? get_locale();

		// If there is no version, try to find the fittest version.
		if (empty($version))
		{
			if (!function_exists('get_preferred_from_update_core'))
			{
				require_once ABSPATH . 'wp-admin/includes/update.php';
			}

			if (!class_exists('WP_Site_Health_Auto_Updates'))
			{
				require_once ABSPATH . 'wp-admin/includes/class-wp-site-health-auto-updates.php';
			}

			// Make sure the update info isn't stale
			wp_version_check();

			// Get the latest available version for update
			$updateInfo = get_preferred_from_update_core() ?: new stdClass();
			$version    = $updateInfo->version ?? null;
		}

		// Set up the return data
		$return = [
			'status' => false,
			'found'  => false,
		];

		// Include necessary files, because WordPress doesn't use an autoloader.
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		// Try to find the update information object for the requested version.
		$update = find_core_update($version, $locale);

		if (!$update)
		{
			return new WP_Error(
				500,
				sprintf('Could not find update information for WordPress %s.', $version ?: 'latest'),
				$return
			);
		}

		$return['found'] = true;

		// Make sure we don't need FTP credentials
		if (!$this->isFTPFree())
		{
			return new WP_Error(
				500,
				'Your site requires FTP credentials to install/update the WordPress core.',
				$return
			);
		}

		/**
		 * Allow relaxed file ownership writes when updating (not when re-installing) and only if the API says it is
		 * safe to do so. This only happens when there are no new files to create.
		 **/
		$allow_relaxed_file_ownership = !$reinstall && isset($update->new_files) && !$update->new_files;

		// Mark re-installation as such
		if ($reinstall)
		{
			$update->response = 'reinstall';
		}

		// Try to install the WordPress update
		$upgrader = new Core_Upgrader();
		@ob_start();
		$result = $upgrader->upgrade(
			$update,
			[
				'allow_relaxed_file_ownership' => $allow_relaxed_file_ownership,
			]
		);
		@ob_end_clean();

		if (is_wp_error($result))
		{
			if ($result->get_error_code() === 'up_to_date')
			{
				return new WP_Error(
					500,
					'Already up-to-date',
					$return
				);
			}

			if ($result->get_error_code() === 'locked')
			{
				return new WP_Error(
					500,
					'Another update is already running',
					$return
				);
			}

			return new WP_Error(
				500,
				$result->get_error_message(),
				$return
			);
		}

		$return['status'] = true;

		return new WP_REST_Response($return, 200);
	}

	/**
	 * Updates the WordPress database post-update
	 *
	 * @param   WP_REST_Request  $request
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.0.0
	 */
	public function updateDatabase(WP_REST_Request $request)
	{
		if (function_exists('error_reporting'))
		{
			error_reporting(0);
		}

		if (function_exists('ini_set'))
		{
			ini_set('display_errors', false);
			ini_set('max_execution_time', 86400);
			ini_set('memory_limit', 2147483648);
		}

		// Include necessary files, because WordPress doesn't use an autoloader.
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		try
		{
			wp_upgrade();

			return new WP_REST_Response(true);
		}
		catch (Throwable $e)
		{
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}

	/**
	 * Returns true when the currently logged-in user can update the WordPress core
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function ensureCanUpdateCore(): bool
	{
		return current_user_can('update_core');
	}

	/**
	 * Detects the stability of a version string
	 *
	 * @param   string  $versionString  The version string to check
	 *
	 * @return  string  The detected stability: dev, alpha, beta, rc, stable
	 * @since   1.0.0
	 */
	private function detectStability(string $versionString): string
	{
		try
		{
			$version = \z4kn4fein\SemVer\Version::parse($versionString, false);
		}
		catch (\z4kn4fein\SemVer\VersionFormatException $e)
		{
			return 'dev';
		}

		$tag = strtolower($version->getPreRelease() ?: '');

		if ($tag === '')
		{
			return 'stable';
		}

		if (strpos($tag, 'alpha') === 0)
		{
			return 'alpha';
		}

		if (strpos($tag, 'beta') === 0)
		{
			return 'beta';
		}

		if (strpos($tag, 'rc') === 0)
		{
			return 'rc';
		}

		return 'dev';
	}

	/**
	 * Get the minimum stability of WordPress which can be installed automatically.
	 *
	 * - NULL when auto-updates are disabled by the WP_AUTO_UPDATE_CORE constant.
	 * - dev, rc, or beta when that minimum level of unstable release is allowed.
	 * - minor when ONLY minor stable version auto-updates are allowed
	 * - stable when ANY stable version auto-updates are allowed
	 *
	 * @return  string|null
	 * @since   1.0.0
	 */
	private function getMinimumStability(): ?string
	{
		$value = defined('WP_AUTO_UPDATE_CORE') ? WP_AUTO_UPDATE_CORE : true;

		if (!in_array($value, [true, 'beta', 'rc', 'development', 'branch-development', 'minor']))
		{
			return null;
		}

		if ($value === true)
		{
			return 'stable';
		}

		if ($value === 'development' || $value === 'branch-development')
		{
			return 'dev';
		}

		return $value;
	}

	/**
	 * Is the wp_version_check() method allowed to return results?
	 *
	 * False when a `wp_version_check` filter prevents update information from being returned.
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	private function isWpVersionCheckAllowed(): bool
	{
		if ((!is_multisite() || is_main_site() && is_network_admin())
		    && !has_filter('wp_version_check', 'wp_version_check')
		)
		{
			return false;
		}

		return true;
	}

	/**
	 * Is WordPress' automatic updater enabled?
	 *
	 * This checks for the entire auto-update feature, NOT just the core updater.
	 *
	 * If either the AUTOMATIC_UPDATER_DISABLED constant or the `automatic_updater_disabled` filter are false, this
	 * method returns false.
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	private function isAutomaticUpdaterEnabled(): bool
	{
		$disabled = defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED;

		return !apply_filters('automatic_updater_disabled', $disabled);
	}

	/**
	 * Is the core update allowed to install WITHOUT providing FTP credentials?
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	private function isFTPFree()
	{
		if (!function_exists('request_filesystem_credentials'))
		{
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if (!class_exists(WP_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}

		if (!class_exists(Automatic_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		}

		return (new Automatic_Upgrader_Skin())
			->request_filesystem_credentials(false, ABSPATH);
	}

	/**
	 * Are all core files writeable?
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	private function allFilesWriteable(): bool
	{
		global $wp_filesystem;
		global $wp_version;

		require ABSPATH . WPINC . '/version.php'; // $wp_version; // x.y.z

		if (!class_exists(WP_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}

		if (!class_exists(Automatic_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		}

		$skin    = new Automatic_Upgrader_Skin();
		$success = $skin->request_filesystem_credentials(false, ABSPATH);

		if (!$success)
		{
			return false;
		}

		WP_Filesystem();

		if ('direct' !== $wp_filesystem->method)
		{
			return false;
		}

		// Make sure the `get_core_checksums()` function is available during our REST API call.
		if (!function_exists('get_core_checksums'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$checksums = get_core_checksums($wp_version, 'en_US');
		$dev       = (str_contains($wp_version, '-'));

		// Get the last stable version's files and test against that.
		if (!$checksums && $dev)
		{
			$checksums = get_core_checksums((float) $wp_version - 0.1, 'en_US');
		}

		// There aren't always checksums for development releases, so just skip the test if we still can't find any.
		if (!$checksums && $dev)
		{
			return true;
		}

		if (!$checksums)
		{
			return false;
		}

		$unwritable_files = [];

		foreach (array_keys($checksums) as $file)
		{
			if (str_starts_with($file, 'wp-content'))
			{
				continue;
			}

			if (!file_exists(ABSPATH . $file))
			{
				continue;
			}

			if (!is_writable(ABSPATH . $file))
			{
				$unwritable_files[] = $file;
			}
		}

		if ($unwritable_files)
		{
			if (count($unwritable_files) > 20)
			{
				$unwritable_files   = array_slice($unwritable_files, 0, 20);
				$unwritable_files[] = '...';
			}

			return false;
		}

		return true;
	}

	/**
	 * Get information about Admin Tools Professional (if installed)
	 *
	 * @return  object|null
	 * @since   1.0.0
	 */
	private function getAdminToolsInformation(): ?object
	{
		if (!function_exists('is_plugin_active'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$ret = (object) [
			'enabled'      => is_plugin_active('admintoolswp/admintoolswp.php'),
			'renamed'      => false,
			'secret_word'  => null,
			'admindir'     => 'wp-admin',
			'awayschedule' => (object) [
				'timezone' => 'UTC',
				'from'     => null,
				'to'       => null,
			],
		];

		if (!$ret->enabled)
		{
			return $ret;
		}

		$ret->renamed = !file_exists(WP_CONTENT_DIR . '/plugins/admintoolswp/app/plugins/waf/admintools/main.php');

		$config                      = $this->getAdminToolsConfigRegistry() ?? new stdClass();
		$ret->secret_word            = $config->adminpw ?? null;
		$ret->admindir               = $config->adminlogindir ?? 'administrator';
		$ret->awayschedule->timezone = wp_timezone()->getName();
		$ret->awayschedule->from     = $config->awayschedule_from ?? null;
		$ret->awayschedule->to       = $config->awayschedule_from ?? null;

		return $ret;
	}

	/**
	 * Get the configuration information for Admin Tools Professional
	 *
	 * @return  object|mixed|null
	 * @since   1.0.0
	 */
	private function getAdminToolsConfigRegistry(): ?object
	{
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT %i FROM %i WHERE %i = %s',
			'at_value',
			$wpdb->prefix . 'admintools_storage',
			'at_key',
			'cparams'
		);

		try
		{
			$json = $wpdb->get_var($query);
		}
		catch (Exception $e)
		{
			return null;
		}


		if (empty($json))
		{
			return null;
		}

		try
		{
			return json_decode($json);
		}
		catch (Exception $e)
		{
			return null;
		}
	}


}