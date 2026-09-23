<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns\core;

/**
 * ACP check-up: verifies every part of the extension and reports what to fix.
 *
 * Each check adds a row with a status (ok, warn, error, info), a label and a
 * plain-text detail. Network tests run only when asked.
 */
class diagnostics
{
	public const OK = 'ok';
	public const WARN = 'warn';
	public const ERROR = 'error';
	public const INFO = 'info';

	public const CRON_NAME = 'salvocortesiano.zodiacsigns.cron.horoscope';

	/** Template events used by the extension => prosilver file that must contain them */
	protected const EVENTS = [
		'ucp_register_profile_fields_after'          => 'ucp_register.html',
		'ucp_profile_profile_info_after'             => 'ucp_profile_profile_info.html',
		'viewtopic_body_postrow_custom_fields_after' => 'viewtopic_body.html',
		'memberlist_view_user_statistics_after'      => 'memberlist_view.html',
		'memberlist_view_content_append'             => 'memberlist_view.html',
		'overall_header_head_append'                 => 'overall_header.html',
		'overall_header_content_before'              => 'overall_header.html',
		'overall_footer_after'                       => 'overall_footer.html',
	];

	/** [month, day, expected sign id] on both sides of every boundary */
	protected const DATE_CASES = [
		[1, 19, 10], [1, 20, 11], [2, 18, 11], [2, 19, 12], [3, 8, 12], [3, 20, 12], [3, 21, 1],
		[4, 19, 1], [4, 20, 2], [5, 20, 2], [5, 21, 3], [6, 20, 3], [6, 21, 4], [7, 22, 4],
		[7, 23, 5], [8, 22, 5], [8, 23, 6], [9, 22, 6], [9, 23, 7], [10, 22, 7], [10, 23, 8],
		[11, 21, 8], [11, 22, 9], [12, 21, 9], [12, 22, 10], [12, 31, 10], [1, 1, 10],
	];

	protected $config;
	protected $config_text;
	protected $db;
	protected $db_tools;
	protected $language;
	protected $cron_manager;
	protected $helper;
	protected $zodiac;
	protected $horoscope;
	protected $controller;
	protected $root_path;
	protected $providers_table;
	protected $horoscopes_table;

	/** @var array */
	protected $sections = [];

	/** @var int */
	protected $current = -1;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\config\db_text $config_text,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\db\tools\tools_interface $db_tools,
		\phpbb\language\language $language,
		\phpbb\cron\manager $cron_manager,
		\phpbb\controller\helper $helper,
		zodiac $zodiac,
		horoscope $horoscope,
		\salvocortesiano\zodiacsigns\controller\main $controller,
		$root_path,
		$providers_table,
		$horoscopes_table
	)
	{
		$this->config           = $config;
		$this->config_text      = $config_text;
		$this->db               = $db;
		$this->db_tools         = $db_tools;
		$this->language         = $language;
		$this->cron_manager     = $cron_manager;
		$this->helper           = $helper;
		$this->zodiac           = $zodiac;
		$this->horoscope        = $horoscope;
		$this->controller       = $controller;
		$this->root_path        = $root_path;
		$this->providers_table  = $providers_table;
		$this->horoscopes_table = $horoscopes_table;
	}

	/**
	 * @param bool $live test the providers over the network
	 * @param bool $full with $live: all twelve signs instead of one
	 */
	public function run(bool $live = false, bool $full = false): array
	{
		$this->sections = [];
		$this->current = -1;
		$this->language->add_lang(['common', 'info_acp_zodiac'], 'salvocortesiano/zodiacsigns');

		$this->check_environment();
		$db_ok = $this->check_database();
		$this->check_configuration();
		$this->check_files();
		$this->check_styles();
		$this->check_calculation();

		if ($db_ok)
		{
			$this->check_security();
			$this->check_providers($live, $full);
			$this->check_storage();
			$this->check_cron();
			$this->check_route();
		}

		$summary = [self::OK => 0, self::WARN => 0, self::ERROR => 0, self::INFO => 0];

		foreach ($this->sections as $section)
		{
			foreach ($section['rows'] as $row)
			{
				$summary[$row['status']]++;
			}
		}

		return ['sections' => $this->sections, 'summary' => $summary, 'time' => time(), 'live' => $live, 'full' => $full];
	}

	/* ------------------------------------------------------------------ */

	protected function section(string $key): void
	{
		$this->sections[] = ['title' => $this->language->lang('ACP_ZODIAC_DIAG_S_' . $key), 'rows' => []];
		$this->current = count($this->sections) - 1;
	}

	protected function add(string $status, string $label, string $detail = ''): void
	{
		$this->sections[$this->current]['rows'][] = ['status' => $status, 'label' => $label, 'detail' => $detail];
	}

	protected function l(string $key, ...$args): string
	{
		return $this->language->lang('ACP_ZODIAC_DIAG_' . $key, ...$args);
	}

	protected function ext_path(): string
	{
		return $this->root_path . 'ext/salvocortesiano/zodiacsigns/';
	}

	/* ------------------------------------------------------------------
	 * 1. Server
	 * ------------------------------------------------------------------ */

	protected function check_environment(): void
	{
		$this->section('ENV');

		$this->add(version_compare(PHP_VERSION, '8.0.0', '>=') ? self::OK : self::ERROR, 'PHP', PHP_VERSION);
		$this->add(phpbb_version_compare($this->config['version'], '3.3.0', '>=') ? self::OK : self::ERROR, 'phpBB', (string) $this->config['version']);

		$composer = @json_decode((string) @file_get_contents($this->ext_path() . 'composer.json'), true);
		$file_version = is_array($composer) ? (string) ($composer['version'] ?? '') : '';
		$db_version = (string) ($this->config['zodiac_version'] ?? '');

		if ($file_version !== '' && $file_version !== $db_version)
		{
			$this->add(self::WARN, $this->l('EXT_VERSION'), $this->l('EXT_VERSION_MISMATCH', $file_version, $db_version));
		}
		else
		{
			$this->add(self::OK, $this->l('EXT_VERSION'), $db_version);
		}

		if (function_exists('curl_init'))
		{
			$v = curl_version();
			$this->add(self::OK, 'cURL', $v['version'] . ' / ' . ($v['ssl_version'] ?? '-'));
		}
		else if (ini_get('allow_url_fopen'))
		{
			$this->add(self::WARN, 'cURL', $this->l('NO_CURL_FOPEN'));
		}
		else
		{
			$this->add(self::ERROR, 'cURL', $this->l('NO_HTTP'));
		}

		$this->add(extension_loaded('openssl') ? self::OK : self::ERROR, 'OpenSSL (HTTPS)', extension_loaded('openssl') ? (string) OPENSSL_VERSION_TEXT : $this->l('MISSING'));

		if (function_exists('sodium_crypto_secretbox'))
		{
			$this->add(self::OK, $this->l('CRYPTO'), 'libsodium');
		}
		else if (function_exists('openssl_encrypt'))
		{
			$this->add(self::OK, $this->l('CRYPTO'), 'OpenSSL AES-256-GCM');
		}
		else
		{
			$this->add(self::ERROR, $this->l('CRYPTO'), $this->l('MISSING'));
		}

		$this->add(function_exists('json_decode') ? self::OK : self::ERROR, 'JSON', function_exists('json_decode') ? '' : $this->l('MISSING'));
		$this->add(ini_get('file_uploads') ? self::OK : self::WARN, $this->l('UPLOADS'), $this->l('UPLOADS_DETAIL', ini_get('upload_max_filesize'), ini_get('post_max_size')));
		$this->add(self::INFO, 'max_execution_time', (string) ini_get('max_execution_time') . ' s');
	}

	/* ------------------------------------------------------------------
	 * 2. Database
	 * ------------------------------------------------------------------ */

	protected function check_database(): bool
	{
		$this->section('DB');
		$ok = true;

		$col = $this->db_tools->sql_column_exists(USERS_TABLE, 'user_zodiac_sign');
		$this->add($col ? self::OK : self::ERROR, $this->l('COLUMN'), USERS_TABLE . '.user_zodiac_sign');
		$ok = $ok && $col;

		foreach ([$this->providers_table, $this->horoscopes_table] as $table)
		{
			$exists = $this->db_tools->sql_table_exists($table);
			$this->add($exists ? self::OK : self::ERROR, $this->l('TABLE'), $table);
			$ok = $ok && $exists;
		}

		if (!$ok)
		{
			$this->add(self::ERROR, $this->l('DB_BROKEN'), $this->l('DB_BROKEN_FIX'));

			return false;
		}

		$sql = 'SELECT user_zodiac_sign, COUNT(user_id) AS total
			FROM ' . USERS_TABLE . '
			WHERE user_zodiac_sign <> 0
			GROUP BY user_zodiac_sign';
		$result = $this->db->sql_query($sql);
		$total = 0;
		$invalid = 0;
		$parts = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$id = (int) $row['user_zodiac_sign'];

			if ($this->zodiac->valid_id($id))
			{
				$total += (int) $row['total'];
				$parts[] = $this->zodiac->name($id) . ' ' . (int) $row['total'];
			}
			else
			{
				$invalid += (int) $row['total'];
			}
		}
		$this->db->sql_freeresult($result);

		$this->add($total ? self::INFO : self::WARN, $this->l('USERS_WITH_SIGN'), $total . ($parts ? ' (' . implode(', ', $parts) . ')' : ' - ' . $this->l('USERS_NONE_HINT')));

		$counts = $this->zodiac->count_without_sign();

		if ($counts['with_birthday'])
		{
			$this->add(!empty($this->config['zodiac_auto_birthday']) ? self::INFO : self::WARN, $this->l('USERS_BDAY_NO_SIGN'), $this->l(!empty($this->config['zodiac_auto_birthday']) ? 'USERS_BDAY_AUTO_ON' : 'USERS_BDAY_AUTO_OFF', $counts['with_birthday']));
		}

		if ($invalid)
		{
			$this->add(self::WARN, $this->l('USERS_INVALID'), (string) $invalid);
		}

		return true;
	}

	/* ------------------------------------------------------------------
	 * 3. Settings
	 * ------------------------------------------------------------------ */

	protected function check_configuration(): void
	{
		$this->section('CONFIG');

		$this->add($this->zodiac->is_enabled() ? self::OK : self::WARN, $this->l('ENABLED'), $this->zodiac->is_enabled() ? $this->language->lang('YES') : $this->l('DISABLED_ALL'));

		$modes = ['ACP_ZODIAC_REGISTER_OFF', 'ACP_ZODIAC_REGISTER_OPTIONAL', 'ACP_ZODIAC_REGISTER_REQUIRED'];
		$this->add(self::INFO, $this->language->lang('ACP_ZODIAC_REGISTER_MODE'), $this->language->lang($modes[(int) $this->config['zodiac_register_mode']] ?? $modes[0]));

		$groups = $this->zodiac->allowed_groups();

		if (!$groups)
		{
			$this->add(self::INFO, $this->language->lang('ACP_ZODIAC_GROUPS'), $this->l('ALL_GROUPS'));
		}
		else
		{
			$sql = 'SELECT group_id FROM ' . GROUPS_TABLE . ' WHERE ' . $this->db->sql_in_set('group_id', $groups);
			$result = $this->db->sql_query($sql);
			$found = [];

			while ($row = $this->db->sql_fetchrow($result))
			{
				$found[] = (int) $row['group_id'];
			}
			$this->db->sql_freeresult($result);

			$missing = array_diff($groups, $found);
			$this->add($missing ? self::WARN : self::OK, $this->language->lang('ACP_ZODIAC_GROUPS'), $missing
				? $this->l('GROUPS_MISSING', implode(', ', $missing))
				: $this->l('GROUPS_COUNT', count($found)));
		}

		$periods = $this->horoscope->enabled_periods();
		$this->add($periods ? self::OK : self::INFO, $this->language->lang('ACP_ZODIAC_HORO_PERIODS'), $periods
			? implode(', ', array_map(function ($p) {
				return $this->language->lang('ZODIAC_HORO_' . strtoupper($p));
			}, $periods))
			: $this->l('HORO_OFF'));

		$mode = (string) $this->config['zodiac_horo_source'];
		$this->add(in_array($mode, horoscope::SOURCES, true) ? self::INFO : self::WARN, $this->language->lang('ACP_ZODIAC_HORO_SOURCE'), $this->language->lang('ACP_ZODIAC_SOURCE_' . strtoupper(in_array($mode, horoscope::SOURCES, true) ? $mode : 'free')));

		if ($periods && $this->db_tools->sql_table_exists($this->providers_table))
		{
			$chains = $this->horoscope->provider_chains();

			if (!$chains)
			{
				$this->add(self::ERROR, $this->l('CHAINS'), $this->l('CHAINS_NONE'));
			}
			else
			{
				foreach ($chains as $lang => $providers)
				{
					$names = array_map(function ($p) {
						return $p['provider_name'];
					}, $providers);

					$this->add(count($providers) > 1 ? self::OK : self::WARN, $this->l('CHAIN_FOR', $this->language->lang('ACP_ZODIAC_LANG_' . strtoupper($lang))),
						implode(' > ', $names) . (count($providers) > 1 ? '' : ' (' . $this->l('CHAIN_SINGLE') . ')'));
				}

				$display = (string) $this->config['zodiac_horo_display'];

				if (in_array($display, horoscope::LANGS, true) && !isset($chains[$display]))
				{
					$this->add(self::WARN, $this->language->lang('ACP_ZODIAC_HORO_DISPLAY'), $this->l('DISPLAY_NO_SOURCE', $this->language->lang('ACP_ZODIAC_LANG_' . strtoupper($display))));
				}
			}
		}

		$secret = base64_decode((string) $this->config_text->get('zodiac_secret'), true);
		$this->add(($secret !== false && strlen($secret) >= 32) ? self::OK : self::WARN, $this->l('SECRET'), ($secret !== false && strlen($secret) >= 32) ? $this->l('SECRET_OK') : $this->l('SECRET_BAD'));
	}

	/* ------------------------------------------------------------------
	 * 4. Files and images
	 * ------------------------------------------------------------------ */

	protected function check_files(): void
	{
		$this->section('FILES');

		$missing = [];

		foreach (zodiac::SIGNS as $sign)
		{
			if (!is_readable($this->ext_path() . 'styles/all/theme/images/signs/' . $sign[0] . '.svg'))
			{
				$missing[] = $sign[0] . '.svg';
			}
		}

		$this->add($missing ? self::ERROR : self::OK, $this->l('DEFAULT_IMAGES'), $missing ? $this->l('FILES_MISSING', implode(', ', $missing)) : '12/12');

		$custom = 0;

		foreach (zodiac::SIGNS as $id => $sign)
		{
			$file = (string) ($this->config['zodiac_img_' . $sign[0]] ?? '');

			if ($file === '')
			{
				continue;
			}

			$custom++;
			$path = $this->root_path . zodiac::UPLOAD_DIR . basename($file);
			$info = is_file($path) ? @getimagesize($path) : false;

			if (!$info)
			{
				$this->add(self::WARN, $this->l('CUSTOM_IMAGE', $this->zodiac->name($id)), $this->l('CUSTOM_IMAGE_BAD', $file));
			}
			else
			{
				$this->add(($info[0] === $info[1]) ? self::OK : self::INFO, $this->l('CUSTOM_IMAGE', $this->zodiac->name($id)), $file . ', ' . $info[0] . 'x' . $info[1] . ' px' . ($info[0] === $info[1] ? '' : ' (' . $this->l('NOT_SQUARE') . ')'));
			}
		}

		if (!$custom)
		{
			$this->add(self::INFO, $this->l('CUSTOM_IMAGES'), $this->l('CUSTOM_NONE'));
		}

		$this->add($this->zodiac->upload_dir_writable() ? self::OK : self::WARN, $this->l('UPLOAD_DIR'), zodiac::UPLOAD_DIR . ($this->zodiac->upload_dir_writable() ? '' : ' - ' . $this->l('NOT_WRITABLE')));

		foreach (['styles/all/theme/zodiacsigns.css', 'styles/all/template/zodiacsigns.js', 'styles/all/template/zodiac_picker.html'] as $asset)
		{
			$this->add(is_readable($this->ext_path() . $asset) ? self::OK : self::ERROR, basename($asset), is_readable($this->ext_path() . $asset) ? '' : $this->l('MISSING'));
		}

		// Language files: presence and same keys in Italian and English
		$keys = [];

		foreach (['it', 'en'] as $iso)
		{
			foreach (['common', 'info_acp_zodiac'] as $file)
			{
				$path = $this->ext_path() . 'language/' . $iso . '/' . $file . '.php';

				if (!is_readable($path))
				{
					$this->add(self::ERROR, $this->l('LANG_FILE'), $iso . '/' . $file . '.php - ' . $this->l('MISSING'));
					continue;
				}

				$keys[$iso][$file] = array_keys($this->load_lang_file($path));
			}
		}

		foreach (['common', 'info_acp_zodiac'] as $file)
		{
			if (!isset($keys['it'][$file], $keys['en'][$file]))
			{
				continue;
			}

			$diff = array_merge(array_diff($keys['it'][$file], $keys['en'][$file]), array_diff($keys['en'][$file], $keys['it'][$file]));
			$this->add($diff ? self::WARN : self::OK, $this->l('LANG_FILE') . ' ' . $file, $diff
				? $this->l('LANG_DIFF', implode(', ', array_slice($diff, 0, 10)))
				: $this->l('LANG_KEYS', count($keys['it'][$file])));
		}

		$sql = 'SELECT lang_iso FROM ' . LANG_TABLE;
		$result = $this->db->sql_query($sql);
		$others = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			if (!in_array($row['lang_iso'], ['it', 'en'], true) && !is_dir($this->ext_path() . 'language/' . $row['lang_iso']))
			{
				$others[] = $row['lang_iso'];
			}
		}
		$this->db->sql_freeresult($result);

		if ($others)
		{
			$this->add(self::INFO, $this->l('BOARD_LANGS'), $this->l('BOARD_LANGS_FALLBACK', implode(', ', $others)));
		}
	}

	protected function load_lang_file(string $path): array
	{
		$lang = [];
		include $path;

		return is_array($lang) ? $lang : [];
	}

	/* ------------------------------------------------------------------
	 * 5. Styles: are the template events present?
	 * ------------------------------------------------------------------ */

	protected function check_styles(): void
	{
		$this->section('STYLES');

		$sql = 'SELECT style_id, style_name, style_path, style_parent_tree
			FROM ' . STYLES_TABLE . '
			WHERE style_active = 1
			ORDER BY style_name';
		$result = $this->db->sql_query($sql);
		$styles = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($styles as $style)
		{
			$chain = [$style['style_path']];

			if (trim((string) $style['style_parent_tree']) !== '')
			{
				$chain = array_merge($chain, array_reverse(explode('/', $style['style_parent_tree'])));
			}

			$missing = [];
			$not_found = [];
			$cache = [];

			foreach (self::EVENTS as $event => $file)
			{
				if (!array_key_exists($file, $cache))
				{
					$cache[$file] = null;

					// The first file found in the inheritance chain is the one phpBB uses
					foreach ($chain as $path)
					{
						$full = $this->root_path . 'styles/' . $path . '/template/' . $file;

						if (is_readable($full))
						{
							$cache[$file] = (string) file_get_contents($full);
							break;
						}
					}
				}

				if ($cache[$file] === null)
				{
					$not_found[] = $file;
				}
				else if (!preg_match('/EVENT\s+' . preg_quote($event, '/') . '\b/', $cache[$file]))
				{
					$missing[] = $event . ' (' . $file . ')';
				}
			}

			$label = $style['style_name'] . ((int) $style['style_id'] === (int) $this->config['default_style'] ? ' (' . $this->l('DEFAULT_STYLE') . ')' : '');

			if ($missing)
			{
				$this->add(self::ERROR, $label, $this->l('EVENTS_MISSING', implode(', ', $missing)));
			}
			else if ($not_found)
			{
				$this->add(self::WARN, $label, $this->l('TEMPLATES_NOT_FOUND', implode(', ', array_unique($not_found))));
			}
			else
			{
				$this->add(self::OK, $label, $this->l('EVENTS_OK', count(self::EVENTS)));
			}
		}
	}

	/* ------------------------------------------------------------------
	 * 6. Birthday -> sign
	 * ------------------------------------------------------------------ */

	protected function check_calculation(): void
	{
		$this->section('CALC');

		$fails = [];

		foreach (self::DATE_CASES as $case)
		{
			[$m, $d, $expected] = $case;
			$got = $this->zodiac->sign_from_date($d, $m);

			if ($got !== $expected)
			{
				$fails[] = $d . '/' . $m . ' = ' . $this->zodiac->name($got) . ' (' . $this->zodiac->name($expected) . ')';
			}
		}

		$this->add($fails ? self::ERROR : self::OK, $this->l('BOUNDARIES'), $fails ? implode('; ', $fails) : $this->l('BOUNDARIES_OK', count(self::DATE_CASES)));

		$holes = 0;

		for ($t = gmmktime(0, 0, 0, 1, 1, 2024); $t < gmmktime(0, 0, 0, 1, 1, 2025); $t += 86400)
		{
			if (!$this->zodiac->sign_from_date((int) gmdate('j', $t), (int) gmdate('n', $t)))
			{
				$holes++;
			}
		}

		$this->add($holes ? self::ERROR : self::OK, $this->l('FULL_YEAR'), $holes ? $this->l('FULL_YEAR_HOLES', $holes) : '366/366');
		$this->add(self::INFO, $this->l('EXAMPLE'), $this->l('EXAMPLE_DETAIL', $this->zodiac->name($this->zodiac->sign_from_birthday(' 8- 3-1970'))));
	}

	/* ------------------------------------------------------------------
	 * 7. Keys
	 * ------------------------------------------------------------------ */

	protected function check_security(): void
	{
		$this->section('SECURITY');

		$probe = 'zodiac-' . bin2hex(random_bytes(6));
		$enc = $this->horoscope->encrypt($probe);
		$ok = $enc !== '' && $this->horoscope->decrypt($enc) === $probe;
		$this->add($ok ? self::OK : self::ERROR, $this->l('ROUNDTRIP'), $ok ? substr($enc, 0, 3) . ' ' . $this->l('ROUNDTRIP_OK') : $this->l('ROUNDTRIP_FAIL'));

		$with_key = 0;

		foreach ($this->horoscope->get_providers() as $p)
		{
			$url = $p['provider_url_weekly'] . ' ' . $p['provider_url_monthly'];
			$has_key = (string) $p['provider_auth_value'] !== '';
			$needs_key = strpos($url, '{key}') !== false || trim((string) $p['provider_auth_header']) !== '';

			if ($has_key)
			{
				$with_key++;
				$plain = $this->horoscope->decrypt((string) $p['provider_auth_value']);
				$this->add($plain !== '' ? self::OK : self::ERROR, $p['provider_name'], $plain !== ''
					? $this->l('KEY_OK', $this->mask($plain))
					: $this->l('KEY_UNREADABLE'));
			}
			else if ($needs_key)
			{
				$this->add(self::ERROR, $p['provider_name'], $this->l('KEY_REQUIRED'));
			}
		}

		if (!$with_key)
		{
			$this->add(self::INFO, $this->l('KEYS'), $this->l('KEYS_NONE'));
		}
	}

	protected function mask(string $key): string
	{
		$len = strlen($key);

		return $len <= 8 ? str_repeat('*', $len) : substr($key, 0, 3) . str_repeat('*', min(12, $len - 6)) . substr($key, -3) . ' (' . $len . ')';
	}

	/* ------------------------------------------------------------------
	 * 8. Sources
	 * ------------------------------------------------------------------ */

	protected function check_providers(bool $live, bool $full): void
	{
		$this->section('PROVIDERS');

		$providers = $this->horoscope->get_providers();

		if (!$providers)
		{
			$this->add(self::ERROR, $this->l('PROVIDERS'), $this->language->lang('ACP_ZODIAC_NO_PROVIDERS'));

			return;
		}

		$mode = (string) $this->config['zodiac_horo_source'];
		$periods = $this->horoscope->enabled_periods() ?: horoscope::PERIODS;

		if ($live)
		{
			@set_time_limit($full ? 600 : 180);
		}

		foreach ($providers as $p)
		{
			$builtin = !empty($p['provider_builtin']);
			$in_use = $p['provider_enabled'] && !(($mode === 'free' && !$builtin) || ($mode === 'api' && $builtin));
			$name = $p['provider_name'] . ' [' . strtoupper($p['provider_lang']) . ', ' . ($builtin ? $this->language->lang('ACP_ZODIAC_TAG_FREE') : $this->language->lang('ACP_ZODIAC_TAG_API')) . ']';

			$this->add($in_use ? self::INFO : self::INFO, $name, $in_use ? $this->l('IN_USE') : ($p['provider_enabled'] ? $this->language->lang('ACP_ZODIAC_TAG_UNUSED') : $this->language->lang('ACP_ZODIAC_TAG_OFF')));

			// Static checks
			$problems = [];

			foreach (['weekly' => 'provider_url_weekly', 'monthly' => 'provider_url_monthly'] as $period => $field)
			{
				$url = trim((string) $p[$field]);

				if ($url === '')
				{
					if (in_array($period, $this->horoscope->enabled_periods(), true))
					{
						$this->add(self::WARN, $name, $this->l('NO_URL_FOR', $this->language->lang('ZODIAC_HORO_' . strtoupper($period))));
					}
					continue;
				}

				if (!preg_match('#^https?://[^\s]+$#i', $url))
				{
					$problems[] = $this->l('URL_INVALID', $period);
				}

				if (strpos($url, '{sign}') === false)
				{
					$problems[] = $this->l('URL_NO_SIGN', $period);
				}

				if (stripos($url, 'http://') === 0 && ((string) $p['provider_auth_value'] !== '' || strpos($url, '{key}') !== false))
				{
					$problems[] = $this->l('URL_NOT_HTTPS', $period);
				}

				if (preg_match_all('/\{([a-z]+)\}/', $url, $m))
				{
					$unknown = array_diff($m[1], ['sign', 'period', 'lang', 'date', 'week', 'month', 'year', 'key']);

					if ($unknown)
					{
						$problems[] = $this->l('URL_UNKNOWN_PH', implode(', ', $unknown));
					}
				}
			}

			if (!in_array($p['provider_sign_format'], horoscope::SIGN_FORMATS, true))
			{
				$problems[] = $this->l('BAD_FORMAT', $p['provider_sign_format']);
			}

			if ($p['provider_lang'] === 'it' && in_array($p['provider_sign_format'], ['en_lower', 'en_ucfirst'], true))
			{
				$this->add(self::INFO, $name, $this->l('IT_WITH_EN_SIGN'));
			}

			foreach (preg_split('/\R/', (string) $p['provider_headers']) as $line)
			{
				if (trim($line) !== '' && !preg_match('/^[A-Za-z0-9\-_]+\s*:\s*\S/', trim($line)))
				{
					$problems[] = $this->l('BAD_HEADER_LINE', trim($line));
				}
			}

			if (trim((string) $p['provider_json_path']) !== '' && !preg_match('/^[A-Za-z0-9_.\-|]+$/', $p['provider_json_path']))
			{
				$problems[] = $this->l('BAD_JSON_PATH', $p['provider_json_path']);
			}

			$this->add($problems ? self::WARN : self::OK, $name, $problems ? implode('; ', $problems) : $this->l('CONFIG_OK'));

			if (!$live)
			{
				continue;
			}

			// Network tests
			$status = '';

			foreach ($periods as $period)
			{
				$field = $period === 'weekly' ? 'provider_url_weekly' : 'provider_url_monthly';

				if (trim((string) $p[$field]) === '')
				{
					continue;
				}

				$signs = $full ? array_keys(zodiac::SIGNS) : [12];
				$ok = 0;
				$ms = 0;
				$texts = [];
				$first = null;
				$errors = [];

				foreach ($signs as $sign_id)
				{
					$res = $this->horoscope->fetch_from_provider($p, $sign_id, $period);
					$ms += (int) $res['ms'];
					$first = $first ?? $res;

					if ($res['ok'])
					{
						$ok++;
						$texts[] = md5($res['text']);
					}
					else
					{
						$errors[] = $this->zodiac->key_from_id($sign_id) . ': ' . $res['error'];
					}
				}

				$label = $name . ' - ' . $this->language->lang('ZODIAC_HORO_' . strtoupper($period));
				$avg = (int) round($ms / max(1, count($signs)));

				if (!$ok)
				{
					$status = $first['error'];
					$this->add(self::ERROR, $label, $this->l('LIVE_FAIL', $first['error'], $first['url'], $first['code'] ?: '-', $avg));
					continue;
				}

				$status = $status ?: 'OK';

				// Single sign test: show what arrived
				if (!$full)
				{
					$len = utf8_strlen($first['text']);
					$level = $len < 40 ? self::WARN : self::OK;
					$detail = $this->l('LIVE_OK', $first['code'], $first['ms'], round($first['bytes'] / 1024, 1), $first['path'] ?: '-', $len) . "\n"
						. $first['url'] . "\n\"" . utf8_substr($first['text'], 0, 160) . (utf8_strlen($first['text']) > 160 ? '…"' : '"');

					if ($len < 40)
					{
						$detail .= "\n" . $this->l('TEXT_SHORT');
					}

					$this->add($level, $label, $detail);
				}
				else
				{
					$level = $ok === count($signs) ? self::OK : self::WARN;
					$this->add($level, $label, $this->l('LIVE_FULL', $ok, count($signs), $avg) . ($errors ? "\n" . implode('; ', array_slice($errors, 0, 6)) : ''));

					if (count($texts) > 1 && count(array_unique($texts)) === 1)
					{
						$this->add(self::ERROR, $label, $this->l('SAME_TEXT'));
					}
				}

				if ($first['path'] !== '' && $first['path'] !== '(text)' && trim((string) $p['provider_json_path']) !== '' && !$this->horoscope->path_is_configured((string) $p['provider_json_path'], $first['path']))
				{
					$this->add(self::WARN, $label, $this->l('PATH_FALLBACK', $first['path']));
				}
				else if ($first['path'] !== '' && trim((string) $p['provider_json_path']) === '')
				{
					$this->add(self::INFO, $label, $this->l('PATH_AUTO', $first['path']));
				}

				if ($avg > 5000)
				{
					$this->add(self::WARN, $label, $this->l('SLOW', $avg));
				}
			}

			if ($status !== '')
			{
				$this->horoscope->save_provider([
					'provider_status'  => utf8_substr($status, 0, 250),
					'provider_checked' => time(),
				], (int) $p['provider_id']);
			}
		}

		if (!$live)
		{
			$this->add(self::INFO, $this->l('LIVE'), $this->l('LIVE_SKIPPED'));
		}
	}

	/* ------------------------------------------------------------------
	 * 9. Stored texts
	 * ------------------------------------------------------------------ */

	protected function check_storage(): void
	{
		$this->section('STORAGE');

		$periods = $this->horoscope->enabled_periods();

		if (!$periods)
		{
			$this->add(self::INFO, $this->l('TEXTS'), $this->l('HORO_OFF'));

			return;
		}

		$all = $this->horoscope->load_all();
		$langs = $this->horoscope->active_langs() ?: ['en'];

		foreach ($langs as $lang)
		{
			foreach ($periods as $period)
			{
				$current = $this->horoscope->period_key($period);
				$now = 0;
				$old = 0;
				$manual = 0;

				foreach (zodiac::SIGNS as $sign)
				{
					$row = $all[$sign[0]][$period][$lang] ?? null;

					if (!$row || trim($row['horo_text']) === '')
					{
						continue;
					}

					if ($row['period_key'] === $current)
					{
						$now++;
					}
					else
					{
						$old++;
					}

					if ($row['horo_manual'])
					{
						$manual++;
					}
				}

				$missing = 12 - $now - $old;
				$status = $now === 12 ? self::OK : ($now + $old > 0 ? self::WARN : self::ERROR);

				$this->add($status, $this->language->lang('ACP_ZODIAC_LANG_' . strtoupper($lang)) . ', ' . $this->language->lang('ZODIAC_HORO_' . strtoupper($period)) . ' (' . $current . ')',
					$this->l('COVERAGE', $now, $old, $missing, $manual));
			}
		}

		$report = $this->horoscope->get_report();

		if ($report)
		{
			$this->add(!empty($report['failed']) && empty($report['fetched']) ? self::WARN : self::INFO, $this->l('LAST_REPORT'),
				date('Y-m-d H:i', (int) $report['time']) . ' - ' . $this->language->lang('ACP_ZODIAC_FETCH_DONE', (int) $report['fetched'], (int) $report['failed'], (int) $report['skipped'])
				. (!empty($report['errors']) ? "\n" . implode("\n", array_slice($report['errors'], 0, 5)) : ''));
		}
	}

	/* ------------------------------------------------------------------
	 * 10. Cron
	 * ------------------------------------------------------------------ */

	protected function check_cron(): void
	{
		$this->section('CRON');

		$task = $this->cron_manager->find_task(self::CRON_NAME);

		if (!$task)
		{
			$this->add(self::ERROR, $this->l('CRON_TASK'), $this->l('CRON_NOT_FOUND'));

			return;
		}

		$this->add(self::OK, $this->l('CRON_TASK'), self::CRON_NAME);

		$runnable = $task->is_runnable();
		$this->add($runnable ? self::OK : self::INFO, $this->l('CRON_RUNNABLE'), $runnable ? $this->language->lang('YES') : $this->l('CRON_NOT_RUNNABLE'));

		$last = (int) $this->config['zodiac_horo_last_run'];
		$interval = max(1, min(24, (int) $this->config['zodiac_horo_interval'])) * 3600;
		$pending = !empty($this->config['zodiac_horo_pending']);

		$this->add(self::INFO, $this->l('CRON_LAST'), $last ? date('Y-m-d H:i:s', $last) . ' (' . $this->l('AGO', $this->ago(time() - $last)) . ')' : $this->language->lang('ACP_ZODIAC_NEVER_RUN'));

		if ($runnable)
		{
			$next = $last + ($pending ? 300 : $interval);
			$this->add(self::INFO, $this->l('CRON_NEXT'), $task->should_run()
				? $this->l('CRON_DUE') . ($pending ? ' (' . $this->l('CRON_PENDING') . ')' : '')
				: date('Y-m-d H:i', $next) . ' (' . $this->l('CRON_IN', $this->ago($next - time())) . ')');

			if ($last && time() - $last > $interval * 4)
			{
				$this->add(self::WARN, $this->l('CRON_LATE'), $this->l('CRON_LATE_DETAIL', $this->ago(time() - $last)));
			}
		}

		$this->add(self::INFO, $this->l('CRON_SYSTEM'), !empty($this->config['use_system_cron']) ? $this->l('CRON_SYSTEM_ON') : $this->l('CRON_SYSTEM_OFF'));

		$lock = (string) $this->config['cron_lock'];

		if ($lock !== '')
		{
			$since = (int) strtok($lock, ' ');
			$stuck = $since && time() - $since > 3600;
			$this->add($stuck ? self::WARN : self::INFO, $this->l('CRON_LOCK'), $stuck ? $this->l('CRON_LOCK_STUCK', $this->ago(time() - $since)) : $this->l('CRON_LOCK_BUSY'));
		}
	}

	protected function ago(int $seconds): string
	{
		$seconds = abs($seconds);

		if ($seconds < 120)
		{
			return $this->l('SECONDS', $seconds);
		}

		if ($seconds < 7200)
		{
			return $this->l('MINUTES', (int) round($seconds / 60));
		}

		if ($seconds < 172800)
		{
			return $this->l('HOURS', (int) round($seconds / 3600));
		}

		return $this->l('DAYS', (int) round($seconds / 86400));
	}

	/* ------------------------------------------------------------------
	 * 11. Popover route
	 * ------------------------------------------------------------------ */

	protected function check_route(): void
	{
		$this->section('ROUTE');

		if (!$this->horoscope->is_enabled())
		{
			$this->add(self::INFO, $this->l('ROUTE'), $this->l('HORO_OFF'));

			return;
		}

		try
		{
			$url = $this->helper->route('salvocortesiano_zodiacsigns_horoscope', ['sign' => 'pisces'], false);
			$this->add(self::OK, $this->l('ROUTE_URL'), str_replace('../', '', $url));
		}
		catch (\Exception $e)
		{
			$this->add(self::ERROR, $this->l('ROUTE_URL'), $e->getMessage());

			return;
		}

		try
		{
			$response = $this->controller->horoscope('pisces');
			$data = json_decode((string) $response->getContent(), true);
			$items = is_array($data) ? count($data['items'] ?? []) : 0;

			if ($response->getStatusCode() !== 200 || !is_array($data))
			{
				$this->add(self::ERROR, $this->l('ROUTE_JSON'), 'HTTP ' . $response->getStatusCode());
			}
			else
			{
				$this->add($items ? self::OK : self::WARN, $this->l('ROUTE_JSON'), $this->l('ROUTE_ITEMS', $items, count($this->horoscope->enabled_periods())));
			}
		}
		catch (\Throwable $e)
		{
			$this->add(self::ERROR, $this->l('ROUTE_JSON'), $e->getMessage());
		}
	}
}
