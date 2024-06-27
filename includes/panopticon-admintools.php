<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\AdminTools\Admin\Helper\HtaccessManager;
use Akeeba\AdminTools\Admin\Helper\Wordpress;
use Akeeba\AdminTools\Admin\Model\HtaccessMaker;
use Akeeba\AdminTools\Admin\Model\Scanner\Util\Session;
use Akeeba\AdminTools\Admin\Model\Scans;
use Akeeba\AdminTools\Admin\Model\UnblockIP;
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
		$namespace = PanopticonPlugin::getApiPrefix();

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

		register_rest_route(
			$namespace, '/admintools/scanner/start', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'scannerStart'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);

		register_rest_route(
			$namespace, '/admintools/scanner/step', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'scannerStep'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);

		register_rest_route(
			$namespace, '/admintools/scans', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'listScans'],
					'permission_callback' => [$this, 'canAccessAdminTools'],
				],
			]
		);
	}

	public function unblockIP(WP_REST_Request $request)
	{
		$ip = $request['ip'];

		if (is_array($ip))
		{
			$ip = array_pop($ip);
		}

		if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP))
		{
			return new WP_Error('unblock_ip_required', 'You must provide an IP address', ['status' => 500]);
		}

		/** @var UnblockIP $model */
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

		/** @var HtaccessMaker $model */
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
				'restored' => HtaccessManager::getInstance()->updateFile(true)
				              !== false,
			]
		);
	}

	public function scannerStart(WP_REST_Request $request)
	{
		/** @var Scans $model */
		$model = $this->createModel('Scans', $request);

		$result            = (array) $model->startScan('api');
		$result['session'] = $this->getScannerState();
		$result['id']      = $result->id ?? $result->session['scanID'] ?? 0;

		return new WP_REST_Response($result);
	}

	public function scannerStep(WP_REST_Request $request)
	{
		$sessionData = $request->get_body_params() ?? [];
		$session     = Session::getInstance();

		if (empty($sessionData))
		{
			return new WP_Error(500, 'You need to pass the session data');
		}

		foreach ($sessionData as $key => $value)
		{
			$session->set($key, $value);
		}

		/** @var Scans $model */
		$model = $this->createModel('Scans', $request);

		$result            = (array) $model->stepScan();
		$result['session'] = $this->getScannerState();
		$result['id']      = $result->id ?? $result->session['scanID'] ?? 0;

		return new WP_REST_Response($result);
	}

	public function listScans(WP_REST_Request $request)
	{
		// Make sure we have limit query parameters
		$queryParams = ($request->get_query_params() ?: [])['page'] ?? [];
		$queryParams = is_array($queryParams) ? $queryParams : [];
		$queryParams['limitstart'] = max(0, $queryParams['limitstart'] ?? 0);
		$queryParams['limit'] = max(1, $queryParams['limit'] ?? Wordpress::get_page_limit());

		$request->set_query_params($queryParams);

		/** @var Scans $model */
		$model = $this->createModel('Scans', $request);
		$total = $model->getTotal();

		return new WP_REST_Response(
			[
				'data' => $model->getItems(),
				'meta' => [
					'total-pages' => ceil($total / $queryParams['limit']),
				],
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
				$request->get_default_params() ?? [], $request->get_body_params() ?? [],
				$request->get_json_params() ?? [], $request->get_query_params() ?? [], $request->get_url_params() ?? []
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

	private function getScannerState(): array
	{
		$session = Session::getInstance();
		$ret     = [];

		foreach ($session->getKnownKeys() as $key)
		{
			$ret[$key] = $session->get($key, null);
		}

		return $ret;
	}
}