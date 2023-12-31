<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
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
			$namespace, '/core/extensions', [
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
		// Get the parameters.
		/** @var bool $force */
		$force = boolval($request['force']);

		// TODO Force reload plugin updates?
		// TODO Force reload theme updates?

		// TODO List the list of plugins
		// TODO List the list of plugin updates
		// TODO List the list of themes
		// TODO List the list of theme updates

		$return = [
			'plugins' => [],
			'themes'  => [],
		];

		// TODO Populate $return['plugins']

		// TODO Populate $return['themes']

		return new WP_REST_Response($return);
	}

	/**
	 * Returns true when the currently logged-in user can update plugins and themes
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function ensureCanUpdateCore(): bool
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