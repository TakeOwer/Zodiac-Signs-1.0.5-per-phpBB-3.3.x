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
 * 1.0.5: sign calculated from the birthday for members who have not chosen one.
 */
class v_1_0_5 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['zodiac_version']) && version_compare($this->config['zodiac_version'], '1.0.5', '>=');
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\zodiacsigns\migrations\v_1_0_4'];
	}

	public function update_data()
	{
		return [
			['config.add', ['zodiac_auto_birthday', 1]],

			// Always last
			['config.update', ['zodiac_version', '1.0.5']],
		];
	}
}
