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
 * 1.0.3: version badges under the title of every ACP tab, credits at the bottom.
 */
class v_1_0_3 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['zodiac_version']) && version_compare($this->config['zodiac_version'], '1.0.3', '>=');
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\zodiacsigns\migrations\v_1_0_2'];
	}

	public function update_data()
	{
		return [
			['config.update', ['zodiac_version', '1.0.3']],
		];
	}
}
