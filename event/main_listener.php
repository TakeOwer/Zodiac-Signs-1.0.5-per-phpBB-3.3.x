<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use salvocortesiano\zodiacsigns\core\zodiac;

class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \salvocortesiano\zodiacsigns\core\zodiac */
	protected $zodiac;

	/** @var \salvocortesiano\zodiacsigns\core\horoscope */
	protected $horoscope;

	/** @var string */
	protected $php_ext;

	/** Sign chosen in the registration form */
	protected $register_sign = 0;
	protected $register_day = 0;
	protected $register_month = 0;

	/** The UCP profile page is handling the sign for this user */
	protected $ucp_active = false;

	/** @var array poster_id => bool, filled once per topic page */
	protected $poster_allowed = [];

	public function __construct(\phpbb\config\config $config, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, \phpbb\language\language $language, \phpbb\controller\helper $helper, \salvocortesiano\zodiacsigns\core\zodiac $zodiac, \salvocortesiano\zodiacsigns\core\horoscope $horoscope, $php_ext)
	{
		$this->config    = $config;
		$this->request   = $request;
		$this->template  = $template;
		$this->user      = $user;
		$this->language  = $language;
		$this->helper    = $helper;
		$this->zodiac    = $zodiac;
		$this->horoscope = $horoscope;
		$this->php_ext   = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'                         => 'load_language',
			'core.page_header_after'                  => 'page_header_after',

			'core.ucp_register_data_before'           => 'register_data_before',
			'core.ucp_register_data_after'            => 'register_validate',
			'core.ucp_register_modify_template_data'  => 'register_template',
			'core.ucp_register_user_row_after'        => 'register_user_row',

			'core.ucp_profile_modify_profile_info'    => 'ucp_profile_data',
			'core.ucp_profile_validate_profile_info'  => 'ucp_profile_validate',
			'core.ucp_profile_info_modify_sql_ary'    => 'ucp_profile_sql',

			'core.viewtopic_cache_user_data'          => 'viewtopic_cache_user',
			'core.viewtopic_modify_post_data'         => 'viewtopic_post_data',
			'core.viewtopic_modify_post_row'          => 'viewtopic_post_row',

			'core.memberlist_view_profile'            => 'memberlist_view',
		];
	}

	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'salvocortesiano/zodiacsigns',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Global template data, popover URLs and the reminder for members without a sign.
	 */
	public function page_header_after($event)
	{
		if (!$this->zodiac->is_enabled())
		{
			return;
		}

		$horo = $this->horoscope->is_enabled();
		$urls = [];

		if ($horo)
		{
			foreach (zodiac::SIGNS as $sign)
			{
				$urls[$sign[0]] = $this->helper->route('salvocortesiano_zodiacsigns_horoscope', ['sign' => $sign[0]], false);
			}
		}

		$this->template->assign_vars([
			'S_ZODIAC_ENABLED'  => true,
			'S_ZODIAC_HORO'     => $horo,
			'ZODIAC_HORO_URLS'  => htmlspecialchars(json_encode($urls, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'),
			'ZODIAC_BADGE_SIZE' => (int) $this->config['zodiac_badge_size'],
		]);

		if (empty($this->config['zodiac_remind'])
			|| empty($this->user->data['is_registered'])
			|| !empty($this->user->data['is_bot'])
			|| (int) $this->user->data['user_zodiac_sign'] !== 0)
		{
			return;
		}

		// No reminder on the page where the sign is chosen
		$page = $this->user->page ?? [];

		if (($page['page_name'] ?? '') === 'ucp.' . $this->php_ext && strpos((string) ($page['query_string'] ?? ''), 'profile_info') !== false)
		{
			return;
		}

		if ($this->zodiac->user_allowed((int) $this->user->data['user_id']))
		{
			$this->template->assign_vars([
				'S_ZODIAC_REMIND' => true,
				'U_ZODIAC_UCP'    => append_sid(generate_board_url() . '/ucp.' . $this->php_ext, 'i=ucp_profile&amp;mode=profile_info'),
			]);
		}
	}

	/* ------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	public function register_data_before($event)
	{
		if ($this->zodiac->registration_mode() === 0)
		{
			return;
		}

		$this->register_day = $this->request->variable('zodiac_bday_day', 0);
		$this->register_month = $this->request->variable('zodiac_bday_month', 0);

		$sign = $this->request->variable('zodiac_sign', 0);
		$sign = $this->zodiac->valid_id($sign) ? (int) $sign : 0;

		// Without JavaScript the radio is not sent: use the day/month helper
		if (!$this->request->is_set_post('zodiac_sign') && $this->register_day && $this->register_month)
		{
			$sign = $this->zodiac->sign_from_date($this->register_day, $this->register_month);
		}

		$this->register_sign = $sign;

		$data = $event['data'];
		$data['zodiac_sign'] = $sign;
		$event['data'] = $data;
	}

	public function register_validate($event)
	{
		if (!$event['submit'] || $this->zodiac->registration_mode() !== 2)
		{
			return;
		}

		if ($this->register_sign === 0)
		{
			$error = $event['error'];
			$error[] = $this->language->lang('ZODIAC_ERR_REQUIRED');
			$event['error'] = $error;
		}
	}

	public function register_template($event)
	{
		$mode = $this->zodiac->registration_mode();

		if ($mode === 0)
		{
			return;
		}

		$this->assign_picker($this->register_sign, $mode === 2, '#zodiac_bday_day', '#zodiac_bday_month');

		for ($d = 1; $d <= 31; $d++)
		{
			$this->template->assign_block_vars('zodiac_days', [
				'VALUE'      => $d,
				'S_SELECTED' => $d === $this->register_day,
			]);
		}

		$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

		foreach ($months as $i => $month)
		{
			$this->template->assign_block_vars('zodiac_months', [
				'VALUE'      => $i + 1,
				'NAME'       => $this->language->lang(['datetime', $month]),
				'S_SELECTED' => ($i + 1) === $this->register_month,
			]);
		}

		$this->template->assign_var('S_ZODIAC_REGISTER', true);
	}

	public function register_user_row($event)
	{
		if ($this->zodiac->registration_mode() === 0)
		{
			return;
		}

		$user_row = $event['user_row'];
		$user_row['user_zodiac_sign'] = $this->register_sign;
		$event['user_row'] = $user_row;
	}

	/* ------------------------------------------------------------------
	 * UCP > Profile > Edit profile
	 * ------------------------------------------------------------------ */

	public function ucp_profile_data($event)
	{
		if (!$this->zodiac->is_enabled() || !$this->zodiac->user_allowed((int) $this->user->data['user_id']))
		{
			return;
		}

		$this->ucp_active = true;
		$data = $event['data'];

		if ($event['submit'])
		{
			$sign = $this->request->variable('zodiac_sign', 0);
			$sign = $this->zodiac->valid_id($sign) ? (int) $sign : 0;

			// Automatic only when the field was not sent at all (JavaScript off):
			// an explicit "none" chosen by the member is respected
			if (!$this->request->is_set_post('zodiac_sign') && !empty($this->config['allow_birthdays']))
			{
				$sign = $this->zodiac->sign_from_date($this->request->variable('bday_day', 0), $this->request->variable('bday_month', 0));
			}
		}
		else
		{
			$sign = (int) $this->user->data['user_zodiac_sign'];

			// First visit with a birthday but no sign: suggest it
			if ($sign === 0 && !empty($this->config['allow_birthdays']))
			{
				$sign = $this->zodiac->sign_from_birthday((string) $this->user->data['user_birthday']);
			}
		}

		$data['zodiac_sign'] = $sign;
		$event['data'] = $data;

		$this->assign_picker($sign, !empty($this->config['zodiac_ucp_required']), '#bday_day', '#bday_month');
		$this->template->assign_var('S_ZODIAC_UCP', true);

		$saved = (int) $this->user->data['user_zodiac_sign'];

		if ($saved && $this->horoscope->is_enabled())
		{
			$this->template->assign_vars([
				'S_ZODIAC_UCP_HORO'  => true,
				'ZODIAC_UCP_TITLE'   => $this->language->lang('ZODIAC_HORO_FOR', $this->zodiac->name($saved)),
				'ZODIAC_UCP_IMG'     => $this->zodiac->image_url($saved),
				'ZODIAC_UCP_ELEMENT' => zodiac::SIGNS[$saved][1],
			]);
			$this->assign_horoscope($saved, 'zodiac_ucp_horo');
		}
	}

	public function ucp_profile_validate($event)
	{
		if (!$this->ucp_active || !$event['submit'] || empty($this->config['zodiac_ucp_required']))
		{
			return;
		}

		if (empty($event['data']['zodiac_sign']))
		{
			$error = $event['error'];
			$error[] = $this->language->lang('ZODIAC_ERR_REQUIRED');
			$event['error'] = $error;
		}
	}

	public function ucp_profile_sql($event)
	{
		if (!$this->ucp_active)
		{
			return;
		}

		$sql_ary = $event['sql_ary'];
		$sql_ary['user_zodiac_sign'] = (int) ($event['data']['zodiac_sign'] ?? 0);
		$event['sql_ary'] = $sql_ary;
	}

	/* ------------------------------------------------------------------
	 * Viewtopic
	 * ------------------------------------------------------------------ */

	public function viewtopic_cache_user($event)
	{
		$data = $event['user_cache_data'];
		$data['zodiac_sign'] = (int) ($event['row']['user_zodiac_sign'] ?? 0);
		$data['zodiac_birthday'] = (string) ($event['row']['user_birthday'] ?? '');
		$event['user_cache_data'] = $data;
	}

	public function viewtopic_post_data($event)
	{
		if (!$this->zodiac->is_enabled() || empty($this->config['zodiac_show_viewtopic']))
		{
			return;
		}

		$ids = [];

		foreach ($event['rowset'] as $row)
		{
			$ids[] = (int) $row['user_id'];
		}

		// One query for all the posters of the page
		$this->poster_allowed = $this->zodiac->users_allowed($ids);
	}

	public function viewtopic_post_row($event)
	{
		if (!$this->zodiac->is_enabled() || empty($this->config['zodiac_show_viewtopic']))
		{
			return;
		}

		$row = isset($event['row']) && is_array($event['row']) ? $event['row'] : [];
		$poster_id = (int) $event['poster_id'];

		if ($poster_id <= 0)
		{
			$poster_id = (int) ($row['poster_id'] ?? ($row['user_id'] ?? 0));
		}

		if ($poster_id <= 0 || $poster_id === ANONYMOUS)
		{
			return;
		}

		// Data from the user cache, or straight from the post row (same u.* columns)
		$cache = $event['user_poster_data'];
		$stored = (int) ($cache['zodiac_sign'] ?? ($row['user_zodiac_sign'] ?? 0));
		$birthday = (string) ($cache['zodiac_birthday'] ?? ($row['user_birthday'] ?? ''));

		[$sign, $auto] = $this->zodiac->effective_sign($stored, $birthday);

		if (!$sign)
		{
			return;
		}

		// Normally filled once for the whole page; if not, ask now for this poster
		if (!isset($this->poster_allowed[$poster_id]))
		{
			$this->poster_allowed += $this->zodiac->users_allowed([$poster_id]);
		}

		if (empty($this->poster_allowed[$poster_id]))
		{
			return;
		}

		$event['post_row'] = array_merge($event['post_row'], $this->zodiac->badge_vars($sign), ['S_ZODIAC_AUTO' => $auto]);
	}

	/* ------------------------------------------------------------------
	 * Member profile
	 * ------------------------------------------------------------------ */

	public function memberlist_view($event)
	{
		if (!$this->zodiac->is_enabled() || empty($this->config['zodiac_show_profile']))
		{
			return;
		}

		$member = $event['member'];

		if (!$this->zodiac->user_allowed((int) $member['user_id']))
		{
			return;
		}

		[$sign, $auto] = $this->zodiac->effective_sign((int) ($member['user_zodiac_sign'] ?? 0), (string) ($member['user_birthday'] ?? ''));

		// Always a line in the profile, like the other statistics: without it the feature looks broken
		if (!$sign)
		{
			$own = (int) $member['user_id'] === (int) $this->user->data['user_id'];

			$this->template->assign_vars([
				'S_ZODIAC_MEMBER_NONE' => true,
				'U_ZODIAC_MEMBER_UCP'  => $own ? append_sid(generate_board_url() . '/ucp.' . $this->php_ext, 'i=ucp_profile&amp;mode=profile_info') : '',
			]);

			return;
		}

		$vars = ['S_ZODIAC_MEMBER_AUTO' => $auto];

		foreach ($this->zodiac->badge_vars($sign) as $k => $v)
		{
			$vars['MEMBER_' . $k] = $v;
		}

		$vars['ZODIAC_MEMBER_TITLE'] = $this->language->lang('ZODIAC_HORO_FOR', $this->zodiac->name($sign));
		$this->template->assign_vars($vars);

		if ($this->horoscope->is_enabled())
		{
			$this->template->assign_var('S_ZODIAC_MEMBER_HORO', true);
			$this->assign_horoscope($sign, 'zodiac_member_horo');
		}
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	protected function assign_picker(int $selected, bool $required, string $day_field, string $month_field): void
	{
		foreach ($this->zodiac->get_sign_rows() as $row)
		{
			$row['S_SELECTED'] = $row['ID'] === $selected;
			$this->template->assign_block_vars('zodiac_signs', $row);
		}

		$this->template->assign_vars([
			'S_ZODIAC_PICKER'    => true,
			'S_ZODIAC_REQUIRED'  => $required,
			'S_ZODIAC_NONE'      => $selected === 0,
			'ZODIAC_DAY_FIELD'   => $day_field,
			'ZODIAC_MONTH_FIELD' => $month_field,
		]);
	}

	protected function assign_horoscope(int $sign, string $block): void
	{
		$lang = $this->horoscope->display_lang();
		$items = $this->horoscope->get_for_sign(zodiac::SIGNS[$sign][0], $lang);

		foreach ($items as $period => $h)
		{
			$this->template->assign_block_vars($block, [
				'PERIOD' => $this->language->lang('ZODIAC_HORO_' . strtoupper($period)),
				'TEXT'   => nl2br(htmlspecialchars($h['text'], ENT_COMPAT, 'UTF-8')),
				'SOURCE' => (!empty($this->config['zodiac_horo_credit']) && $h['provider'] !== '') ? htmlspecialchars($this->language->lang('ZODIAC_HORO_SOURCE', $h['provider']), ENT_COMPAT, 'UTF-8') : '',
				'NOTE'   => $h['lang'] !== $lang ? $this->language->lang('ZODIAC_HORO_LANG_' . strtoupper($h['lang'])) : '',
			]);
		}

		$this->template->assign_var('S_' . strtoupper($block) . '_EMPTY', !$items);
	}
}
