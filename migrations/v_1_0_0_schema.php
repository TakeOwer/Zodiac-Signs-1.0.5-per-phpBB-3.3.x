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

class v_1_0_0_schema extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'users', 'user_zodiac_sign')
			&& $this->db_tools->sql_table_exists($this->table_prefix . 'zodiac_providers')
			&& $this->db_tools->sql_table_exists($this->table_prefix . 'zodiac_horoscopes');
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'users' => [
					'user_zodiac_sign' => ['TINT:3', 0],
				],
			],
			'add_tables' => [
				$this->table_prefix . 'zodiac_providers' => [
					'COLUMNS' => [
						'provider_id'          => ['UINT', null, 'auto_increment'],
						'provider_name'        => ['VCHAR_UNI:100', ''],
						'provider_lang'        => ['VCHAR:8', 'en'],
						'provider_url_weekly'  => ['TEXT_UNI', ''],
						'provider_url_monthly' => ['TEXT_UNI', ''],
						'provider_sign_format' => ['VCHAR:16', 'en_lower'],
						'provider_auth_header' => ['VCHAR:100', ''],
						'provider_auth_value'  => ['TEXT_UNI', ''],
						'provider_headers'     => ['TEXT_UNI', ''],
						'provider_json_path'   => ['VCHAR:255', ''],
						'provider_priority'    => ['USINT', 10],
						'provider_enabled'     => ['BOOL', 1],
						'provider_builtin'     => ['BOOL', 0],
						'provider_status'      => ['VCHAR_UNI:255', ''],
						'provider_checked'     => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'provider_id',
				],
				$this->table_prefix . 'zodiac_horoscopes' => [
					'COLUMNS' => [
						'horo_id'     => ['UINT', null, 'auto_increment'],
						'sign_key'    => ['VCHAR:16', ''],
						'horo_period' => ['VCHAR:8', ''],
						'horo_lang'   => ['VCHAR:8', ''],
						'horo_text'   => ['MTEXT_UNI', ''],
						'period_key'  => ['VCHAR:10', ''],
						'provider_id' => ['UINT', 0],
						'horo_time'   => ['TIMESTAMP', 0],
						'horo_manual' => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'horo_id',
					'KEYS' => [
						'sign_per_lng' => ['UNIQUE', ['sign_key', 'horo_period', 'horo_lang']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'users' => ['user_zodiac_sign'],
			],
			'drop_tables' => [
				$this->table_prefix . 'zodiac_providers',
				$this->table_prefix . 'zodiac_horoscopes',
			],
		];
	}
}
