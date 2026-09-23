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
 * Sign catalogue, birthday -> sign calculation, sign images and group checks.
 */
class zodiac
{
	/**
	 * id => [key, element, first month, first day]
	 * Tropical zodiac, the most common date ranges used in Italy and abroad.
	 */
	public const SIGNS = [
		1  => ['aries',       'fire',  3, 21],
		2  => ['taurus',      'earth', 4, 20],
		3  => ['gemini',      'air',   5, 21],
		4  => ['cancer',      'water', 6, 21],
		5  => ['leo',         'fire',  7, 23],
		6  => ['virgo',       'earth', 8, 23],
		7  => ['libra',       'air',   9, 23],
		8  => ['scorpio',     'water', 10, 23],
		9  => ['sagittarius', 'fire',  11, 22],
		10 => ['capricorn',   'earth', 12, 22],
		11 => ['aquarius',    'air',   1, 20],
		12 => ['pisces',      'water', 2, 19],
	];

	/** Upload formats accepted from the ACP */
	public const IMG_EXT = ['gif', 'jpg', 'jpeg', 'png'];

	/** Upload folder, relative to the board root */
	public const UPLOAD_DIR = 'images/zodiacsigns/';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $root_path;

	/** @var array user_id => bool */
	protected $allowed_cache = [];

	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, $root_path)
	{
		$this->config    = $config;
		$this->db        = $db;
		$this->language  = $language;
		$this->root_path = $root_path;
	}

	public function is_enabled(): bool
	{
		return !empty($this->config['zodiac_enable']);
	}

	/**
	 * 0 = not asked, 1 = optional, 2 = required
	 */
	public function registration_mode(): int
	{
		return $this->is_enabled() ? min(2, max(0, (int) $this->config['zodiac_register_mode'])) : 0;
	}

	public function valid_id($id): bool
	{
		return isset(self::SIGNS[(int) $id]);
	}

	public function key_from_id(int $id): string
	{
		return self::SIGNS[$id][0] ?? '';
	}

	public function id_from_key(string $key): int
	{
		foreach (self::SIGNS as $id => $sign)
		{
			if ($sign[0] === $key)
			{
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Sign from day and month. Returns 0 when the date is not valid.
	 * Example: 8 March -> 12 (Pisces).
	 */
	public function sign_from_date(int $day, int $month): int
	{
		if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || !checkdate($month, $day, 2000))
		{
			return 0;
		}

		$md = $month * 100 + $day;

		// Capricorn is the only sign that spans the end of the year
		if ($md >= 1222 || $md < 120)
		{
			return 10;
		}

		$best = 0;
		$best_start = 0;

		foreach (self::SIGNS as $id => $sign)
		{
			if ($id === 10)
			{
				continue;
			}

			$start = $sign[2] * 100 + $sign[3];

			if ($start <= $md && $start > $best_start)
			{
				$best = $id;
				$best_start = $start;
			}
		}

		return $best;
	}

	/**
	 * Sign from the phpBB birthday string (" 8- 3-1970"). Returns 0 if not set.
	 */
	public function sign_from_birthday(string $birthday): int
	{
		$parts = array_map('trim', explode('-', $birthday));

		if (count($parts) < 2)
		{
			return 0;
		}

		return $this->sign_from_date((int) $parts[0], (int) $parts[1]);
	}

	/**
	 * Sign to show for a member: the one chosen, otherwise (if enabled) the one
	 * calculated from the birthday of the profile.
	 *
	 * @return array [sign id or 0, true when calculated from the birthday]
	 */
	public function effective_sign(int $stored, string $birthday): array
	{
		if ($this->valid_id($stored))
		{
			return [$stored, false];
		}

		if (!empty($this->config['zodiac_auto_birthday']) && !empty($this->config['allow_birthdays']) && trim($birthday) !== '')
		{
			$auto = $this->sign_from_birthday($birthday);

			if ($auto)
			{
				return [$auto, true];
			}
		}

		return [0, false];
	}

	/**
	 * Members with a birthday but no sign.
	 */
	public function count_without_sign(): array
	{
		$sql = 'SELECT COUNT(user_id) AS total
			FROM ' . USERS_TABLE . '
			WHERE user_zodiac_sign = 0
				AND user_type <> ' . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$sql = 'SELECT COUNT(user_id) AS total
			FROM ' . USERS_TABLE . "
			WHERE user_zodiac_sign = 0
				AND user_type <> " . USER_IGNORE . "
				AND user_birthday <> ''
				AND user_birthday <> ' 0- 0-   0'";
		$result = $this->db->sql_query($sql);
		$with_bday = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return ['none' => $total, 'with_birthday' => $with_bday];
	}

	/**
	 * Saves the sign calculated from the birthday for every member who has not chosen one.
	 *
	 * @return int members updated
	 */
	public function assign_from_birthdays(): int
	{
		$sql = 'SELECT user_id, user_birthday
			FROM ' . USERS_TABLE . "
			WHERE user_zodiac_sign = 0
				AND user_type <> " . USER_IGNORE . "
				AND user_birthday <> ''";
		$result = $this->db->sql_query($sql);
		$by_sign = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$sign = $this->sign_from_birthday((string) $row['user_birthday']);

			if ($sign)
			{
				$by_sign[$sign][] = (int) $row['user_id'];
			}
		}
		$this->db->sql_freeresult($result);

		$updated = 0;

		// One UPDATE per sign, in chunks, instead of one per member
		foreach ($by_sign as $sign => $ids)
		{
			foreach (array_chunk($ids, 500) as $chunk)
			{
				$this->db->sql_query('UPDATE ' . USERS_TABLE . '
					SET user_zodiac_sign = ' . (int) $sign . '
					WHERE ' . $this->db->sql_in_set('user_id', $chunk) . '
						AND user_zodiac_sign = 0');
				$updated += (int) $this->db->sql_affectedrows();
			}
		}

		return $updated;
	}

	public function name(int $id): string
	{
		$key = $this->key_from_id($id);

		return $key !== '' ? $this->language->lang('ZODIAC_SIGN_' . strtoupper($key)) : '';
	}

	/**
	 * Absolute URL of the sign image: the ACP upload if present, otherwise the bundled animated SVG.
	 */
	public function image_url(int $id): string
	{
		$key = $this->key_from_id($id);

		if ($key === '')
		{
			return '';
		}

		if ($this->has_custom_image($key))
		{
			return generate_board_url() . '/' . self::UPLOAD_DIR . rawurlencode($this->config['zodiac_img_' . $key]);
		}

		return $this->default_image_url($key);
	}

	public function default_image_url(string $key): string
	{
		return generate_board_url() . '/ext/salvocortesiano/zodiacsigns/styles/all/theme/images/signs/' . $key . '.svg';
	}

	public function has_custom_image(string $key): bool
	{
		$file = (string) ($this->config['zodiac_img_' . $key] ?? '');

		return $file !== '' && is_file($this->root_path . self::UPLOAD_DIR . $file);
	}

	/**
	 * Template variables for a small badge (viewtopic, profile).
	 */
	public function badge_vars(int $id): array
	{
		return [
			'S_ZODIAC_SIGN'  => true,
			'ZODIAC_ID'      => $id,
			'ZODIAC_KEY'     => $this->key_from_id($id),
			'ZODIAC_NAME'    => $this->name($id),
			'ZODIAC_ELEMENT' => self::SIGNS[$id][1],
			'ZODIAC_IMG'     => $this->image_url($id),
		];
	}

	/**
	 * All twelve signs, ready for a template loop.
	 */
	public function get_sign_rows(): array
	{
		$rows = [];

		foreach (self::SIGNS as $id => $sign)
		{
			$ukey = strtoupper($sign[0]);

			$rows[] = [
				'ID'           => $id,
				'KEY'          => $sign[0],
				'NAME'         => $this->language->lang('ZODIAC_SIGN_' . $ukey),
				'RANGE'        => $this->language->lang('ZODIAC_RANGE_' . $ukey),
				'ELEMENT'      => $sign[1],
				'ELEMENT_NAME' => $this->language->lang('ZODIAC_ELEMENT_' . strtoupper($sign[1])),
				'IMG'          => $this->image_url($id),
			];
		}

		return $rows;
	}

	/**
	 * Group ids allowed to use the sign. Empty = everybody.
	 */
	public function allowed_groups(): array
	{
		$raw = trim((string) $this->config['zodiac_groups']);

		return $raw === '' ? [] : array_values(array_filter(array_map('intval', explode(',', $raw))));
	}

	public function user_allowed(int $user_id): bool
	{
		$map = $this->users_allowed([$user_id]);

		return !empty($map[$user_id]);
	}

	/**
	 * One query for many users (viewtopic).
	 *
	 * @return array user_id => bool
	 */
	public function users_allowed(array $user_ids): array
	{
		$user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
		$out = [];
		$groups = $this->allowed_groups();

		if (!$groups)
		{
			foreach ($user_ids as $uid)
			{
				$out[$uid] = true;
			}

			return $out;
		}

		$todo = [];

		foreach ($user_ids as $uid)
		{
			if (isset($this->allowed_cache[$uid]))
			{
				$out[$uid] = $this->allowed_cache[$uid];
			}
			else
			{
				$out[$uid] = false;
				$todo[] = $uid;
			}
		}

		if ($todo)
		{
			$sql = 'SELECT DISTINCT user_id
				FROM ' . USER_GROUP_TABLE . '
				WHERE ' . $this->db->sql_in_set('user_id', $todo) . '
					AND ' . $this->db->sql_in_set('group_id', $groups) . '
					AND user_pending = 0';
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$out[(int) $row['user_id']] = true;
			}
			$this->db->sql_freeresult($result);

			foreach ($todo as $uid)
			{
				$this->allowed_cache[$uid] = $out[$uid];
			}
		}

		return $out;
	}

	/**
	 * Validates and stores an image uploaded from the ACP.
	 *
	 * @return string '' on success, otherwise a language key
	 */
	public function store_uploaded_image(string $key, array $file, int $max_bytes): string
	{
		if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK)
		{
			return in_array((int) $file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'ZODIAC_UPLOAD_ERR_SIZE' : 'ZODIAC_UPLOAD_ERR_PHP';
		}

		$ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

		if (!in_array($ext, self::IMG_EXT, true))
		{
			return 'ZODIAC_UPLOAD_ERR_EXT';
		}

		if ((int) $file['size'] <= 0 || (int) $file['size'] > $max_bytes)
		{
			return 'ZODIAC_UPLOAD_ERR_SIZE';
		}

		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
		{
			return 'ZODIAC_UPLOAD_ERR_PHP';
		}

		// Trust the real content, not the file name
		$info = @getimagesize($file['tmp_name']);
		$types = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];

		if (!$info || !isset($types[$info[2]]))
		{
			return 'ZODIAC_UPLOAD_ERR_TYPE';
		}

		$dir = $this->root_path . self::UPLOAD_DIR;

		if (!is_dir($dir) && !@mkdir($dir, 0755, true))
		{
			return 'ZODIAC_UPLOAD_ERR_DIR';
		}

		if (!is_file($dir . 'index.htm'))
		{
			@file_put_contents($dir . 'index.htm', '');
		}

		$name = $key . '_' . substr(md5(unique_id()), 0, 10) . '.' . $types[$info[2]];

		if (!@move_uploaded_file($file['tmp_name'], $dir . $name))
		{
			return 'ZODIAC_UPLOAD_ERR_DIR';
		}

		@chmod($dir . $name, 0644);

		$this->delete_custom_image($key);
		$this->config->set('zodiac_img_' . $key, $name);

		return '';
	}

	public function delete_custom_image(string $key): void
	{
		$file = (string) ($this->config['zodiac_img_' . $key] ?? '');

		// basename() keeps the delete inside our folder whatever is in the config
		if ($file !== '' && is_file($this->root_path . self::UPLOAD_DIR . basename($file)))
		{
			@unlink($this->root_path . self::UPLOAD_DIR . basename($file));
		}

		$this->config->set('zodiac_img_' . $key, '');
	}

	public function upload_dir_writable(): bool
	{
		$dir = $this->root_path . self::UPLOAD_DIR;

		return is_dir($dir) ? is_writable($dir) : is_writable($this->root_path . 'images/');
	}
}
