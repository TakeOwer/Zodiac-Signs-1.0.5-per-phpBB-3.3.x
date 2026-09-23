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
 * Note: no container services here, extension services do not exist yet while the
 * extension is being enabled. The version key is added LAST, so a failed install
 * is never reported as installed.
 */
class v_1_0_0_data extends \phpbb\db\migration\migration
{
	protected const SIGN_KEYS = ['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'];

	public function effectively_installed()
	{
		return isset($this->config['zodiac_version']);
	}

	public static function depends_on()
	{
		return ['\salvocortesiano\zodiacsigns\migrations\v_1_0_0_schema'];
	}

	public function update_data()
	{
		$data = [
			['config.add', ['zodiac_enable', 1]],
			['config.add', ['zodiac_register_mode', 2]],
			['config.add', ['zodiac_ucp_required', 0]],
			['config.add', ['zodiac_remind', 1]],
			['config.add', ['zodiac_groups', '']],
			['config.add', ['zodiac_show_viewtopic', 1]],
			['config.add', ['zodiac_show_profile', 1]],
			['config.add', ['zodiac_badge_size', 32]],
			['config.add', ['zodiac_img_maxsize', 512]],

			['config.add', ['zodiac_horo_weekly', 1]],
			['config.add', ['zodiac_horo_monthly', 1]],
			['config.add', ['zodiac_horo_source', 'free']],
			['config.add', ['zodiac_horo_display', 'user']],
			['config.add', ['zodiac_horo_interval', 6]],
			['config.add', ['zodiac_horo_credit', 1]],
			['config.add', ['zodiac_horo_last_run', 0, true]],
			['config.add', ['zodiac_horo_pending', 1, true]],

			['config_text.add', ['zodiac_secret', base64_encode(random_bytes(32))]],
			['config_text.add', ['zodiac_horo_report', '']],
		];

		foreach (self::SIGN_KEYS as $key)
		{
			$data[] = ['config.add', ['zodiac_img_' . $key, '']];
		}

		$data[] = ['custom', [[$this, 'insert_builtin_providers']]];

		$data[] = ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_ZODIAC_TITLE']];
		$data[] = ['module.add', ['acp', 'ACP_ZODIAC_TITLE', [
			'module_basename' => '\salvocortesiano\zodiacsigns\acp\main_module',
			'modes'           => ['settings', 'signs', 'horoscope', 'providers'],
		]]];

		// Always last
		$data[] = ['config.add', ['zodiac_version', '1.0.0']];

		return $data;
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_uploads']]],
		];
	}

	/**
	 * Free English sources, verified working on 22 September 2026.
	 */
	public function insert_builtin_providers()
	{
		$table = $this->table_prefix . 'zodiac_providers';

		$sql = 'SELECT COUNT(provider_id) AS total FROM ' . $table . ' WHERE provider_builtin = 1';
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		if ($total)
		{
			return;
		}

		$rows = [
			[
				'provider_name'        => 'Free Horoscope API',
				'provider_lang'        => 'en',
				'provider_url_weekly'  => 'https://freehoroscopeapi.com/api/v1/get-horoscope/weekly?sign={sign}',
				'provider_url_monthly' => 'https://freehoroscopeapi.com/api/v1/get-horoscope/monthly?sign={sign}',
				'provider_sign_format' => 'en_lower',
				'provider_auth_header' => '',
				'provider_auth_value'  => '',
				'provider_headers'     => '',
				'provider_json_path'   => 'data.horoscope|data.horoscope_data',
				'provider_priority'    => 10,
				'provider_enabled'     => 1,
				'provider_builtin'     => 1,
				'provider_status'      => '',
				'provider_checked'     => 0,
			],
			[
				'provider_name'        => 'Horoscope App API (Vercel)',
				'provider_lang'        => 'en',
				'provider_url_weekly'  => 'https://horoscope-app-api.vercel.app/api/v1/get-horoscope/weekly?sign={sign}',
				'provider_url_monthly' => 'https://horoscope-app-api.vercel.app/api/v1/get-horoscope/monthly?sign={sign}',
				'provider_sign_format' => 'en_lower',
				'provider_auth_header' => '',
				'provider_auth_value'  => '',
				'provider_headers'     => '',
				'provider_json_path'   => 'data.horoscope|data.horoscope_data',
				'provider_priority'    => 20,
				'provider_enabled'     => 1,
				'provider_builtin'     => 1,
				'provider_status'      => '',
				'provider_checked'     => 0,
			],
		];

		$this->db->sql_multi_insert($table, $rows);
	}

	public function remove_uploads()
	{
		$dir = $this->phpbb_root_path . 'images/zodiacsigns/';

		if (!is_dir($dir))
		{
			return;
		}

		foreach (glob($dir . '*') ?: [] as $file)
		{
			if (is_file($file))
			{
				@unlink($file);
			}
		}

		@rmdir($dir);
	}
}
