<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns\migrations;

/**
 * 1.0.1: check-up tab in the ACP.
 */
class v_1_0_1 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['zodiac_version']) && version_compare($this->config['zodiac_version'], '1.0.1', '>=');
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\zodiacsigns\migrations\v_1_0_0_data'];
	}

	public function update_data()
	{
		return [
			['module.add', ['acp', 'ACP_ZODIAC_TITLE', [
				'module_basename' => '\salvocortesiano\zodiacsigns\acp\main_module',
				'modes'           => ['diagnostics'],
			]]],

			// Always last
			['config.update', ['zodiac_version', '1.0.1']],
		];
	}
}
