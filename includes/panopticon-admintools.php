<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\AdminTools\Library\Input\Input;
use Akeeba\AdminTools\Library\Mvc\Model\Model;

class Panopticon_AdminTools extends WP_REST_Controller
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
			$namespace, '/admintools/unblock', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'unblockIP'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);

		register_rest_route(
			$namespace, '/admintools/plugin/disable', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'disablePlugin'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);

		register_rest_route(
			$namespace, '/admintools/plugin/enable', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'enablePlugin'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);

		register_rest_route(
			$namespace, '/admintools/htaccess/disable', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'htaccessDisable'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);

		register_rest_route(
			$namespace, '/admintools/htaccess/enable', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'htaccessEnable'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);
	}

	public function unblockIP(WP_REST_Request $request)
	{
		$ip = $request['ip'];

		if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP))
		{
			return new WP_Error('unblock_ip_required', 'You must provide an IP address', ['status' => 500]);
		}

		/** @var \Akeeba\AdminTools\Admin\Model\UnblockIP $model */
		$model = $this->createModel('UnblockIP', $request);

		return new WP_REST_Response(
			[
				'status'  => true,
				'deleted' => $model->unblockIP($ip),
			]
		);
	}

	public function disablePlugin(WP_REST_Request $request)
	{
		$ret = [
			'renamed' => true,
			'name'    => $this->getRenamedMainPhpFilePath(),
		];

		$hasRenamed   = $ret['name'] !== null;
		$originalName = ADMINTOOLSWP_PATH . '/app/plugins/waf/admintools/main.php';
		$renamedName  = ADMINTOOLSWP_PATH . '/app/plugins/waf/admintools/main.bak.php';
		$hasOriginal  = @file_exists($originalName);

		if ($hasRenamed && !$hasOriginal)
		{
			return new WP_REST_Response($ret);
		}

		if (@rename($originalName, $renamedName))
		{
			$ret['name'] = basename($renamedName);
		}
		else
		{
			$ret['renamed'] = false;
			$ret['name']    = null;
		}

		return new WP_REST_Response($ret);
	}

	public function enablePlugin(WP_REST_Request $request)
	{
		$ret = [
			'renamed' => true,
			'name'    => $this->getRenamedMainPhpFilePath(),
		];

		$hasRenamed   = $ret['name'] !== null;
		$originalName = ADMINTOOLSWP_PATH . '/app/plugins/waf/admintools/main.php';
		$renamedName  = ADMINTOOLSWP_PATH . '/app/plugins/waf/admintools/' . ($ret['name'] ?? 'main.bak.php');
		$hasOriginal  = @file_exists($originalName);

		if (!$hasRenamed || $hasOriginal)
		{
			return new WP_REST_Response($ret);
		}

		if (@rename($renamedName, $originalName))
		{
			$ret['name'] = basename($renamedName);
		}
		else
		{
			$ret['renamed'] = false;
			$ret['name']    = null;
		}

		return new WP_REST_Response($ret);
	}

	public function htaccessDisable(WP_REST_Request $request)
	{
		if (!function_exists('get_home_path'))
		{
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		/** @var \Akeeba\AdminTools\Admin\Model\HtaccessMaker $model */
		$model = $this->createModel('HtaccessMaker', $request);

		$model->nuke();

		return new WP_REST_Response(
			[
				'exists'  => true,
				'renamed' => true,
			]
		);
	}

	public function htaccessEnable(WP_REST_Request $request)
	{
		if (!function_exists('get_home_path'))
		{
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		return new WP_REST_Response(
			[
				'exists'   => true,
				'restored' => \Akeeba\AdminTools\Admin\Helper\HtaccessManager::getInstance()->updateFile(true) !== false,
			]
		);
	}

	public function canAccessAdminTools(): bool
	{
		// Make sure Admin Tools is loaded
		if (!defined('ADMINTOOLSINC'))
		{
			return false;
		}

		// Super Administrators always have full access
		if (is_super_admin())
		{
			return true;
		}

		// On multisite installations only Super Admins are allowed.
		if (is_multisite())
		{
			return false;
		}

		// On single site installations regular admins are allowed (anyone who can activate plugins)
		$wpCaps = (get_userdata(get_current_user_id())->allcaps ?? []) ?: [];

		return (bool) ($wpCaps['activate_plugins'] ?? false);
	}

	private function createModel(string $modelName, WP_REST_Request $request): Model
	{
		$input = new Input(
			array_merge(
				$request->get_default_params() ?? [],
				$request->get_body_params() ?? [],
				$request->get_json_params() ?? [],
				$request->get_query_params() ?? [],
				$request->get_url_params() ?? []
			)
		);

		$className = '\\Akeeba\\AdminTools\\Admin\\Model\\' . ucfirst($modelName);

		return new $className($input);
	}

	protected function getRenamedMainPhpFilePath(): ?string
	{
		$basePath      = ADMINTOOLSWP_PATH . '/app/plugins/waf/admintools';
		$possibleNames = [
			'main.php.bak',
			'main.bak.php',
			'main.bak',
			'main-disable.bak',
			'-main.php',
			'main.php-',
		];

		foreach ($possibleNames as $possibleName)
		{
			if (file_exists($basePath . '/' . $possibleName))
			{
				return $possibleName;
			}
		}

		return null;
	}
}