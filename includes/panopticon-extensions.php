<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * API REST Controller for plugins and themes information.
 *
 * @since  1.0.0
 */
class Panopticon_Extensions extends \WP_REST_Controller
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
			$namespace, '/extensions', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'getExtensions'],
					'permission_callback' => [$this, 'canListExtensions'],
					'args'                => [
						'force' => [
							'default'           => false,
							'sanitize_callback' => function ($x) {
								return boolval($x);
							},
						],
					],
				],
			]
		);

		register_rest_route(
			$namespace, '/extension/install', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'installExtensionFromUrl'],
					'permission_callback' => [$this, 'canInstallExtensions'],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [$this, 'installExtensionFromUpload'],
					'permission_callback' => [$this, 'canInstallExtensions'],
				],
			]
		);
	}

	/**
	 * Returns the installed plugins and themes, including their update status
	 *
	 * @param   WP_REST_Request  $request
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.0.0
	 */
	public function getExtensions(WP_REST_Request $request)
	{
		if (!function_exists('get_plugin_updates'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if (!function_exists('get_plugins'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get the parameters.
		/** @var bool $force */
		$force = boolval($request['force']);

		// When forcing the retrieval of updates, we just need to delete the current update information.
		if ($force)
		{
			// This should do nothing
			delete_transient('update_plugins');
			delete_transient('update_themes');
			// This should actually delete the transient with the updates
			delete_site_transient('update_plugins');
			delete_site_transient('update_themes');
		}

		// Ask WordPress to refresh the plugin and theme update information, if necessary
		wp_update_plugins();
		wp_update_themes();

		// List plugins and their updates
		$pList = get_plugins();
		ksort($pList);
		$pList = array_map(
			function ($x, $id) {
				return [
					'id'           => $id,
					'enabled'      => is_plugin_active($id),
					'name'         => $x['Name'],
					'plugin_uri'   => $x['PluginURI'],
					'version'      => $x['Version'],
					'description'  => $x['Description'],
					'author'       => $x['Author'],
					'author_uri'   => $x['AuthorURI'],
					'text_domain'  => $x['TextDomain'],
					'domain_path'  => $x['DomainPath'],
					'network'      => $x['Network'],
					'requires'     => $x['RequiresWP'],
					'requires_php' => $x['RequiresPHP'],
					'update_uri'   => $x['UpdateURI'],
					'title'        => $x['Title'],
					'author_name'  => $x['AuthorName'],
				];
			}, array_values($pList), array_keys($pList)
		);

		$pUpdates = get_plugin_updates();

		// List themes and their updates
		$tList = wp_get_themes();
		ksort($tList);
		$tList    = array_map(
			function ($k, WP_Theme $x) {
				return [
					'id'             => $k,
					'name'           => $x->get('Name'),
					'theme_uri'      => $x->get('ThemeURI'),
					'description'    => $x->get('Description'),
					'author'         => $x->get('Author'),
					'author_uri'     => $x->get('AuthorURI'),
					'version'        => $x->get('Version'),
					'template'       => $x->get('Template'),
					'status'         => $x->get('Status'),
					'tags'           => $x->get('Tags'),
					'text_domain'    => $x->get('TextDomain'),
					'domain_path'    => $x->get('DomainPath'),
					'requires'       => $x->get('RequiresWP'),
					'requires_php'   => $x->get('RequiresPHP'),
					'update_uri'     => $x->get('UpdateURI'),
					'parent_theme'   => $x->parent_theme,
					'template_dir'   => $x->template_dir,
					'stylesheet_dir' => $x->stylesheet_dir,
					'stylesheet'     => $x->stylesheet,
					'screenshot'     => $x->screenshot,
					'theme_root'     => $x->theme_root,
					'theme_root_uri' => $x->theme_root_uri,
				];
			}, array_keys($tList), array_values($tList)
		);
		$tUpdates = get_theme_updates();

		$return = [
			'plugins' => array_map(
				function ($v) use ($pUpdates) {
					$possibleUpdate = $pUpdates[$v['id']] ?? null;

					$v['update'] = (is_object($possibleUpdate) && is_object($possibleUpdate->update)
						? $possibleUpdate->update : null);

					return $v;
				}, $pList
			),
			'themes'  => array_map(
				function ($v) use ($tUpdates) {
					$possibleUpdate = $tUpdates[$v['id']] ?? null;

					$v['update'] = $possibleUpdate instanceof WP_Theme
						? $possibleUpdate->update : null;

					return $v;
				}, $tList
			),
		];

		return new WP_REST_Response($return);
	}

	/**
	 * Returns true when the currently logged-in user can update plugins and themes
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function canListExtensions(): bool
	{
		if (is_multisite())
		{
			return current_user_can('manage_network_plugins')
			       && current_user_can('manage_network_themes');
		}

		return current_user_can('update_plugins')
		       && current_user_can('update_themes');
	}

	/**
	 * Install an extension from a URL (POST request)
	 *
	 * @param   WP_REST_Request  $request
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.2.0
	 */
	public function installExtensionFromUrl(WP_REST_Request $request)
	{
		// Check if remote extension installation is allowed
		$options = get_option('panopticon_options');

		if (empty($options['panopticon_field_allow_remote_install'] ?? 1))
		{
			return new WP_Error(
				'remote_install_disabled',
				'Remote extension installation is disabled on this site.',
				['status' => 403]
			);
		}

		// Get the URL parameter from form data
		$url = $request->get_param('url');

		if (empty($url))
		{
			return new WP_Error('missing_url', 'URL parameter is required', ['status' => 400]);
		}

		// Validate URL
		if (!filter_var($url, FILTER_VALIDATE_URL))
		{
			return new WP_Error('invalid_url', 'Invalid URL provided', ['status' => 400]);
		}

		// Download the file to temporary directory
		$temp_dir  = get_temp_dir();
		$temp_file = $temp_dir . wp_unique_filename($temp_dir, basename(parse_url($url, PHP_URL_PATH)));

		$response = wp_remote_get($url, [
			'timeout'  => 300,
			'stream'   => true,
			'filename' => $temp_file,
		]);

		if (is_wp_error($response))
		{
			@unlink($temp_file);

			return new WP_Error(
				'download_failed',
				'Failed to download package: ' . $response->get_error_message(),
				['status' => 500]
			);
		}

		$response_code = wp_remote_retrieve_response_code($response);

		if ($response_code !== 200)
		{
			@unlink($temp_file);

			return new WP_Error(
				'download_failed',
				'Failed to download package: HTTP ' . $response_code,
				['status' => 500]
			);
		}

		// Install the extension
		$result = $this->installExtensionFromFile($temp_file);

		// Clean up
		@unlink($temp_file);

		return $result;
	}

	/**
	 * Install an extension from uploaded binary data (PUT request)
	 *
	 * @param   WP_REST_Request  $request
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.2.0
	 */
	public function installExtensionFromUpload(WP_REST_Request $request)
	{
		// Check if remote extension installation is allowed
		$options = get_option('panopticon_options');

		if (empty($options['panopticon_field_allow_remote_install'] ?? 1))
		{
			return new WP_Error(
				'remote_install_disabled',
				'Remote extension installation is disabled on this site.',
				['status' => 403]
			);
		}

		// Get the filename parameter
		$filename = $request->get_param('filename');

		if (empty($filename))
		{
			return new WP_Error('missing_filename', 'Filename parameter is required', ['status' => 400]);
		}

		// Sanitize filename: remove dots, backslashes, and forward slashes
		$filename = preg_replace('/[\.\/\\\\]/', '', $filename);

		if (empty($filename))
		{
			return new WP_Error('invalid_filename', 'Invalid filename after sanitization', ['status' => 400]);
		}

		// Get the uploaded binary data from request body
		$body = $request->get_body();

		if (empty($body))
		{
			return new WP_Error('missing_data', 'No file data provided', ['status' => 400]);
		}

		// Save to temporary directory
		$temp_dir  = get_temp_dir();
		$temp_file = $temp_dir . $filename;

		$result = @file_put_contents($temp_file, $body);

		if ($result === false)
		{
			return new WP_Error(
				'upload_failed',
				'Failed to save uploaded file',
				['status' => 500]
			);
		}

		// Install the extension
		$install_result = $this->installExtensionFromFile($temp_file);

		// Clean up
		@unlink($temp_file);

		return $install_result;
	}

	/**
	 * Install and activate an extension from a package file
	 *
	 * @param   string  $package_file  Path to the package file
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.2.0
	 */
	private function installExtensionFromFile($package_file)
	{
		// Load required WordPress files
		if (!function_exists('get_plugins'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (!class_exists('Plugin_Upgrader'))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if (!class_exists('Theme_Upgrader'))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if (!class_exists('Automatic_Upgrader_Skin'))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';
		}

		// Determine if this is a plugin or theme by checking the package
		$extension_type = $this->detectExtensionType($package_file);

		if ($extension_type === 'plugin')
		{
			return $this->installPlugin($package_file);
		}
		elseif ($extension_type === 'theme')
		{
			return $this->installTheme($package_file);
		}
		else
		{
			return new WP_Error(
				'unknown_type',
				'Could not determine if package is a plugin or theme',
				['status' => 400]
			);
		}
	}

	/**
	 * Detect extension type by inspecting the package
	 *
	 * @param   string  $package_file  Path to the package file
	 *
	 * @return  string  'plugin', 'theme', or 'unknown'
	 * @since   1.2.0
	 */
	private function detectExtensionType($package_file)
	{
		if (!class_exists('WP_Filesystem_Direct'))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$filesystem = new WP_Filesystem_Direct(null);
		$temp_dir   = get_temp_dir() . 'panopticon_detect_' . wp_generate_password(8, false);

		// Create temporary directory
		if (!wp_mkdir_p($temp_dir))
		{
			return 'unknown';
		}

		// Unzip the package
		$result = unzip_file($package_file, $temp_dir);

		if (is_wp_error($result))
		{
			$filesystem->rmdir($temp_dir, true);

			return 'unknown';
		}

		// Check for plugin files (*.php with plugin headers)
		$files = $filesystem->dirlist($temp_dir, true, true);

		$is_plugin = false;
		$is_theme  = false;

		if (is_array($files))
		{
			foreach ($files as $file)
			{
				if ($file['type'] === 'f' && substr($file['name'], -4) === '.php')
				{
					$file_path = $temp_dir . '/' . $file['name'];
					$content   = $filesystem->get_contents($file_path);

					// Check for plugin header
					if (preg_match('/Plugin Name\s*:/i', $content))
					{
						$is_plugin = true;
						break;
					}
				}

				// Check for theme (style.css with theme headers)
				if ($file['name'] === 'style.css')
				{
					$file_path = $temp_dir . '/' . $file['name'];
					$content   = $filesystem->get_contents($file_path);

					if (preg_match('/Theme Name\s*:/i', $content))
					{
						$is_theme = true;
						break;
					}
				}
			}
		}

		// Clean up
		$filesystem->rmdir($temp_dir, true);

		if ($is_plugin)
		{
			return 'plugin';
		}
		elseif ($is_theme)
		{
			return 'theme';
		}

		return 'unknown';
	}

	/**
	 * Install and activate a plugin
	 *
	 * @param   string  $package_file  Path to the plugin package file
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.2.0
	 */
	private function installPlugin($package_file)
	{
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader($skin);

		// Install the plugin
		$result = $upgrader->install($package_file);

		if (is_wp_error($result))
		{
			return new WP_Error(
				'installation_failed',
				'Plugin installation failed: ' . $result->get_error_message(),
				['status' => 500]
			);
		}

		if (!$result)
		{
			return new WP_Error(
				'installation_failed',
				'Plugin installation failed',
				['status' => 500]
			);
		}

		// Get the plugin file path
		$plugin_file = $upgrader->plugin_info();

		if (!$plugin_file)
		{
			return new WP_Error(
				'plugin_not_found',
				'Plugin installed but could not be located',
				['status' => 500]
			);
		}

		// Determine if this is a network-only plugin and should be network activated
		$isNetworkActivated = is_multisite() && is_network_only_plugin($plugin_file);

		// Activate the plugin
		$activate_result = activate_plugin($plugin_file, '', $isNetworkActivated, false);

		if (is_wp_error($activate_result))
		{
			return new WP_REST_Response([
				'data' => [
					'type'       => 'extensioninstall',
					'id'         => 0,
					'attributes' => [
						'id'       => 0,
						'status'   => true,
						'messages' => [
							[
								'message' => 'Plugin installed but activation failed: ' . $activate_result->get_error_message(),
								'type'    => 'warning',
							],
						],
					],
				],
			]);
		}

		return new WP_REST_Response([
			'data' => [
				'type'       => 'extensioninstall',
				'id'         => 0,
				'attributes' => [
					'id'       => 0,
					'status'   => true,
					'messages' => [
						[
							'message' => 'Plugin installed and activated successfully',
							'type'    => 'message',
						],
					],
				],
			],
		]);
	}

	/**
	 * Install and activate a theme
	 *
	 * @param   string  $package_file  Path to the theme package file
	 *
	 * @return  WP_REST_Response|WP_Error
	 * @since   1.2.0
	 */
	private function installTheme($package_file)
	{
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader($skin);

		// Install the theme
		$result = $upgrader->install($package_file);

		if (is_wp_error($result))
		{
			return new WP_Error(
				'installation_failed',
				'Theme installation failed: ' . $result->get_error_message(),
				['status' => 500]
			);
		}

		if (!$result)
		{
			return new WP_Error(
				'installation_failed',
				'Theme installation failed',
				['status' => 500]
			);
		}

		// Get the theme stylesheet
		$theme_info = $upgrader->theme_info();

		if (!$theme_info)
		{
			return new WP_Error(
				'theme_not_found',
				'Theme installed but could not be located',
				['status' => 500]
			);
		}

		// Activate the theme
		switch_theme($theme_info->get_stylesheet());

		return new WP_REST_Response([
			'data' => [
				'type'       => 'extensioninstall',
				'id'         => 0,
				'attributes' => [
					'id'       => 0,
					'status'   => true,
					'messages' => [
						[
							'message' => 'Theme installed and activated successfully',
							'type'    => 'message',
						],
					],
				],
			],
		]);
	}

	/**
	 * Returns true when the currently logged-in user can install plugins and themes
	 *
	 * @return  bool
	 * @since   1.2.0
	 */
	public function canInstallExtensions(): bool
	{
		if (is_multisite())
		{
			return current_user_can('install_plugins')
			       && current_user_can('install_themes');
		}

		return current_user_can('install_plugins')
		       && current_user_can('install_themes');
	}

}