<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * JSON used by the horoscope popover in viewtopic.
 */
class main
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \salvocortesiano\zodiacsigns\core\zodiac */
	protected $zodiac;

	/** @var \salvocortesiano\zodiacsigns\core\horoscope */
	protected $horoscope;

	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \salvocortesiano\zodiacsigns\core\zodiac $zodiac, \salvocortesiano\zodiacsigns\core\horoscope $horoscope)
	{
		$this->config    = $config;
		$this->language  = $language;
		$this->zodiac    = $zodiac;
		$this->horoscope = $horoscope;
	}

	public function horoscope($sign)
	{
		$this->language->add_lang('common', 'salvocortesiano/zodiacsigns');

		$id = $this->zodiac->id_from_key((string) $sign);

		if (!$id || !$this->zodiac->is_enabled() || !$this->horoscope->is_enabled())
		{
			return new JsonResponse(['ok' => false], 404);
		}

		$lang = $this->horoscope->display_lang();
		$items = [];

		foreach ($this->horoscope->get_for_sign((string) $sign, $lang) as $period => $h)
		{
			$items[] = [
				'period' => $period,
				'title'  => $this->language->lang('ZODIAC_HORO_' . strtoupper($period)),
				'text'   => $h['text'],
				'source' => (!empty($this->config['zodiac_horo_credit']) && $h['provider'] !== '') ? $this->language->lang('ZODIAC_HORO_SOURCE', $h['provider']) : '',
				'note'   => $h['lang'] !== $lang ? $this->language->lang('ZODIAC_HORO_LANG_' . strtoupper($h['lang'])) : '',
			];
		}

		$response = new JsonResponse([
			'ok'    => true,
			'name'  => $this->zodiac->name($id),
			'title' => $this->language->lang('ZODIAC_HORO_FOR', $this->zodiac->name($id)),
			'img'   => $this->zodiac->image_url($id),
			'items' => $items,
			'empty' => $this->language->lang('ZODIAC_HORO_NONE'),
		]);

		// The language can change per visitor: browser cache only, never shared proxies
		$response->setPrivate();
		$response->setMaxAge(900);

		return $response;
	}
}
