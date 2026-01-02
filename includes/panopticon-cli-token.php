<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */


defined('WPINC') || die;

/**
 * Manage the Akeeba Panopticon connector plugin's API token
 *
 * @since  1.0.0
 */
class Panopticon_Cli_Token
{
	/**
	 * Returns the Panopticon API Token.
	 *
	 * If the token does not exist, it is created afresh.
	 *
	 * ## OPTIONS
	 *
	 * [--porcelain]
	 * : Return just the token
	 *
	 * ## EXAMPLES
	 *
	 *  wp panopticon token get
	 *
	 *  wp panopticon token get --porcelain
	 *
	 * @when    after_wp_load
	 *
	 * @param   array  $args        Positional arguments (literal arguments)
	 * @param   array  $assoc_args  Associative arguments (--flag, --no-flag, --key=value)
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function get($args, $assoc_args)
	{
		$panopticon = PanopticonPlugin::getInstance();
		$token      = $panopticon->getToken();
		$porcelain  = ($assoc_args['porcelain'] ?? null) && $assoc_args['porcelain'];

		if ($porcelain)
		{
			echo $token;

			return;
		}

		_e('Akeeba Panopticon connection information', 'panopticon');
		echo "\n";
		echo str_repeat('-', 78) . "\n\n";

		_e('Endpoint URL', 'panopticon');
		echo ": " . get_rest_url() . "\n";
		_e('Token', 'panopticon');
		echo ": " . $token  . "\n";
	}

	/**
	 * Resets the Panopticon API Token.
	 *
	 * ## OPTIONS
	 *
	 *  [--porcelain]
	 *  : Return just the new token
	 *
	 *  ## EXAMPLES
	 *
	 *   wp panopticon token reset
	 *
	 *   wp panopticon token reset --porcelain
	 *
	 * @when    after_wp_load
	 *
	 * @param   array  $args        Positional arguments (literal arguments)
	 * @param   array  $assoc_args  Associative arguments (--flag, --no-flag, --key=value)
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function reset($args, $assoc_args)
	{
		delete_option('panopticon_token');

		$this->get($args, $assoc_args);
	}
}