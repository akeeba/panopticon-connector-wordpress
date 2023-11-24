<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('WPINC') || die;

/**
 * @wordpress-plugin
 * Plugin Name:       Akeeba Panopticon Connector for WordPress
 * Plugin URI:        https://github.com/akeeba/panopticon_connector_wp
 * Description:       Use your WordPress site with Akeeba Panopticon
 * Version:           1.0.0
 * Release Date:      2023-11-15
 * Panopticon API:    100
 * Requires PHP:      7.2
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Author:            akeeba
 * Author URI:        http://www.akeeba.com
 * License:           AGPL-3.0+
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * GitHub Plugin URI: https://github.com/akeeba/panopticon_connector_wp
 * Update URI:        false
 */
class PanopticonPlugin
{
	/**
	 * Object instance for Singleton implementation
	 *
	 * @var   null|self
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * The API route controller classes to load
	 *
	 * @var   string[]
	 * @since 1.0.0
	 */
	private static $classes = [
		Panopticon_Core::class,
	];

	private static $apiPrefix = 'v1/panopticon';

	/**
	 * Plugin version
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $version = '0.0.0-dev';

	/**
	 * Minimum PHP version
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $php = '7.2.0';

	/**
	 * Release date of this plugin
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $releaseDate = '';

	/**
	 * Panopticon API level
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $apiLevel = '0';

	/**
	 * Common constructor
	 *
	 * @since   1.0.0
	 */
	public function __construct()
	{
		$this->releaseDate = gmdate('Y-m-d');

		$this->loadComposer();
		$this->loadVersionInfo();

		add_action('rest_api_init', [$this, 'loadRoutes']);
	}

	/**
	 * Get a Singleton instance
	 *
	 * @return  self
	 * @since   1.0.0
	 */
	public static function getInstance(): self
	{
		return self::$instance = self::$instance ?? new self();
	}

	/**
	 * Runs on plugin activation
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public static function activation()
	{
		register_uninstall_hook(__FILE__, [PanopticonPlugin::class, 'uninstallation']);
	}

	/**
	 * Runs on plugin deactivation
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public static function deactivation()
	{
		// This method is intentionally left blank
	}

	/**
	 * Runs on plugin uninstallation
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public static function uninstallation()
	{
		// This method is intentionally left blank
	}

	/**
	 * Returns the API prefix for the routes
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public static function getApiPrefix(): string
	{
		return self::$apiPrefix;
	}

	/**
	 * Returns the version of the plugin
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getVersion(): string
	{
		return $this->version;
	}

	/**
	 * Returns the minimum supported PHP version
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getMinimumPhp(): string
	{
		return $this->php;
	}

	/**
	 * Returns the Panopticon API level supported by this plugin
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getApiLevel(): string
	{
		return $this->apiLevel;
	}

	/**
	 * Get the plugin's release date
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getReleaseDate()
	{
		return $this->releaseDate;
	}

	/**
	 * Loads the API route controllers and registers their routes
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function loadRoutes()
	{
		foreach (self::$classes as $class)
		{
			if (!class_exists($class))
			{
				$filePath = __DIR__ . '/includes/' . str_replace('_', '-', strtolower($class)) . '.php';

				if (!@file_exists($filePath) || !@is_readable($filePath))
				{
					continue;
				}

				try
				{
					require_once $filePath;
				}
				catch (Throwable $e)
				{
					continue;
				}
			}

			if (!class_exists($class))
			{
				continue;
			}

			$o = new $class;

			if (!method_exists($class, 'register_routes') || !$o instanceof WP_REST_Controller)
			{
				continue;
			}

			$o->register_routes();
		}
	}

	/**
	 * Retrieves the version info from the contents of this plugin file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function loadVersionInfo()
	{
		$fileContents = file_get_contents(__FILE__);

		if (preg_match('#Version\s*:\s*(.*)#', $fileContents, $matches))
		{
			$this->version = $matches[1];
		}

		if (preg_match('#Requires PHP\s*:\s*(.*)#', $fileContents, $matches))
		{
			$this->php = $matches[1];
		}

		if (preg_match('#Panopticon API\s*:\s*(.*)#', $fileContents, $matches))
		{
			$this->apiLevel = $matches[1];
		}

		if (preg_match('#Release Date\s*:\s*(.*)#', $fileContents, $matches))
		{
			$this->releaseDate = $matches[1];
		}
	}

	/**
	 * Loads the included Composer dependencies
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function loadComposer()
	{
		$file = __DIR__ . '/vendor/autoload.php';

		if (@is_file($file) && @is_readable($file))
		{
			try
			{
				require_once $file;
			}
			catch (Throwable $e)
			{
				return;
			}
		}
	}

}

// Initialize the plugin
PanopticonPlugin::getInstance();

// Register the various activation hooks
register_activation_hook(__FILE__, [PanopticonPlugin::class, 'activation']);
register_deactivation_hook(__FILE__, [PanopticonPlugin::class, 'deactivation']);