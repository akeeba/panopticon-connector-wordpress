<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * Handles the plugin options
 *
 * @since  1.0.2
 * @link   https://developer.wordpress.org/plugins/settings/custom-settings-page/
 */
trait Panopticon_Options_Trait
{
	/**
	 * Register the options page's handling.
	 *
	 * @return  void
	 * @since   1.0.2
	 */
	public function initOptionsPageHandling()
	{
		// Register options and their handling
		add_action('admin_init', [$this, 'registerOptions']);
	}

	/**
	 * Renders a message at the top of a section of the Options page
	 *
	 * @param   array  $args
	 *
	 * @return  void
	 * @since   1.0.2
	 */
	public function renderOptionsSection(array $args = [])
	{
		// Intentionally left blank
	}

	/**
	 * Render a field: sysinfo
	 *
	 * @param   array  $args
	 *
	 * @return  void
	 * @since   1.0.02
	 */
	public function renderFieldSysinfo(array $args = [])
	{
		// Get the value of the setting we've registered with registerOptionsPage()
		$options = get_option('panopticon_options');
		$id      = esc_attr($args['label_for']);
		$value   = $options[$args['label_for']] ?? 1;
		$sel1    = $value == 1 ? 'selected' : '';
		$sel0    = $value != 1 ? 'selected' : '';
		$txtYes  = __('Yes', 'panopticon');
		$txtNo   = __('No', 'panopticon');
		$description = __('Collect and report system information such as CPU usage, disk usage etc. This is useful for troubleshooting server performance issue, but some hosts like to pretend you are not allowed to collect it, and may give you a hard time. In this case, disable this feature until you move your site to a decent host.', 'panopticon');

		echo <<< HTML
<select id="$id" name="panopticon_options[$id]">
	<option value="1" $sel1>$txtYes</option>
	<option value="0" $sel0>$txtNo</option>
</select>
<p class="description">
	$description
</p>
HTML;

	}

	/**
	 * Registers the plugin options, and their handling, with WordPress
	 *
	 * @return  void
	 * @since   1.0.2
	 */
	public function registerOptions()
	{
		// Register a new setting for the "panopticon" page.
		register_setting('panopticon', 'panopticon_options');

		// Register a new section in the "panopticon" page.
		add_settings_section(
			'panopticon_section_prefs',
			__('Preferences', 'panopticon'),
			[$this, 'renderOptionsSection'],
			'panopticon'
		);

		// Register a new field in the "panopticon_section_prefs" section, inside the "panopticon" page.
		add_settings_field(
			'panopticon_field_sysinfo',
			__('Report System Information', 'panopticon'),
			[$this, 'renderFieldSysinfo'],
			'panopticon',
			'panopticon_section_prefs',
			[
				'label_for' => 'panopticon_field_sysinfo',
				'class'     => 'panopticon_row',
			]
		);
	}
}