<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/*
Plugin Name: Akeeba Panopticon Connector for WordPress
Plugin URI: https://github.com/akeeba/panopticon_connector_wp
Description: Use your WordPress site with Akeeba Panopticon
Version: 1.0.2-dev202409071431-reveeaa6d7
Panopticon API: 100
Requires PHP: 7.2
Requires at least: 5.0
Tested up to: 6.6
Author: akeeba
Author URI: http://www.akeeba.com
License: AGPL-3.0+
License URI: https://www.gnu.org/licenses/agpl-3.0.html
GitHub Plugin URI: https://github.com/akeeba/panopticon_connector_wp
Update URI: false
*/

defined('WPINC') || die;

if (!trait_exists('Panopticon_Options_Trait', false))
{
	require_once __DIR__ . '/includes/panopticon-options-trait.php';
}

class PanopticonPlugin
{
	use Panopticon_Options_Trait;

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
		Panopticon_Server_Info::class,
		Panopticon_Core::class,
		Panopticon_Extensions::class,
		Panopticon_Updates::class,
		Panopticon_AkeebaBackup::class,
		Panopticon_AdminTools::class,
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
	 * Authorize an API user.
	 *
	 * If the provided token matches the configured one we log in the first Super Admin (multisite), or Administrator
	 * user we can find.
	 *
	 * @param   mixed  $user
	 *
	 * @return  mixed
	 */
	public function authorizeAPIUser($user)
	{
		// We must have no logged-in user, and the token in the request must match the configured one.
		if (!empty($user) || !$this->isAuthenticated())
		{
			return $user;
		}

		$users = get_users(
			[
				'role'    => is_multisite() ? 'Super Admin' : 'Administrator',
				'orderby' => 'ID',
				'number'  => 1,
			]
		);

		return $users[0];
	}

	/**
	 * Register a custom page under the Tools menu item.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function registerAdminMenu(): void
	{
		add_management_page(
			__('Panopticon', 'panopticon'), __('Panopticon', 'panopticon'), 'update_core', __FILE__,
			[$this, 'adminPage']
		);
	}

	/**
	 * Render the custom page under the Tools menu item.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function adminPage(): void
	{
		// Add error/update messages

		// Check if the user have submitted the settings
		// WordPress will add the "settings-updated" $_GET parameter to the url
		if (isset($_GET['settings-updated']))
		{
			// add settings saved message with the class of "updated"
			add_settings_error('panopticon_messages', 'panopticon_message', 'Settings Saved', 'updated');
		}

		// Show error/update messages
		settings_errors('panopticon_messages');

		?>
		<h2 class="title">
			<?= __('Connection information', 'panopticon') ?>
		</h2>
		<table>
			<tr>
				<th scope="row">
					<?= __('Endpoint URL', 'panopticon') ?>
				</th>
				<td>
					<code style="background: none">
						<?= get_rest_url() ?>
					</code>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?= __('Token', 'panopticon') ?>
				</th>
				<td>
					<code style="background: none">
						<?= $this->getToken() ?>
					</code>
				</td>
			</tr>
		</table>

		<hr style="margin: 1.5em 0"/>

		<form action="options.php" method="post">
			<?php
			// Output security fields for the registered setting "wporg"
			settings_fields('panopticon');
			// Output setting sections, and their fields
			// (sections are registered for "panopticon", each field is registered to a specific section)
			do_settings_sections('panopticon');
			// output save settings button
			submit_button('Save Settings');
			?>
		</form>
		<?php
	}

	/**
	 * Get the (hashed, salted) token which authenticates our API
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getToken(): string
	{
		$salt  = wp_salt('auth');
		$token = get_option('panopticon_token', null);

		if (empty($token))
		{
			$token = wp_generate_password('64', false);
			update_option('panopticon_token', $token, true);
		}

		return hash('sha256', $token . ':' . $salt);
	}

	public function registerWpCliCommands() {
		require_once __DIR__ . '/includes/panopticon-cli-namespace.php';
		require_once __DIR__ . '/includes/panopticon-cli-token.php';

		WP_CLI::add_command('panopticon', Panopticon_Cli_Namespace::class);
		WP_CLI::add_command('panopticon token', Panopticon_Cli_Token::class);
	}

	/**
	 * Does the token in the request match the one set up in the plugin?
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	private function isAuthenticated(): bool
	{
		$authHeader  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
		$tokenString = '';

		// Apache specific fixes. See https://github.com/symfony/symfony/issues/19693
		if (
			empty($authHeader) && \PHP_SAPI === 'apache2handler'
			&& function_exists('apache_request_headers')
			&& apache_request_headers() !== false
		)
		{
			$apacheHeaders = array_change_key_case(apache_request_headers(), CASE_LOWER);

			if (array_key_exists('authorization', $apacheHeaders))
			{
				$authHeader = $apacheHeaders['authorization'];
			}
		}

		// Preferred form: `Authorization: Bearer TOKEN_STRING`.
		if (substr($authHeader, 0, 7) == 'Bearer ')
		{
			$parts       = explode(' ', $authHeader, 2);
			$tokenString = trim($parts[1]);
		}

		// Fallback: `X-Panopticon-Token: TOKEN_STRING`.
		if (empty($tokenString))
		{
			$tokenString = $_SERVER['HTTP_X_PANOPTICON_TOKEN'] ?? '';
		}

		// Fallback: `X-Joomla-Token: TOKEN_STRING` (this is used )for simplicity in Panopticon 1.x).
		if (empty($tokenString))
		{
			$tokenString = $_SERVER['HTTP_X_JOOMLA_TOKEN'] ?? '';
		}

		// DO NOT INLINE. We want to run both checks.
		return hash_equals($this->getToken(), $tokenString);
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

call_user_func(
	function () {
		// Initialize the plugin
		$panopticon = PanopticonPlugin::getInstance();

		// Register the various activation hooks
		register_activation_hook(__FILE__, [PanopticonPlugin::class, 'activation']);
		register_deactivation_hook(__FILE__, [PanopticonPlugin::class, 'deactivation']);

		// Register admin hooks
		add_action('admin_menu', [$panopticon, 'registerAdminMenu']);
		add_filter('determine_current_user', [$panopticon, 'authorizeAPIUser'], 20);

		// Register the options page
		$panopticon->initOptionsPageHandling();

		// WP-CLI integration
		if (defined('WP_CLI') && WP_CLI)
		{
			$panopticon->registerWpCliCommands();
		}
	}
);

