<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns\acp;

class main_info
{
	public function module()
	{
		$auth = 'ext_salvocortesiano/zodiacsigns && acl_a_board';

		return [
			'filename' => '\salvocortesiano\zodiacsigns\acp\main_module',
			'title'    => 'ACP_ZODIAC_TITLE',
			'modes'    => [
				'settings'  => ['title' => 'ACP_ZODIAC_SETTINGS',  'auth' => $auth, 'cat' => ['ACP_ZODIAC_TITLE']],
				'signs'     => ['title' => 'ACP_ZODIAC_SIGNS',     'auth' => $auth, 'cat' => ['ACP_ZODIAC_TITLE']],
				'horoscope' => ['title' => 'ACP_ZODIAC_HOROSCOPE', 'auth' => $auth, 'cat' => ['ACP_ZODIAC_TITLE']],
				'providers' => ['title' => 'ACP_ZODIAC_PROVIDERS', 'auth' => $auth, 'cat' => ['ACP_ZODIAC_TITLE']],
				'diagnostics' => ['title' => 'ACP_ZODIAC_DIAGNOSTICS', 'auth' => $auth, 'cat' => ['ACP_ZODIAC_TITLE']],
			],
		];
	}
}
