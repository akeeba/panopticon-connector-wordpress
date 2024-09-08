<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

class Panopticon_AkeebaBackup extends WP_REST_Controller
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
			$namespace, '/akeebabackup/info', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'getAkeebaBackupInfo'],
					'permission_callback' => [$this, 'canAccessAkeebaBackup'],
				],
			]
		);
	}

	public function getAkeebaBackupInfo(WP_REST_Request $request)
	{
		$ret = [
			'status'    => true,
			'installed' => false,
			'version'   => '0.0.0',
			'api'       => null,
			'secret'    => null,
			'endpoints' => [],
		];

		if (!defined('AKEEBABACKUPWP_ALREADY_LOADED') && !defined('AKEEBABACKUP_VERSION'))
		{
			return new WP_REST_Response($ret);
		}

		$akeebaBackupVersion = defined('AKEEBABACKUP_VERSION') ? AKEEBABACKUP_VERSION : '0.0.0';

		if (version_compare($akeebaBackupVersion, '8.0.0', 'lt'))
		{
			\Akeeba\Engine\Factory::getSecureSettings()->setKeyFilename('secretkey.php');
		}

		$ret['installed'] = true;
		$ret['version']   = $akeebaBackupVersion;
		$ret['api']       = $this->getMaxApiVersion();
		$ret['secret']    = \Akeeba\Engine\Platform::getInstance()->get_platform_configuration_option(
			'frontend_secret_word', ''
		);
		$ret['endpoints'] = $this->getEndpoints();

		return new WP_REST_Response($ret);
	}

	public function canAccessAkeebaBackup(): bool
	{
		if (!defined('AKEEBABACKUPWP_ALREADY_LOADED'))
		{
			// This is cheating; but it will be caught in getAkeebaBackupInfo()
			return true;
		}

		// Super Administrators have all Akeeba Backup privileges enabled
		if (is_super_admin())
		{
			return true;
		}

		// In any other case we need an administrator on a non-multisite installation.
		if (is_multisite())
		{
			return false;
		}

		$wpCaps = (get_userdata(get_current_user_id())->allcaps ?? []) ?: [];

		return (bool) ($wpCaps['activate_plugins'] ?? false);
	}

	/**
	 * Get the maximum API version you can use with the currently installed Akeeba Backup version.
	 *
	 * @return  int
	 * @see     https://www.akeeba.com/documentation/json-api/endpoints.html
	 */
	private function getMaxApiVersion()
	{
		$version = defined('AKEEBABACKUP_VERSION') ? AKEEBABACKUP_VERSION : '0.0.0';

		if ($version === '0.0.0')
		{
			return 2;
		}

		if (version_compare($version, '7.5.0', 'ge'))
		{
			return 2;
		}

		return 1;
	}

	private function getMinApiVersion()
	{
		$version = defined('AKEEBABACKUP_VERSION') ? AKEEBABACKUP_VERSION : '0.0.0';

		if ($version === '0.0.0')
		{
			return 2;
		}

		return version_compare($version, '8.0.0', 'ge') ? 2 : 1;
	}

	/**
	 * Get the Akeeba Backup endpoint for the currently installed Akeeba Backup version.
	 *
	 * @return  array[]
	 * @see     https://www.akeeba.com/documentation/json-api/endpoints.html
	 */
	private function getEndpoints()
	{
		$version = defined('AKEEBABACKUP_VERSION') ? AKEEBABACKUP_VERSION : '0.0.0';
		$maxApi  = $this->getMaxApiVersion();
		$minApi  = $this->getMinApiVersion();
		$dirName = \AkeebaBackupWP::$dirName;

		$v1Endpoint = version_compare($version, '7.4.0', 'ge')
			? sprintf(
				"%s/wp-content/plugins/%s/app/index.php?option=com_akeeba&view=json&format=raw",
				rtrim(home_url(), '/'),
				$dirName
			)
			: sprintf(
				"%s/wp-content/plugins/%s/app/index.php?option=com_akeeba&view=api&format=raw",
				rtrim(home_url(), '/'),
				$dirName
			);

		$v2Endpoint = version_compare($version, '7.7.1', 'ge')
			? sprintf(
				"%s/wp-admin/admin-ajax.php?action=akeebabackup_api&option=com_akeeba&view=api&format=raw",
				rtrim(home_url(), '/')
			)
			: sprintf(
				"%s/wp-content/plugins/%s/app/index.php?option=com_akeeba&view=api&format=raw",
				rtrim(home_url(), '/'),
				$dirName
			);

		if ($maxApi === 1)
		{
			return [
				'v1' => [$v1Endpoint],
			];
		}

		if ($minApi === 1)
		{
			return [
				'v1' => [$v1Endpoint],
				'v2' => [$v2Endpoint],
			];
		}

		return [
			'v2' => [$v2Endpoint],
		];
	}


}