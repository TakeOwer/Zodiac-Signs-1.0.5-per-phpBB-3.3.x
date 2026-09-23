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
 * 1.0.2: version/system footer in the ACP pages, update-engine and decryption fixes.
 */
class v_1_0_2 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['zodiac_version']) && version_compare($this->config['zodiac_version'], '1.0.2', '>=');
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\zodiacsigns\migrations\v_1_0_1'];
	}

	public function update_data()
	{
		return [
			['config.update', ['zodiac_version', '1.0.2']],
		];
	}
}
