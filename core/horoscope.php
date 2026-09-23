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
 * Horoscope sources ("providers"), fetching with fallback, storage and display.
 *
 * One text is stored per sign, period and language (at most 12 x 2 x 2 rows):
 * profiles read the text of the member's sign, no per-user copies.
 */
class horoscope
{
	public const PERIODS = ['weekly', 'monthly'];
	public const LANGS = ['it', 'en'];
	public const SOURCES = ['free', 'api', 'both'];
	public const SIGN_FORMATS = ['en_lower', 'en_ucfirst', 'it_lower', 'it_ucfirst', 'number'];

	/** A provider failing this many times in one run is skipped for the rest of the run */
	protected const MAX_FAILS_PER_RUN = 3;

	protected const IT_NAMES = [
		1 => 'ariete', 'toro', 'gemelli', 'cancro', 'leone', 'vergine',
		'bilancia', 'scorpione', 'sagittario', 'capricorno', 'acquario', 'pesci',
	];

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\config\db_text */
	protected $config_text;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $providers_table;

	/** @var string */
	protected $horoscopes_table;

	/** JSON path that produced the last extracted text (used by the check-up) */
	protected $last_path = '';

	public function __construct(\phpbb\config\config $config, \phpbb\config\db_text $config_text, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, $providers_table, $horoscopes_table)
	{
		$this->config           = $config;
		$this->config_text      = $config_text;
		$this->db               = $db;
		$this->user             = $user;
		$this->providers_table  = $providers_table;
		$this->horoscopes_table = $horoscopes_table;
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public function enabled_periods(): array
	{
		$periods = [];

		if (!empty($this->config['zodiac_horo_weekly']))
		{
			$periods[] = 'weekly';
		}

		if (!empty($this->config['zodiac_horo_monthly']))
		{
			$periods[] = 'monthly';
		}

		return $periods;
	}

	public function is_enabled(): bool
	{
		return !empty($this->config['zodiac_enable']) && count($this->enabled_periods()) > 0;
	}

	/**
	 * Identifies the current week (ISO, e.g. 2026-W39) or month (2026-09).
	 */
	public function period_key(string $period, ?int $time = null): string
	{
		$time = $time ?? time();

		return $period === 'weekly' ? gmdate('o-\WW', $time) : gmdate('Y-m', $time);
	}

	/**
	 * Language shown to the current visitor: fixed by the ACP or the visitor's own language.
	 */
	public function display_lang(): string
	{
		$pref = (string) $this->config['zodiac_horo_display'];

		if (in_array($pref, self::LANGS, true))
		{
			return $pref;
		}

		$lang = strtolower(substr((string) ($this->user->lang_name ?: $this->config['default_lang']), 0, 2));

		return $lang === 'it' ? 'it' : 'en';
	}

	/* ------------------------------------------------------------------
	 * Providers
	 * ------------------------------------------------------------------ */

	public function get_providers(bool $only_enabled = false): array
	{
		$sql = 'SELECT *
			FROM ' . $this->providers_table .
			($only_enabled ? ' WHERE provider_enabled = 1' : '') . '
			ORDER BY provider_lang ASC, provider_builtin ASC, provider_priority ASC, provider_id ASC';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows ?: [];
	}

	public function get_provider(int $id): ?array
	{
		$sql = 'SELECT *
			FROM ' . $this->providers_table . '
			WHERE provider_id = ' . (int) $id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * @param array $data provider_* columns, auth value already encrypted
	 */
	public function save_provider(array $data, int $id = 0): int
	{
		if ($id)
		{
			$sql = 'UPDATE ' . $this->providers_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $data) . '
				WHERE provider_id = ' . (int) $id;
			$this->db->sql_query($sql);

			return $id;
		}

		$this->db->sql_query('INSERT INTO ' . $this->providers_table . ' ' . $this->db->sql_build_array('INSERT', $data));

		return (int) $this->db->sql_nextid();
	}

	public function delete_provider(int $id): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->providers_table . '
			WHERE provider_id = ' . (int) $id . '
				AND provider_builtin = 0');
	}

	/**
	 * Providers to try, grouped by language, in order: custom APIs first (by priority), then the free ones.
	 * Every language gets its own chain: first provider = primary, the others = fallbacks.
	 */
	public function provider_chains(): array
	{
		$mode = (string) $this->config['zodiac_horo_source'];
		$chains = [];

		foreach ($this->get_providers(true) as $p)
		{
			$builtin = !empty($p['provider_builtin']);

			if (($mode === 'free' && !$builtin) || ($mode === 'api' && $builtin))
			{
				continue;
			}

			$chains[$p['provider_lang']][] = $p;
		}

		return $chains;
	}

	public function active_langs(): array
	{
		return array_keys($this->provider_chains());
	}

	/* ------------------------------------------------------------------
	 * Fetching
	 * ------------------------------------------------------------------ */

	/**
	 * Fetches what is missing or out of date.
	 *
	 * @param int  $max_requests stop after this many HTTP calls (0 = no limit), the cron continues next run
	 * @param bool $force        refetch the current period too (manual texts are always kept)
	 */
	public function update(int $max_requests = 0, bool $force = false): array
	{
		$report = [
			'time'     => time(),
			'fetched'  => 0,
			'failed'   => 0,
			'skipped'  => 0,
			'pending'  => false,
			'errors'   => [],
		];

		$periods = $this->enabled_periods();
		$chains = $this->provider_chains();

		if (!$periods || !$chains)
		{
			return $report;
		}

		$existing = $this->load_all();
		$requests = 0;
		$fails = [];
		$status = [];

		foreach ($chains as $lang => $providers)
		{
			foreach ($periods as $period)
			{
				$pkey = $this->period_key($period);

				foreach (zodiac::SIGNS as $sign_id => $sign)
				{
					$key = $sign[0];
					$row = $existing[$key][$period][$lang] ?? null;

					if ($row && $row['period_key'] === $pkey && trim($row['horo_text']) !== '' && ($row['horo_manual'] || !$force))
					{
						$report['skipped']++;
						continue;
					}

					$text = '';
					$used = 0;

					foreach ($providers as $p)
					{
						$pid = (int) $p['provider_id'];
						$fail_key = $pid . ':' . $period;

						// No address for this period: not a failure, just not offered by this source
						if (trim((string) ($period === 'weekly' ? $p['provider_url_weekly'] : $p['provider_url_monthly'])) === '')
						{
							continue;
						}

						// A source may be down for one period and fine for the other
						if (($fails[$fail_key] ?? 0) >= self::MAX_FAILS_PER_RUN)
						{
							continue;
						}

						if ($max_requests && $requests >= $max_requests)
						{
							$report['pending'] = true;
							break 4;
						}

						$requests++;
						$res = $this->fetch_from_provider($p, $sign_id, $period);

						if ($res['ok'])
						{
							$text = $res['text'];
							$used = $pid;
							$status[$pid] = 'OK';
							break;
						}

						$fails[$fail_key] = ($fails[$fail_key] ?? 0) + 1;
						$status[$pid] = $res['error'];

						if (count($report['errors']) < 15)
						{
							$report['errors'][] = $p['provider_name'] . ' (' . $key . ', ' . $period . '): ' . $res['error'];
						}
					}

					if ($text !== '')
					{
						$this->store($key, $period, $lang, $text, $used, $pkey, false);
						$report['fetched']++;
					}
					else
					{
						$report['failed']++;
					}
				}
			}
		}

		foreach ($status as $pid => $msg)
		{
			$this->db->sql_query('UPDATE ' . $this->providers_table . '
				SET ' . $this->db->sql_build_array('UPDATE', [
					'provider_status'  => utf8_substr((string) $msg, 0, 250),
					'provider_checked' => time(),
				]) . '
				WHERE provider_id = ' . (int) $pid);
		}

		return $report;
	}

	/**
	 * One call to one provider.
	 *
	 * @return array ['ok' => bool, 'text' => string, 'error' => string, 'url' => string]
	 */
	public function fetch_from_provider(array $p, int $sign_id, string $period): array
	{
		$url = trim((string) ($period === 'weekly' ? $p['provider_url_weekly'] : $p['provider_url_monthly']));

		if ($url === '')
		{
			return ['ok' => false, 'text' => '', 'error' => 'no URL for ' . $period, 'url' => '', 'code' => 0, 'ms' => 0, 'bytes' => 0, 'path' => ''];
		}

		$secret = $this->decrypt((string) $p['provider_auth_value']);
		$now = time();

		$url = strtr($url, [
			'{sign}'   => rawurlencode($this->format_sign($sign_id, (string) $p['provider_sign_format'])),
			'{period}' => $period,
			'{lang}'   => (string) $p['provider_lang'],
			'{date}'   => gmdate('Y-m-d', $now),
			'{week}'   => gmdate('W', $now),
			'{month}'  => gmdate('m', $now),
			'{year}'   => gmdate('Y', $now),
			'{key}'    => rawurlencode($secret),
		]);

		if (!preg_match('#^https?://#i', $url))
		{
			return ['ok' => false, 'text' => '', 'error' => 'invalid URL', 'url' => '', 'code' => 0, 'ms' => 0, 'bytes' => 0, 'path' => ''];
		}

		$board = function_exists('generate_board_url') ? generate_board_url() : '';
		$headers = [
			'Accept: application/json, text/plain;q=0.8, */*;q=0.5',
			'User-Agent: phpBB-ZodiacSigns/1.0' . ($board ? ' (+' . $board . ')' : ''),
		];

		if (trim((string) $p['provider_auth_header']) !== '' && $secret !== '')
		{
			$headers[] = trim($p['provider_auth_header']) . ': ' . $secret;
		}

		foreach (preg_split('/\R/', (string) $p['provider_headers']) as $line)
		{
			$line = trim($line);

			if ($line !== '' && strpos($line, ':') > 0)
			{
				$headers[] = $line;
			}
		}

		// Never show the key in logs or in the ACP
		$safe_url = $secret !== '' ? str_replace(rawurlencode($secret), '***', $url) : $url;
		$resp = $this->http_get($url, $headers);
		$info = [
			'url'   => $safe_url,
			'code'  => $resp['code'],
			'ms'    => $resp['ms'],
			'bytes' => strlen($resp['body']),
			'path'  => '',
		];

		if (!$resp['ok'])
		{
			return ['ok' => false, 'text' => '', 'error' => $resp['error']] + $info;
		}

		$this->last_path = '';
		$text = $this->extract($resp['body'], (string) $p['provider_json_path']);
		$info['path'] = $this->last_path;

		if ($text === '')
		{
			return ['ok' => false, 'text' => '', 'error' => 'no text found in the response'] + $info;
		}

		return ['ok' => true, 'text' => $text, 'error' => ''] + $info;
	}

	public function format_sign(int $sign_id, string $format): string
	{
		switch ($format)
		{
			case 'number':
				return (string) $sign_id;
			case 'it_lower':
				return self::IT_NAMES[$sign_id] ?? '';
			case 'it_ucfirst':
				return ucfirst(self::IT_NAMES[$sign_id] ?? '');
			case 'en_ucfirst':
				return ucfirst(zodiac::SIGNS[$sign_id][0] ?? '');
			default:
				return zodiac::SIGNS[$sign_id][0] ?? '';
		}
	}

	protected function http_get(string $url, array $headers): array
	{
		$max = 512 * 1024;
		$start = microtime(true);

		if (function_exists('curl_init'))
		{
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 3,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT        => 10,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_ENCODING       => '',
			]);

			if (defined('CURLOPT_PROTOCOLS'))
			{
				curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
				curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}

			$body = curl_exec($ch);
			$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			$err = curl_error($ch);
			curl_close($ch);

			if ($body === false)
			{
				return ['ok' => false, 'body' => '', 'error' => 'connection: ' . $err, 'code' => 0, 'ms' => (int) round((microtime(true) - $start) * 1000)];
			}
		}
		else
		{
			$ctx = stream_context_create(['http' => [
				'method'        => 'GET',
				'header'        => implode("\r\n", $headers),
				'timeout'       => 10,
				'ignore_errors' => true,
				'max_redirects' => 3,
			]]);
			$body = @file_get_contents($url, false, $ctx, 0, $max);
			$code = 0;

			foreach ($http_response_header ?? [] as $h)
			{
				if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m))
				{
					$code = (int) $m[1];
				}
			}

			if ($body === false)
			{
				return ['ok' => false, 'body' => '', 'error' => 'connection failed (cURL not available)', 'code' => 0, 'ms' => (int) round((microtime(true) - $start) * 1000)];
			}
		}

		$ms = (int) round((microtime(true) - $start) * 1000);

		if ($code < 200 || $code >= 300)
		{
			return ['ok' => false, 'body' => '', 'error' => 'HTTP ' . $code, 'code' => $code, 'ms' => $ms];
		}

		return ['ok' => true, 'body' => substr((string) $body, 0, $max), 'error' => '', 'code' => $code, 'ms' => $ms];
	}

	/**
	 * Takes the text out of the answer. json_path accepts alternatives separated by "|" (e.g. data.horoscope|data.text);
	 * the formats of the known free APIs are always tried as a last resort.
	 */
	protected function extract(string $body, string $json_path): string
	{
		$json = json_decode($body, true);

		if (is_array($json))
		{
			$paths = $json_path !== '' ? explode('|', $json_path) : [];
			$paths = array_merge($paths, ['data.horoscope', 'data.horoscope_data', 'horoscope', 'horoscope_data', 'data.text', 'text', 'description', 'data']);

			foreach ($paths as $path)
			{
				$value = $this->dig($json, trim($path));

				if (is_string($value) && trim($value) !== '')
				{
					$this->last_path = trim($path);
					return $this->clean($value);
				}
			}

			return '';
		}

		// Plain text answers only, never an HTML error page
		if ($json_path === '' && stripos($body, '<html') === false && stripos($body, '<!doctype') === false)
		{
			$this->last_path = '(text)';
			return $this->clean($body);
		}

		return '';
	}

	/**
	 * True if the provider's own JSON path produced the text (false = a built-in fallback path was used).
	 */
	public function path_is_configured(string $json_path, string $used): bool
	{
		return $used !== '' && in_array($used, array_map('trim', explode('|', $json_path)), true);
	}

	protected function dig(array $data, string $path)
	{
		if ($path === '')
		{
			return null;
		}

		foreach (explode('.', $path) as $part)
		{
			if (!is_array($data) || !array_key_exists($part, $data))
			{
				return null;
			}
			$data = $data[$part];
		}

		return $data;
	}

	protected function clean(string $text): string
	{
		$text = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace("/[ \t]+/u", ' ', $text);
		$text = preg_replace("/\n{3,}/", "\n\n", $text);

		return utf8_substr(trim($text), 0, 6000);
	}

	/* ------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	public function store(string $sign_key, string $period, string $lang, string $text, int $provider_id, string $period_key, bool $manual): void
	{
		$where = "sign_key = '" . $this->db->sql_escape($sign_key) . "'
			AND horo_period = '" . $this->db->sql_escape($period) . "'
			AND horo_lang = '" . $this->db->sql_escape($lang) . "'";

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->horoscopes_table . ' WHERE ' . $where);
		$this->db->sql_query('INSERT INTO ' . $this->horoscopes_table . ' ' . $this->db->sql_build_array('INSERT', [
			'sign_key'    => $sign_key,
			'horo_period' => $period,
			'horo_lang'   => $lang,
			'horo_text'   => $text,
			'period_key'  => $period_key,
			'provider_id' => $provider_id,
			'horo_time'   => time(),
			'horo_manual' => $manual ? 1 : 0,
		]));
		$this->db->sql_transaction('commit');
	}

	/**
	 * A manually edited text goes back to automatic: the next update refetches it.
	 */
	public function release_manual(string $sign_key, string $period, string $lang): void
	{
		$this->db->sql_query('UPDATE ' . $this->horoscopes_table . "
			SET horo_manual = 0, period_key = ''
			WHERE sign_key = '" . $this->db->sql_escape($sign_key) . "'
				AND horo_period = '" . $this->db->sql_escape($period) . "'
				AND horo_lang = '" . $this->db->sql_escape($lang) . "'");
	}

	/**
	 * @return array [sign_key][period][lang] => row (with provider_name)
	 */
	public function load_all(?string $sign_key = null): array
	{
		$sql = 'SELECT h.*, p.provider_name
			FROM ' . $this->horoscopes_table . ' h
			LEFT JOIN ' . $this->providers_table . ' p
				ON p.provider_id = h.provider_id' .
			($sign_key !== null ? " WHERE h.sign_key = '" . $this->db->sql_escape($sign_key) . "'" : '');
		$result = $this->db->sql_query($sql);
		$out = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$out[$row['sign_key']][$row['horo_period']][$row['horo_lang']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $out;
	}

	/**
	 * Texts to show for a sign, one per enabled period, preferred language first
	 * and the other language as fallback.
	 *
	 * @return array period => ['text', 'lang', 'provider', 'time', 'manual']
	 */
	public function get_for_sign(string $sign_key, string $lang): array
	{
		$periods = $this->enabled_periods();

		if (!$periods)
		{
			return [];
		}

		$rows = $this->load_all($sign_key)[$sign_key] ?? [];
		$order = array_unique(array_merge([$lang], self::LANGS));
		$out = [];

		foreach ($periods as $period)
		{
			foreach ($order as $l)
			{
				$row = $rows[$period][$l] ?? null;

				if ($row && trim($row['horo_text']) !== '')
				{
					$out[$period] = [
						'text'     => $row['horo_text'],
						'lang'     => $l,
						'provider' => $row['horo_manual'] ? '' : (string) $row['provider_name'],
						'time'     => (int) $row['horo_time'],
						'manual'   => (bool) $row['horo_manual'],
					];
					break;
				}
			}
		}

		return $out;
	}

	public function save_report(array $report): void
	{
		$this->config_text->set('zodiac_horo_report', json_encode($report));
	}

	public function get_report(): array
	{
		$raw = (string) $this->config_text->get('zodiac_horo_report');
		$data = $raw !== '' ? json_decode($raw, true) : null;

		return is_array($data) ? $data : [];
	}

	/* ------------------------------------------------------------------
	 * API key encryption (stored encrypted, decrypted only when calling the API)
	 * ------------------------------------------------------------------ */

	protected function secret_key(): string
	{
		$stored = (string) $this->config_text->get('zodiac_secret');

		if ($stored === '')
		{
			$stored = base64_encode(random_bytes(32));
			$this->config_text->set('zodiac_secret', $stored);
		}

		return hash('sha256', base64_decode($stored), true);
	}

	public function encrypt(string $plain): string
	{
		if ($plain === '')
		{
			return '';
		}

		$key = $this->secret_key();

		if (function_exists('sodium_crypto_secretbox'))
		{
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

			return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
		}

		$iv = random_bytes(12);
		$tag = '';
		$cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

		return 'o1:' . base64_encode($iv . $tag . $cipher);
	}

	public function decrypt(string $stored): string
	{
		if ($stored === '' || strlen($stored) < 4)
		{
			return '';
		}

		$prefix = substr($stored, 0, 3);
		$raw = base64_decode(substr($stored, 3), true);

		if ($raw === false)
		{
			return '';
		}

		$key = $this->secret_key();

		try
		{
			if ($prefix === 's1:' && function_exists('sodium_crypto_secretbox_open'))
			{
				// Too short to hold a nonce and a MAC: corrupted value
				if (strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)
				{
					return '';
				}

				$nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
				$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);

				return $plain === false ? '' : $plain;
			}

			if ($prefix === 'o1:' && strlen($raw) > 28)
			{
				$plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

				return $plain === false ? '' : $plain;
			}
		}
		catch (\Throwable $e)
		{
			return '';
		}

		return '';
	}
}
