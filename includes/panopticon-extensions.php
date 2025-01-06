<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nikolaos Dionysopoulos / Akeeba Ltd
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

}