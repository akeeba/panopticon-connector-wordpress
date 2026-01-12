<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

class Panopticon_Updates extends WP_REST_Controller
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
			$namespace, '/updates', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'refreshUpdates'],
					'permission_callback' => [$this, 'canUpdateExtensions'],
				],
			]
		);

		register_rest_route(
			$namespace, '/update/plugin/(?P<plugin>[^./]+(?:/[^/]+)?)', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'updatePlugin'],
					'permission_callback' => [$this, 'canUpdatePlugins'],
				],
			]
		);

		register_rest_route(
			$namespace, '/update/theme/(?P<theme>[^./]+(?:/[^./]+)?)', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'updateTheme'],
					'permission_callback' => [$this, 'canUpdateThemes'],
				],
			]
		);
	}

	public function refreshUpdates(WP_REST_Request $request)
	{
		if (!function_exists('get_plugin_updates'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if (!function_exists('get_plugins'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// This should do nothing
		delete_transient('update_plugins');
		delete_transient('update_themes');
		// This should actually delete the transient with the updates
		delete_site_transient('update_plugins');
		delete_site_transient('update_themes');

		// Ask WordPress to refresh the plugin and theme update information, if necessary
		wp_update_plugins();
		wp_update_themes();

		return new WP_REST_Response(
			[
				'status' => true,
			]
		);
	}

	public function updatePlugin(WP_REST_Request $request)
	{
		$brokenWPWorkaround = false;

		if (!function_exists('show_message'))
		{
			function show_message($message)
			{
				global $_panopticon_upgrade_messages;

				$_panopticon_upgrade_messages = ($_panopticon_upgrade_messages ?? '') . $message;
			}
		}
		else
		{
			/**
			 * WordPress' show_message function kills output buffering, then calls flush() for good measure, thus
			 * ensuring that it is impossible to call it without breaking the heck out of the JSON API it itself
			 * provides!
			 */
			$brokenWPWorkaround = true;
		}

		if (!function_exists('get_plugin_updates'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if (!function_exists('get_plugins'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (!function_exists('request_filesystem_credentials'))
		{
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if (!class_exists(Plugin_Upgrader::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if (!class_exists(Plugin_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';
		}

		$plugin  = $request['plugin'];
		$plugins = get_plugins();

		if (!isset($plugins[$plugin]))
		{
			return new WP_Error('rest_plugin_not_found', __('Plugin not found.'), ['status' => 404]);
		}

		// Do I have an update?
		$updates = get_plugin_updates();

		if (!is_array($updates))
		{
			$updates = json_decode(json_encode($updates), true);
		}

		if (!isset($updates[$plugin]))
		{
			return new WP_Error('no_such_update', 'There is no such update', ['status' => 409]);
		}

		// Is the plugin activated?
		$isPluginActivated = is_plugin_active($plugin);
		$isNetworkActivated = is_multisite() && is_network_only_plugin($plugin)
		                      && is_plugin_active_for_network($plugin);

		// Install the plugin update
		@ob_start();
		$upgrader = new Plugin_Upgrader(
			new Plugin_Upgrader_Skin(
				[
					'plugin' => $plugin,
				]
			)
		);
		$result   = $upgrader->upgrade($plugin);
		@ob_end_clean();

		if ($brokenWPWorkaround)
		{
			echo '###!#!--!-+=+-!--!#!###' . "\n";
		}

		if (is_wp_error($result))
		{
			return $result;
		}

		// Re-activate plugin
		if ($isPluginActivated || $isNetworkActivated)
		{
			activate_plugin($plugin, '', $isNetworkActivated, false);
		}

		global $_panopticon_upgrade_messages;

		return new WP_REST_Response(
			[
				'status'   => true,
				'messages' => $_panopticon_upgrade_messages,
			]
		);
	}

	public function updateTheme(WP_REST_Request $request)
	{
		$brokenWPWorkaround = false;

		if (!function_exists('show_message'))
		{
			function show_message($message)
			{
				global $_panopticon_upgrade_messages;

				$_panopticon_upgrade_messages = ($_panopticon_upgrade_messages ?? '') . $message;
			}
		}
		else
		{
			/**
			 * WordPress' show_message function kills output buffering, then calls flush() for good measure, thus
			 * ensuring that it is impossible to call it without breaking the heck out of the JSON API it itself
			 * provides!
			 */
			$brokenWPWorkaround = true;
		}

		if (!function_exists('get_theme_updates'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if (!function_exists('wp_get_themes'))
		{
			require_once ABSPATH . 'wp-includes/theme.php';
		}

		if (!function_exists('request_filesystem_credentials'))
		{
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if (!class_exists(Theme_Upgrader::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		}

		if (!class_exists(Theme_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader-skin.php';
		}

		$theme  = $request['theme'];
		$themes = wp_get_themes();

		if (!isset($themes[$theme]))
		{
			return new WP_Error('rest_theme_not_found', __('Theme not found.'), ['status' => 404]);
		}

		// Do I have an update?
		$updates = get_theme_updates();

		if (!isset($updates[$theme]))
		{
			return new WP_Error('no_such_update', 'There is no such update', ['status' => 409]);
		}

		// Install the plugin update
		@ob_start();
		$upgrader = new Theme_Upgrader(
			new Theme_Upgrader_Skin(
				[
					'theme' => $theme,
				]
			)
		);
		$result   = $upgrader->upgrade($theme);
		@ob_end_clean();

		if ($brokenWPWorkaround)
		{
			echo '###!#!--!-+=+-!--!#!###' . "\n";
		}

		if (is_wp_error($result))
		{
			return $result;
		}

		global $_panopticon_upgrade_messages;

		return new WP_REST_Response(
			[
				'status'   => true,
				'messages' => $_panopticon_upgrade_messages,
			]
		);
	}

	public function canUpdateExtensions(): bool
	{
		return $this->canUpdatePlugins() && $this->canUpdateThemes();
	}

	public function canUpdatePlugins(): bool
	{
		if (is_multisite())
		{
			return current_user_can('manage_network_plugins');
		}

		return current_user_can('update_plugins');
	}

	public function canUpdateThemes(): bool
	{
		if (is_multisite())
		{
			return current_user_can('manage_network_themes');
		}

		return current_user_can('update_themes');
	}

	protected function getPluginData($plugin)
	{
		$plugins = get_plugins();

		if (!isset($plugins[$plugin]))
		{
			return new WP_Error('rest_plugin_not_found', __('Plugin not found.'), ['status' => 404]);
		}

		$data          = $plugins[$plugin];
		$data['_file'] = $plugin;

		return $data;
	}
}