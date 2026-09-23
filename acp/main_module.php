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

use salvocortesiano\zodiacsigns\core\zodiac;
use salvocortesiano\zodiacsigns\core\horoscope;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var zodiac */
	protected $zodiac;

	/** @var horoscope */
	protected $horoscope;

	/** @var array */
	protected $errors = [];

	protected const FORM_KEY = 'salvocortesiano_zodiacsigns';

	public function main($id, $mode)
	{
		global $phpbb_container;

		$this->config    = $phpbb_container->get('config');
		$this->db        = $phpbb_container->get('dbal.conn');
		$this->request   = $phpbb_container->get('request');
		$this->template  = $phpbb_container->get('template');
		$this->user      = $phpbb_container->get('user');
		$this->language  = $phpbb_container->get('language');
		$this->log       = $phpbb_container->get('log');
		$this->zodiac    = $phpbb_container->get('salvocortesiano.zodiacsigns.zodiac');
		$this->horoscope = $phpbb_container->get('salvocortesiano.zodiacsigns.horoscope');

		$this->language->add_lang('common', 'salvocortesiano/zodiacsigns');
		add_form_key(self::FORM_KEY);

		switch ($mode)
		{
			case 'signs':
				$this->mode_signs();
			break;

			case 'horoscope':
				$this->mode_horoscope();
			break;

			case 'providers':
				$this->mode_providers();
			break;

			case 'diagnostics':
				$this->mode_diagnostics();
			break;

			default:
				$this->mode_settings();
			break;
		}

		$this->template->assign_vars([
			'U_ACTION'  => $this->u_action,
			'S_ERROR'   => (bool) $this->errors,
			'ERROR_MSG' => implode('<br>', $this->errors),
		]);

		$this->assign_footer();
	}

	/**
	 * Badges under the title of every tab (version, phpBB, PHP, licence) and the
	 * credits at the bottom. Version and licence come from composer.json, so they
	 * always match the files actually installed; phpBB and PHP turn red outside
	 * the supported range, the version turns orange if the migrations are behind.
	 */
	protected function assign_footer(): void
	{
		global $phpbb_container;

		$meta = [];

		try
		{
			$meta = $phpbb_container->get('ext.manager')
				->create_extension_metadata_manager('salvocortesiano/zodiacsigns')
				->get_metadata('all');
		}
		catch (\Throwable $e)
		{
			// A broken composer.json must not take the ACP page down with it.
		}

		$meta = is_array($meta) ? $meta : [];
		$file_version = (string) ($meta['version'] ?? '');
		$db_version = (string) ($this->config['zodiac_version'] ?? '');
		$phpbb = (string) $this->config['version'];
		$author = $meta['authors'][0] ?? [];
		$license = $meta['license'] ?? '?';

		$this->template->assign_vars([
			'ZS_EXT_VERSION'   => $file_version !== '' ? $file_version : ($db_version !== '' ? $db_version : '?'),
			'ZS_EXT_MISMATCH'  => $file_version !== '' && $db_version !== '' && $file_version !== $db_version,
			'ZS_DB_VERSION'    => $db_version,
			'ZS_PHPBB_VERSION' => $phpbb,
			'ZS_PHP_VERSION'   => PHP_VERSION,
			'ZS_LICENSE'       => is_array($license) ? implode(', ', $license) : (string) $license,
			'S_ZS_PHPBB_OK'    => phpbb_version_compare($phpbb, '3.3.0', '>=') && phpbb_version_compare($phpbb, '4.0.0-dev', '<'),
			'S_ZS_PHP_OK'      => version_compare(PHP_VERSION, '8.0.0', '>='),
			'ZS_AUTHOR'        => (string) ($author['name'] ?? 'Salvo Cortesiano'),
			'ZS_AUTHOR_URL'    => (string) ($author['homepage'] ?? 'https://netshadows.de'),
			'ZS_SUPPORT'       => (string) ($author['email'] ?? 'info@netshadows.de'),
		]);
	}

	protected function check_form(): void
	{
		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}
	}

	protected function add_log(string $key, array $data = []): void
	{
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, $key, false, $data);
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	protected function mode_settings(): void
	{
		$this->tpl_name = 'acp_zodiac_settings';
		$this->page_title = 'ACP_ZODIAC_SETTINGS';

		if ($this->request->is_set_post('submit'))
		{
			$this->check_form();

			foreach (['zodiac_enable', 'zodiac_ucp_required', 'zodiac_remind', 'zodiac_show_viewtopic', 'zodiac_show_profile', 'zodiac_auto_birthday'] as $name)
			{
				$this->config->set($name, $this->request->variable($name, 0) ? 1 : 0);
			}

			$this->config->set('zodiac_register_mode', min(2, max(0, $this->request->variable('zodiac_register_mode', 0))));
			$this->config->set('zodiac_badge_size', min(64, max(16, $this->request->variable('zodiac_badge_size', 32))));

			$groups = array_unique(array_filter(array_map('intval', $this->request->variable('zodiac_groups', [0]))));
			$this->config->set('zodiac_groups', implode(',', $groups));

			$this->add_log('LOG_ZODIAC_SETTINGS');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		if ($this->request->is_set_post('assign_birthdays'))
		{
			$this->check_form();
			@set_time_limit(120);
			$updated = $this->zodiac->assign_from_birthdays();
			$this->add_log('LOG_ZODIAC_ASSIGNED', [$updated]);
			trigger_error($this->language->lang('ACP_ZODIAC_ASSIGN_DONE', $updated) . adm_back_link($this->u_action));
		}

		$counts = $this->zodiac->count_without_sign();

		$selected = $this->zodiac->allowed_groups();
		$sql = 'SELECT group_id, group_name, group_type
			FROM ' . GROUPS_TABLE . '
			ORDER BY group_type DESC, group_name ASC';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$special = (int) $row['group_type'] === GROUP_SPECIAL;

			$this->template->assign_block_vars('groups', [
				'ID'         => (int) $row['group_id'],
				'NAME'       => $special ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'],
				'S_SPECIAL'  => $special,
				'S_SELECTED' => in_array((int) $row['group_id'], $selected, true),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'ZODIAC_ENABLE'         => (bool) $this->config['zodiac_enable'],
			'ZODIAC_REGISTER_MODE'  => (int) $this->config['zodiac_register_mode'],
			'ZODIAC_UCP_REQUIRED'   => (bool) $this->config['zodiac_ucp_required'],
			'ZODIAC_REMIND'         => (bool) $this->config['zodiac_remind'],
			'ZODIAC_SHOW_VIEWTOPIC' => (bool) $this->config['zodiac_show_viewtopic'],
			'ZODIAC_SHOW_PROFILE'   => (bool) $this->config['zodiac_show_profile'],
			'ZODIAC_BADGE_SIZE'     => (int) $this->config['zodiac_badge_size'],
			'ZODIAC_AUTO_BIRTHDAY'  => (bool) $this->config['zodiac_auto_birthday'],
			'ZODIAC_WITHOUT_SIGN'   => $counts['none'],
			'ZODIAC_WITH_BIRTHDAY'  => $counts['with_birthday'],
			'S_BIRTHDAYS_ALLOWED'   => (bool) $this->config['allow_birthdays'],
		]);
	}

	/* ------------------------------------------------------------------
	 * Sign images
	 * ------------------------------------------------------------------ */

	protected function mode_signs(): void
	{
		$this->tpl_name = 'acp_zodiac_signs';
		$this->page_title = 'ACP_ZODIAC_SIGNS';

		if ($this->request->is_set_post('submit'))
		{
			$this->check_form();

			$max_kb = min(4096, max(16, $this->request->variable('zodiac_img_maxsize', 512)));
			$this->config->set('zodiac_img_maxsize', $max_kb);
			$changed = [];

			foreach (zodiac::SIGNS as $sign)
			{
				$key = $sign[0];
				$name = $this->language->lang('ZODIAC_SIGN_' . strtoupper($key));

				if ($this->request->variable('reset_' . $key, 0))
				{
					$this->zodiac->delete_custom_image($key);
					$changed[] = $name;
					continue;
				}

				$file = $this->request->file('img_' . $key);

				if (empty($file['name']) || (isset($file['error']) && (int) $file['error'] === UPLOAD_ERR_NO_FILE))
				{
					continue;
				}

				$error = $this->zodiac->store_uploaded_image($key, $file, $max_kb * 1024);

				if ($error !== '')
				{
					$this->errors[] = $name . ': ' . $this->language->lang($error, $max_kb);
				}
				else
				{
					$changed[] = $name;
				}
			}

			if ($changed)
			{
				$this->add_log('LOG_ZODIAC_IMAGES', [implode(', ', $changed)]);
			}

			if (!$this->errors)
			{
				trigger_error($this->language->lang('ACP_ZODIAC_SIGNS_SAVED') . adm_back_link($this->u_action));
			}
		}

		foreach ($this->zodiac->get_sign_rows() as $row)
		{
			$row['S_CUSTOM'] = $this->zodiac->has_custom_image($row['KEY']);
			$row['DEFAULT_IMG'] = $this->zodiac->default_image_url($row['KEY']);
			$this->template->assign_block_vars('signs', $row);
		}

		$this->template->assign_vars([
			'ZODIAC_IMG_MAXSIZE'  => (int) $this->config['zodiac_img_maxsize'],
			'ZODIAC_UPLOAD_DIR'   => zodiac::UPLOAD_DIR,
			'S_ZODIAC_DIR_OK'     => $this->zodiac->upload_dir_writable(),
			'S_ZODIAC_PHP_LIMIT'  => ini_get('upload_max_filesize'),
		]);
	}

	/* ------------------------------------------------------------------
	 * Horoscope: settings, status, manual texts
	 * ------------------------------------------------------------------ */

	protected function mode_horoscope(): void
	{
		$this->tpl_name = 'acp_zodiac_horoscope';
		$this->page_title = 'ACP_ZODIAC_HOROSCOPE';

		if ($this->request->variable('action', '') === 'edit')
		{
			$this->horoscope_edit();
			return;
		}

		if ($this->request->is_set_post('submit'))
		{
			$this->check_form();

			$source = $this->request->variable('zodiac_horo_source', 'free');
			$display = $this->request->variable('zodiac_horo_display', 'user');

			$this->config->set('zodiac_horo_weekly', $this->request->variable('zodiac_horo_weekly', 0) ? 1 : 0);
			$this->config->set('zodiac_horo_monthly', $this->request->variable('zodiac_horo_monthly', 0) ? 1 : 0);
			$this->config->set('zodiac_horo_source', in_array($source, horoscope::SOURCES, true) ? $source : 'free');
			$this->config->set('zodiac_horo_display', in_array($display, array_merge(['user'], horoscope::LANGS), true) ? $display : 'user');
			$this->config->set('zodiac_horo_interval', min(24, max(1, $this->request->variable('zodiac_horo_interval', 6))));
			$this->config->set('zodiac_horo_credit', $this->request->variable('zodiac_horo_credit', 0) ? 1 : 0);

			// New settings: let the cron check again soon
			$this->config->set('zodiac_horo_pending', 1, false);

			$this->add_log('LOG_ZODIAC_HORO_SETTINGS');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		if ($this->request->is_set_post('fetch_now') || $this->request->is_set_post('fetch_force'))
		{
			$this->check_form();

			if (!$this->horoscope->enabled_periods() || !$this->horoscope->provider_chains())
			{
				trigger_error($this->language->lang('ACP_ZODIAC_FETCH_NOTHING') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			@set_time_limit(300);
			$report = $this->horoscope->update(0, $this->request->is_set_post('fetch_force'));
			$this->horoscope->save_report($report);
			$this->config->set('zodiac_horo_last_run', time(), false);
			$this->config->set('zodiac_horo_pending', 0, false);
			$this->add_log('LOG_ZODIAC_HORO_FETCH', [$report['fetched'], $report['failed']]);

			$msg = $this->language->lang('ACP_ZODIAC_FETCH_DONE', $report['fetched'], $report['failed'], $report['skipped']);

			if ($report['errors'])
			{
				$msg .= '<br><br>' . implode('<br>', array_map(function ($e) {
					return htmlspecialchars($e, ENT_COMPAT, 'UTF-8');
				}, $report['errors']));
			}

			trigger_error($msg . adm_back_link($this->u_action), ($report['failed'] && !$report['fetched']) ? E_USER_WARNING : E_USER_NOTICE);
		}

		// Status table
		$langs = $this->horoscope->active_langs() ?: ['en'];
		$periods = $this->horoscope->enabled_periods();
		$all = $this->horoscope->load_all();

		foreach ($langs as $lang)
		{
			$this->template->assign_block_vars('langs', ['NAME' => $this->language->lang('ACP_ZODIAC_LANG_' . strtoupper($lang))]);
		}

		foreach ($periods as $period)
		{
			$current = $this->horoscope->period_key($period);

			foreach ($this->zodiac->get_sign_rows() as $sign)
			{
				$this->template->assign_block_vars('horo', [
					'SIGN'    => $sign['NAME'],
					'IMG'     => $sign['IMG'],
					'PERIOD'  => $this->language->lang('ZODIAC_HORO_' . strtoupper($period)),
				]);

				foreach ($langs as $lang)
				{
					$row = $all[$sign['KEY']][$period][$lang] ?? null;
					$text = $row ? (string) $row['horo_text'] : '';

					$this->template->assign_block_vars('horo.cells', [
						'EXCERPT'   => $text !== '' ? htmlspecialchars(utf8_substr($text, 0, 110) . (utf8_strlen($text) > 110 ? '…' : ''), ENT_COMPAT, 'UTF-8') : '',
						'DATE'      => $row ? $this->user->format_date((int) $row['horo_time']) : '',
						'PROVIDER'  => $row ? ($row['horo_manual'] ? $this->language->lang('ACP_ZODIAC_MANUAL') : (string) $row['provider_name']) : '',
						'S_CURRENT' => $row && $row['period_key'] === $current,
						'S_MANUAL'  => $row && $row['horo_manual'],
						'U_EDIT'    => $this->u_action . '&amp;action=edit&amp;sign=' . $sign['KEY'] . '&amp;period=' . $period . '&amp;hlang=' . $lang,
					]);
				}
			}
		}

		$report = $this->horoscope->get_report();

		$this->template->assign_vars([
			'ZODIAC_HORO_WEEKLY'   => (bool) $this->config['zodiac_horo_weekly'],
			'ZODIAC_HORO_MONTHLY'  => (bool) $this->config['zodiac_horo_monthly'],
			'ZODIAC_HORO_SOURCE'   => (string) $this->config['zodiac_horo_source'],
			'ZODIAC_HORO_DISPLAY'  => (string) $this->config['zodiac_horo_display'],
			'ZODIAC_HORO_INTERVAL' => (int) $this->config['zodiac_horo_interval'],
			'ZODIAC_HORO_CREDIT'   => (bool) $this->config['zodiac_horo_credit'],
			'S_HORO_PERIODS'       => (bool) $periods,
			'S_HORO_NO_SOURCE'     => !$this->horoscope->provider_chains(),
			'HORO_LAST_RUN'        => $this->config['zodiac_horo_last_run'] ? $this->user->format_date((int) $this->config['zodiac_horo_last_run']) : '',
			'S_HORO_REPORT'        => (bool) $report,
			'HORO_REPORT'          => $report ? $this->language->lang('ACP_ZODIAC_FETCH_DONE', (int) $report['fetched'], (int) $report['failed'], (int) $report['skipped']) : '',
			'HORO_REPORT_ERRORS'   => $report && !empty($report['errors']) ? implode('<br>', array_map(function ($e) {
				return htmlspecialchars((string) $e, ENT_COMPAT, 'UTF-8');
			}, $report['errors'])) : '',
			'U_PROVIDERS'          => str_replace('mode=horoscope', 'mode=providers', $this->u_action),
		]);
	}

	protected function horoscope_edit(): void
	{
		$this->tpl_name = 'acp_zodiac_horoscope_edit';

		$sign_key = $this->request->variable('sign', '');
		$period = $this->request->variable('period', '');
		$lang = $this->request->variable('hlang', '');
		$sign_id = $this->zodiac->id_from_key($sign_key);

		if (!$sign_id || !in_array($period, horoscope::PERIODS, true) || !in_array($lang, horoscope::LANGS, true))
		{
			trigger_error($this->language->lang('NO_MODE') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if ($this->request->is_set_post('save'))
		{
			$this->check_form();

			$text = trim(htmlspecialchars_decode($this->request->variable('horo_text', '', true), ENT_COMPAT));

			if ($text === '')
			{
				$this->errors[] = $this->language->lang('ACP_ZODIAC_TEXT_EMPTY');
			}
			else
			{
				$this->horoscope->store($sign_key, $period, $lang, utf8_substr($text, 0, 6000), 0, $this->horoscope->period_key($period), true);
				$this->add_log('LOG_ZODIAC_HORO_EDIT', [$this->zodiac->name($sign_id), $period, $lang]);
				trigger_error($this->language->lang('ACP_ZODIAC_TEXT_SAVED') . adm_back_link($this->u_action));
			}
		}

		if ($this->request->is_set_post('release'))
		{
			$this->check_form();
			$this->horoscope->release_manual($sign_key, $period, $lang);
			$this->config->set('zodiac_horo_pending', 1, false);
			trigger_error($this->language->lang('ACP_ZODIAC_TEXT_RELEASED') . adm_back_link($this->u_action));
		}

		$row = $this->horoscope->load_all($sign_key)[$sign_key][$period][$lang] ?? null;

		$this->template->assign_vars([
			'EDIT_TITLE'  => $this->language->lang('ACP_ZODIAC_EDIT_TITLE', $this->zodiac->name($sign_id), $this->language->lang('ZODIAC_HORO_' . strtoupper($period)), $this->language->lang('ACP_ZODIAC_LANG_' . strtoupper($lang))),
			'EDIT_IMG'    => $this->zodiac->image_url($sign_id),
			'EDIT_TEXT'   => $row ? htmlspecialchars((string) $row['horo_text'], ENT_COMPAT, 'UTF-8') : '',
			'S_MANUAL'    => $row && $row['horo_manual'],
			'U_BACK'      => $this->u_action,
			'U_EDIT_POST' => $this->u_action . '&amp;action=edit&amp;sign=' . $sign_key . '&amp;period=' . $period . '&amp;hlang=' . $lang,
		]);
	}

	/* ------------------------------------------------------------------
	 * Providers (horoscope sources)
	 * ------------------------------------------------------------------ */

	protected function mode_providers(): void
	{
		$this->tpl_name = 'acp_zodiac_providers';
		$this->page_title = 'ACP_ZODIAC_PROVIDERS';

		$action = $this->request->variable('action', '');
		$pid = $this->request->variable('id', 0);

		switch ($action)
		{
			case 'add':
			case 'edit':
				$this->provider_form($action === 'edit' ? $pid : 0);
				return;

			case 'delete':
				$provider = $this->require_provider($pid);

				if ($provider['provider_builtin'])
				{
					trigger_error($this->language->lang('ACP_ZODIAC_PROVIDER_BUILTIN') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				if (confirm_box(true))
				{
					$this->horoscope->delete_provider($pid);
					$this->add_log('LOG_ZODIAC_PROVIDER_DELETE', [$provider['provider_name']]);
					trigger_error($this->language->lang('ACP_ZODIAC_PROVIDER_DELETED') . adm_back_link($this->u_action));
				}

				confirm_box(false, $this->language->lang('ACP_ZODIAC_PROVIDER_DELETE_CONFIRM', $provider['provider_name']), build_hidden_fields([
					'action' => 'delete',
					'id'     => $pid,
				]));
				return;

			case 'toggle':
				if (!check_link_hash($this->request->variable('hash', ''), 'zodiac_provider'))
				{
					trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				$provider = $this->require_provider($pid);
				$this->horoscope->save_provider(['provider_enabled' => $provider['provider_enabled'] ? 0 : 1], $pid);
				redirect($this->u_action);
			break;

			case 'test':
				if (!check_link_hash($this->request->variable('hash', ''), 'zodiac_provider'))
				{
					trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				$provider = $this->require_provider($pid);
				$period = trim($provider['provider_url_weekly']) !== '' ? 'weekly' : 'monthly';
				$res = $this->horoscope->fetch_from_provider($provider, 12, $period);

				$this->horoscope->save_provider([
					'provider_status'  => utf8_substr($res['ok'] ? 'OK' : $res['error'], 0, 250),
					'provider_checked' => time(),
				], $pid);

				$msg = '<strong>' . htmlspecialchars($provider['provider_name'], ENT_COMPAT, 'UTF-8') . '</strong> (' . $this->language->lang('ZODIAC_SIGN_PISCES') . ', ' . $this->language->lang('ZODIAC_HORO_' . strtoupper($period)) . ')<br>';
				$msg .= '<code>' . htmlspecialchars($res['url'], ENT_COMPAT, 'UTF-8') . '</code><br><br>';
				$msg .= $res['ok']
					? $this->language->lang('ACP_ZODIAC_TEST_OK') . '<br><em>' . htmlspecialchars(utf8_substr($res['text'], 0, 400), ENT_COMPAT, 'UTF-8') . '…</em>'
					: $this->language->lang('ACP_ZODIAC_TEST_FAIL', htmlspecialchars($res['error'], ENT_COMPAT, 'UTF-8'));

				trigger_error($msg . adm_back_link($this->u_action), $res['ok'] ? E_USER_NOTICE : E_USER_WARNING);
			break;
		}

		$hash = generate_link_hash('zodiac_provider');
		$mode = (string) $this->config['zodiac_horo_source'];

		foreach ($this->horoscope->get_providers() as $p)
		{
			$id = (int) $p['provider_id'];
			$in_use = $p['provider_enabled'] && !(($mode === 'free' && !$p['provider_builtin']) || ($mode === 'api' && $p['provider_builtin']));

			$this->template->assign_block_vars('providers', [
				'NAME'       => $p['provider_name'],
				'LANG'       => $this->language->lang('ACP_ZODIAC_LANG_' . strtoupper($p['provider_lang'])),
				'PRIORITY'   => (int) $p['provider_priority'],
				'STATUS'     => $p['provider_status'],
				'S_STATUS_OK'=> $p['provider_status'] === 'OK',
				'CHECKED'    => $p['provider_checked'] ? $this->user->format_date((int) $p['provider_checked']) : '',
				'S_ENABLED'  => (bool) $p['provider_enabled'],
				'S_BUILTIN'  => (bool) $p['provider_builtin'],
				'S_IN_USE'   => $in_use,
				'S_HAS_KEY'  => $p['provider_auth_value'] !== '',
				'U_EDIT'     => $this->u_action . '&amp;action=edit&amp;id=' . $id,
				'U_DELETE'   => $p['provider_builtin'] ? '' : $this->u_action . '&amp;action=delete&amp;id=' . $id,
				'U_TOGGLE'   => $this->u_action . '&amp;action=toggle&amp;id=' . $id . '&amp;hash=' . $hash,
				'U_TEST'     => $this->u_action . '&amp;action=test&amp;id=' . $id . '&amp;hash=' . $hash,
			]);
		}

		$this->template->assign_vars([
			'S_PROVIDER_LIST'    => true,
			'ZODIAC_SOURCE_MODE' => $this->language->lang('ACP_ZODIAC_SOURCE_' . strtoupper($mode)),
			'U_ADD'              => $this->u_action . '&amp;action=add',
			'U_HOROSCOPE'        => str_replace('mode=providers', 'mode=horoscope', $this->u_action),
		]);
	}

	protected function require_provider(int $pid): array
	{
		$provider = $pid ? $this->horoscope->get_provider($pid) : null;

		if (!$provider)
		{
			trigger_error($this->language->lang('ACP_ZODIAC_PROVIDER_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		return $provider;
	}

	protected function provider_form(int $pid): void
	{
		$provider = $pid ? $this->require_provider($pid) : [
			'provider_name'        => '',
			'provider_lang'        => 'it',
			'provider_url_weekly'  => '',
			'provider_url_monthly' => '',
			'provider_sign_format' => 'en_lower',
			'provider_auth_header' => '',
			'provider_auth_value'  => '',
			'provider_headers'     => '',
			'provider_json_path'   => '',
			'provider_priority'    => 10,
			'provider_enabled'     => 1,
			'provider_builtin'     => 0,
		];

		if ($this->request->is_set_post('submit'))
		{
			$this->check_form();

			$data = [
				'provider_name'        => trim($this->request->variable('provider_name', '', true)),
				'provider_lang'        => $this->request->variable('provider_lang', 'en'),
				'provider_url_weekly'  => trim(htmlspecialchars_decode($this->request->variable('provider_url_weekly', ''), ENT_COMPAT)),
				'provider_url_monthly' => trim(htmlspecialchars_decode($this->request->variable('provider_url_monthly', ''), ENT_COMPAT)),
				'provider_sign_format' => $this->request->variable('provider_sign_format', 'en_lower'),
				'provider_auth_header' => trim($this->request->variable('provider_auth_header', '')),
				'provider_headers'     => trim(htmlspecialchars_decode($this->request->variable('provider_headers', '', true), ENT_COMPAT)),
				'provider_json_path'   => trim($this->request->variable('provider_json_path', '')),
				'provider_priority'    => min(999, max(0, $this->request->variable('provider_priority', 10))),
				'provider_enabled'     => $this->request->variable('provider_enabled', 0) ? 1 : 0,
			];

			if ($data['provider_name'] === '')
			{
				$this->errors[] = $this->language->lang('ACP_ZODIAC_ERR_NAME');
			}

			if (!in_array($data['provider_lang'], horoscope::LANGS, true))
			{
				$data['provider_lang'] = 'en';
			}

			if (!in_array($data['provider_sign_format'], horoscope::SIGN_FORMATS, true))
			{
				$data['provider_sign_format'] = 'en_lower';
			}

			if ($data['provider_url_weekly'] === '' && $data['provider_url_monthly'] === '')
			{
				$this->errors[] = $this->language->lang('ACP_ZODIAC_ERR_URL_NONE');
			}

			foreach (['provider_url_weekly', 'provider_url_monthly'] as $field)
			{
				if ($data[$field] !== '' && !preg_match('#^https?://[^\s]+$#i', $data[$field]))
				{
					$this->errors[] = $this->language->lang('ACP_ZODIAC_ERR_URL');
					break;
				}
			}

			if ($data['provider_auth_header'] !== '' && !preg_match('#^[A-Za-z0-9\-_]+$#', $data['provider_auth_header']))
			{
				$this->errors[] = $this->language->lang('ACP_ZODIAC_ERR_HEADER');
			}

			// The key: empty field = keep the current one
			$new_key = trim(htmlspecialchars_decode($this->request->variable('provider_auth_value', '', true), ENT_COMPAT));

			if ($this->request->variable('provider_auth_clear', 0))
			{
				$data['provider_auth_value'] = '';
			}
			else if ($new_key !== '')
			{
				$data['provider_auth_value'] = $this->horoscope->encrypt($new_key);
			}

			if (!$this->errors)
			{
				if (!$pid)
				{
					$data['provider_builtin'] = 0;
					$data['provider_auth_value'] = $data['provider_auth_value'] ?? '';
					$data['provider_status'] = '';
					$data['provider_checked'] = 0;
				}

				$this->horoscope->save_provider($data, $pid);
				$this->add_log($pid ? 'LOG_ZODIAC_PROVIDER_EDIT' : 'LOG_ZODIAC_PROVIDER_ADD', [$data['provider_name']]);
				trigger_error($this->language->lang('ACP_ZODIAC_PROVIDER_SAVED') . adm_back_link($this->u_action));
			}

			$provider = array_merge($provider, $data);
		}

		foreach (horoscope::SIGN_FORMATS as $format)
		{
			$this->template->assign_block_vars('formats', [
				'VALUE'      => $format,
				'NAME'       => $this->language->lang('ACP_ZODIAC_FORMAT_' . strtoupper($format), $this->horoscope->format_sign(12, $format)),
				'S_SELECTED' => $provider['provider_sign_format'] === $format,
			]);
		}

		$this->template->assign_vars([
			'S_PROVIDER_FORM'  => true,
			'S_EDIT'           => (bool) $pid,
			'S_BUILTIN'        => (bool) $provider['provider_builtin'],
			'P_NAME'           => $provider['provider_name'],
			'P_LANG'           => $provider['provider_lang'],
			'P_URL_WEEKLY'     => htmlspecialchars($provider['provider_url_weekly'], ENT_COMPAT, 'UTF-8'),
			'P_URL_MONTHLY'    => htmlspecialchars($provider['provider_url_monthly'], ENT_COMPAT, 'UTF-8'),
			'P_AUTH_HEADER'    => $provider['provider_auth_header'],
			'S_HAS_KEY'        => (string) $provider['provider_auth_value'] !== '',
			'P_HEADERS'        => htmlspecialchars($provider['provider_headers'], ENT_COMPAT, 'UTF-8'),
			'P_JSON_PATH'      => $provider['provider_json_path'],
			'P_PRIORITY'       => (int) $provider['provider_priority'],
			'P_ENABLED'        => (bool) $provider['provider_enabled'],
			'U_FORM'           => $this->u_action . '&amp;action=' . ($pid ? 'edit&amp;id=' . $pid : 'add'),
			'U_BACK'           => $this->u_action,
		]);
	}

	/* ------------------------------------------------------------------
	 * Check-up
	 * ------------------------------------------------------------------ */

	protected function mode_diagnostics(): void
	{
		global $phpbb_container;

		$this->tpl_name = 'acp_zodiac_diagnostics';
		$this->page_title = 'ACP_ZODIAC_DIAGNOSTICS';

		/** @var \salvocortesiano\zodiacsigns\core\diagnostics $diag */
		$diag = $phpbb_container->get('salvocortesiano.zodiacsigns.diagnostics');

		$live = false;
		$full = false;
		$cron_msg = '';

		if ($this->request->is_set_post('run_cron'))
		{
			$this->check_form();

			$task = $phpbb_container->get('cron.manager')->find_task(\salvocortesiano\zodiacsigns\core\diagnostics::CRON_NAME);

			if (!$task)
			{
				$this->errors[] = $this->language->lang('ACP_ZODIAC_DIAG_CRON_NOT_FOUND');
			}
			else if (!$task->is_runnable())
			{
				$this->errors[] = $this->language->lang('ACP_ZODIAC_DIAG_CRON_NOT_RUNNABLE');
			}
			else
			{
				@set_time_limit(300);
				$start = microtime(true);
				$task->run();
				$report = $this->horoscope->get_report();
				$cron_msg = $this->language->lang('ACP_ZODIAC_DIAG_CRON_RAN', round(microtime(true) - $start, 1))
					. ' ' . $this->language->lang('ACP_ZODIAC_FETCH_DONE', (int) ($report['fetched'] ?? 0), (int) ($report['failed'] ?? 0), (int) ($report['skipped'] ?? 0))
					. (!empty($report['pending']) ? ' ' . $this->language->lang('ACP_ZODIAC_DIAG_CRON_MORE') : '');
				$this->add_log('LOG_ZODIAC_DIAG_CRON');
			}
		}

		if ($this->request->is_set_post('run'))
		{
			$this->check_form();
			$live = (bool) $this->request->variable('diag_live', 0);
			$full = $live && $this->request->variable('diag_full', 0);
		}

		$result = $diag->run($live, $full);
		$labels = [
			'ok'    => $this->language->lang('ACP_ZODIAC_DIAG_OK'),
			'warn'  => $this->language->lang('ACP_ZODIAC_DIAG_WARN'),
			'error' => $this->language->lang('ACP_ZODIAC_DIAG_ERROR'),
			'info'  => $this->language->lang('ACP_ZODIAC_DIAG_INFO'),
		];
		$text = [
			'Zodiac Signs ' . $this->config['zodiac_version'] . ' - check-up ' . date('Y-m-d H:i') . ($live ? ' (live' . ($full ? ', 12' : '') . ')' : ''),
			'phpBB ' . $this->config['version'] . ', PHP ' . PHP_VERSION,
		];

		foreach ($result['sections'] as $section)
		{
			$worst = 'ok';

			foreach ($section['rows'] as $row)
			{
				if ($row['status'] === 'error' || ($row['status'] === 'warn' && $worst !== 'error'))
				{
					$worst = $row['status'];
				}
			}

			$this->template->assign_block_vars('diag', [
				'TITLE'  => $section['title'],
				'STATUS' => $worst,
			]);
			$text[] = '';
			$text[] = '== ' . $section['title'] . ' ==';

			foreach ($section['rows'] as $row)
			{
				$this->template->assign_block_vars('diag.rows', [
					'STATUS'       => $row['status'],
					'STATUS_LABEL' => $labels[$row['status']],
					'LABEL'        => htmlspecialchars($row['label'], ENT_COMPAT, 'UTF-8'),
					'DETAIL'       => nl2br(htmlspecialchars($row['detail'], ENT_COMPAT, 'UTF-8')),
				]);
				$text[] = '[' . strtoupper($row['status']) . '] ' . $row['label'] . ($row['detail'] !== '' ? ': ' . str_replace("\n", ' | ', $row['detail']) : '');
			}
		}

		$this->template->assign_vars([
			'DIAG_OK'       => $result['summary']['ok'],
			'DIAG_WARN'     => $result['summary']['warn'],
			'DIAG_ERROR'    => $result['summary']['error'],
			'DIAG_INFO'     => $result['summary']['info'],
			'DIAG_LIVE'     => $live,
			'DIAG_FULL'     => $full,
			'DIAG_TIME'     => $this->user->format_date($result['time']),
			'DIAG_CRON_MSG' => $cron_msg,
			'DIAG_TEXT'     => htmlspecialchars(implode("\n", $text), ENT_COMPAT, 'UTF-8'),
		]);
	}
}
