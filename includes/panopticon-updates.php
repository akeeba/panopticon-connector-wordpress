<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
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
			$namespace, '/update/plugin/(?P<plugin>[^.\/]+(?:\/[^.\/]+)?)', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'updatePlugin'],
					'permission_callback' => [$this, 'canUpdatePlugins'],
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
		if (!function_exists('get_plugin_updates'))
		{
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if (!function_exists('get_plugins'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (!class_exists(Plugin_Upgrader::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if (!class_exists(Plugin_Upgrader_Skin::class))
		{
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';
		}

		$plugin = $request['plugin'];
		$plugins = get_plugins();

		if (!isset($plugins[$plugin]))
		{
			return new WP_Error('rest_plugin_not_found', __('Plugin not found.'), ['status' => 404]);
		}

		// Do I have an update?
		$updates = get_plugin_updates();

		if (!isset($updates->{$plugin}))
		{
			// TODO Return error 409: no such update
		}

		// Install the plugin update
		@ob_start();
		$upgrader = new Plugin_Upgrader(new Plugin_Upgrader_Skin([
			'plugin' => $plugin
		]));
		$result = $upgrader->upgrade($plugin);
		@ob_end_clean();

		if (is_wp_error($result))
		{
			return $result;
		}

		return new WP_REST_Response([
			'status' => true
		]);
	}

	public function canUpdateExtensions(): bool
	{
		if (is_multisite())
		{
			return current_user_can('manage_network_plugins')
			       && current_user_can('manage_network_themes');
		}

		return current_user_can('update_plugins')
		       && current_user_can('update_themes');
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