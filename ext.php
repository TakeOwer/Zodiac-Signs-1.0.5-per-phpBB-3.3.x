<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns;

class ext extends \phpbb\extension\base
{
	public function is_enableable()
	{
		$config = $this->container->get('config');

		return phpbb_version_compare($config['version'], '3.3.0', '>=')
			&& version_compare(PHP_VERSION, '8.0.0', '>=');
	}
}
