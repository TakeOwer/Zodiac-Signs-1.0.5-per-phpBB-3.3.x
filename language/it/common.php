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
	'ZODIAC_SIGN'                  => 'Segno zodiacale',
	'ZODIAC_SIGN_EXPLAIN'          => 'Facoltativo. Se hai indicato la data di nascita viene scelto in automatico, ma puoi sempre cambiarlo.',
	'ZODIAC_SIGN_EXPLAIN_REQUIRED' => 'Obbligatorio. Se indichi giorno e mese di nascita viene scelto in automatico, ma puoi sempre cambiarlo.',
	'ZODIAC_NONE'                  => 'Nessuno',

	'ZODIAC_SIGN_ARIES'       => 'Ariete',
	'ZODIAC_SIGN_TAURUS'      => 'Toro',
	'ZODIAC_SIGN_GEMINI'      => 'Gemelli',
	'ZODIAC_SIGN_CANCER'      => 'Cancro',
	'ZODIAC_SIGN_LEO'         => 'Leone',
	'ZODIAC_SIGN_VIRGO'       => 'Vergine',
	'ZODIAC_SIGN_LIBRA'       => 'Bilancia',
	'ZODIAC_SIGN_SCORPIO'     => 'Scorpione',
	'ZODIAC_SIGN_SAGITTARIUS' => 'Sagittario',
	'ZODIAC_SIGN_CAPRICORN'   => 'Capricorno',
	'ZODIAC_SIGN_AQUARIUS'    => 'Acquario',
	'ZODIAC_SIGN_PISCES'      => 'Pesci',

	'ZODIAC_RANGE_ARIES'       => '21 mar – 19 apr',
	'ZODIAC_RANGE_TAURUS'      => '20 apr – 20 mag',
	'ZODIAC_RANGE_GEMINI'      => '21 mag – 20 giu',
	'ZODIAC_RANGE_CANCER'      => '21 giu – 22 lug',
	'ZODIAC_RANGE_LEO'         => '23 lug – 22 ago',
	'ZODIAC_RANGE_VIRGO'       => '23 ago – 22 set',
	'ZODIAC_RANGE_LIBRA'       => '23 set – 22 ott',
	'ZODIAC_RANGE_SCORPIO'     => '23 ott – 21 nov',
	'ZODIAC_RANGE_SAGITTARIUS' => '22 nov – 21 dic',
	'ZODIAC_RANGE_CAPRICORN'   => '22 dic – 19 gen',
	'ZODIAC_RANGE_AQUARIUS'    => '20 gen – 18 feb',
	'ZODIAC_RANGE_PISCES'      => '19 feb – 20 mar',

	'ZODIAC_ELEMENT_FIRE'  => 'Fuoco',
	'ZODIAC_ELEMENT_EARTH' => 'Terra',
	'ZODIAC_ELEMENT_AIR'   => 'Aria',
	'ZODIAC_ELEMENT_WATER' => 'Acqua',

	'ZODIAC_BIRTH_HELPER'         => 'Giorno e mese di nascita',
	'ZODIAC_BIRTH_HELPER_EXPLAIN' => 'Facoltativo: serve solo a scegliere il segno per te.',
	'ZODIAC_DAY'                  => 'Giorno',
	'ZODIAC_MONTH'                => 'Mese',
	'ZODIAC_HINT_AUTO'            => 'Scelto in base alla data di nascita: %s.',
	'ZODIAC_HINT_MISMATCH'        => 'In base alla data di nascita il tuo segno sarebbe %s. Puoi comunque tenere quello che hai scelto.',
	'ZODIAC_ERR_REQUIRED'         => 'Scegli il tuo segno zodiacale.',

	'ZODIAC_HORO_TITLE'   => 'Oroscopo',
	'ZODIAC_HORO_FOR'     => 'Oroscopo del segno %s',
	'ZODIAC_HORO_WEEKLY'  => 'Questa settimana',
	'ZODIAC_HORO_MONTHLY' => 'Questo mese',
	'ZODIAC_HORO_NONE'    => 'L’oroscopo non è ancora disponibile, riprova più tardi.',
	'ZODIAC_HORO_SOURCE'  => 'Fonte: %s',
	'ZODIAC_HORO_LANG_EN' => 'Testo disponibile solo in inglese',
	'ZODIAC_HORO_LANG_IT' => 'Testo disponibile solo in italiano',
	'ZODIAC_HORO_OPEN'    => 'Leggi l’oroscopo del segno %s',
	'ZODIAC_HORO_LOADING' => 'Carico l’oroscopo…',
	'ZODIAC_HORO_ERROR'   => 'Impossibile caricare l’oroscopo. Riprova tra poco.',
	'ZODIAC_CLOSE'        => 'Chiudi',

	'ZODIAC_REMIND'      => 'Non hai ancora scelto il tuo segno zodiacale.',
	'ZODIAC_REMIND_LINK' => 'Scegli il segno',

	// 1.0.5
	'ZODIAC_NOT_SET' => 'Non indicato',
	'ZODIAC_FROM_BIRTHDAY' => 'dalla data di nascita',
]);
