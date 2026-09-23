<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ZODIAC_SIGN'                  => 'Zodiac sign',
	'ZODIAC_SIGN_EXPLAIN'          => 'Optional. If you entered your birthday it is picked for you, but you can always change it.',
	'ZODIAC_SIGN_EXPLAIN_REQUIRED' => 'Required. Enter your day and month of birth and it is picked for you, but you can always change it.',
	'ZODIAC_NONE'                  => 'None',

	'ZODIAC_SIGN_ARIES'       => 'Aries',
	'ZODIAC_SIGN_TAURUS'      => 'Taurus',
	'ZODIAC_SIGN_GEMINI'      => 'Gemini',
	'ZODIAC_SIGN_CANCER'      => 'Cancer',
	'ZODIAC_SIGN_LEO'         => 'Leo',
	'ZODIAC_SIGN_VIRGO'       => 'Virgo',
	'ZODIAC_SIGN_LIBRA'       => 'Libra',
	'ZODIAC_SIGN_SCORPIO'     => 'Scorpio',
	'ZODIAC_SIGN_SAGITTARIUS' => 'Sagittarius',
	'ZODIAC_SIGN_CAPRICORN'   => 'Capricorn',
	'ZODIAC_SIGN_AQUARIUS'    => 'Aquarius',
	'ZODIAC_SIGN_PISCES'      => 'Pisces',

	'ZODIAC_RANGE_ARIES'       => 'Mar 21 – Apr 19',
	'ZODIAC_RANGE_TAURUS'      => 'Apr 20 – May 20',
	'ZODIAC_RANGE_GEMINI'      => 'May 21 – Jun 20',
	'ZODIAC_RANGE_CANCER'      => 'Jun 21 – Jul 22',
	'ZODIAC_RANGE_LEO'         => 'Jul 23 – Aug 22',
	'ZODIAC_RANGE_VIRGO'       => 'Aug 23 – Sep 22',
	'ZODIAC_RANGE_LIBRA'       => 'Sep 23 – Oct 22',
	'ZODIAC_RANGE_SCORPIO'     => 'Oct 23 – Nov 21',
	'ZODIAC_RANGE_SAGITTARIUS' => 'Nov 22 – Dec 21',
	'ZODIAC_RANGE_CAPRICORN'   => 'Dec 22 – Jan 19',
	'ZODIAC_RANGE_AQUARIUS'    => 'Jan 20 – Feb 18',
	'ZODIAC_RANGE_PISCES'      => 'Feb 19 – Mar 20',

	'ZODIAC_ELEMENT_FIRE'  => 'Fire',
	'ZODIAC_ELEMENT_EARTH' => 'Earth',
	'ZODIAC_ELEMENT_AIR'   => 'Air',
	'ZODIAC_ELEMENT_WATER' => 'Water',

	'ZODIAC_BIRTH_HELPER'         => 'Day and month of birth',
	'ZODIAC_BIRTH_HELPER_EXPLAIN' => 'Optional: only used to pick your sign.',
	'ZODIAC_DAY'                  => 'Day',
	'ZODIAC_MONTH'                => 'Month',
	'ZODIAC_HINT_AUTO'            => 'Picked from your birthday: %s.',
	'ZODIAC_HINT_MISMATCH'        => 'Based on your birthday your sign would be %s. You can keep your choice anyway.',
	'ZODIAC_ERR_REQUIRED'         => 'Please choose your zodiac sign.',

	'ZODIAC_HORO_TITLE'   => 'Horoscope',
	'ZODIAC_HORO_FOR'     => '%s horoscope',
	'ZODIAC_HORO_WEEKLY'  => 'This week',
	'ZODIAC_HORO_MONTHLY' => 'This month',
	'ZODIAC_HORO_NONE'    => 'The horoscope is not available yet, please try again later.',
	'ZODIAC_HORO_SOURCE'  => 'Source: %s',
	'ZODIAC_HORO_LANG_EN' => 'Text available in English only',
	'ZODIAC_HORO_LANG_IT' => 'Text available in Italian only',
	'ZODIAC_HORO_OPEN'    => 'Read the %s horoscope',
	'ZODIAC_HORO_LOADING' => 'Loading the horoscope…',
	'ZODIAC_HORO_ERROR'   => 'The horoscope could not be loaded. Please try again shortly.',
	'ZODIAC_CLOSE'        => 'Close',

	'ZODIAC_REMIND'      => 'You have not chosen your zodiac sign yet.',
	'ZODIAC_REMIND_LINK' => 'Choose your sign',

	// 1.0.5
	'ZODIAC_NOT_SET' => 'Not set',
	'ZODIAC_FROM_BIRTHDAY' => 'from the birthday',
]);
