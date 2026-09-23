<?php
/**
 *
 * Zodiac Signs. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano - https://netshadows.de
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace salvocortesiano\zodiacsigns\cron\task;

/**
 * Keeps the weekly and monthly horoscopes up to date.
 *
 * Runs every "interval" hours; a run makes at most MAX_REQUESTS HTTP calls so
 * the page that triggers the cron never waits long. If there is more to do,
 * the next run follows after a few minutes.
 */
class horoscope_update extends \phpbb\cron\task\base
{
	protected const MAX_REQUESTS = 24;
	protected const PENDING_DELAY = 300;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \salvocortesiano\zodiacsigns\core\horoscope */
	protected $horoscope;

	public function __construct(\phpbb\config\config $config, \phpbb\log\log_interface $log, \salvocortesiano\zodiacsigns\core\horoscope $horoscope)
	{
		$this->config    = $config;
		$this->log       = $log;
		$this->horoscope = $horoscope;
	}

	public function is_runnable()
	{
		return $this->horoscope->is_enabled();
	}

	public function should_run()
	{
		$elapsed = time() - (int) $this->config['zodiac_horo_last_run'];

		if (!empty($this->config['zodiac_horo_pending']))
		{
			return $elapsed >= self::PENDING_DELAY;
		}

		return $elapsed >= max(1, min(24, (int) $this->config['zodiac_horo_interval'])) * 3600;
	}

	public function run()
	{
		// Mark first, so two simultaneous visitors never start two runs
		$this->config->set('zodiac_horo_last_run', time(), false);

		$report = $this->horoscope->update(self::MAX_REQUESTS);
		$this->horoscope->save_report($report);

		// A run that fetched nothing because every source failed waits for the normal interval
		$pending = $report['pending'] && ($report['fetched'] > 0 || $report['failed'] === 0);
		$this->config->set('zodiac_horo_pending', $pending ? 1 : 0, false);

		if ($report['failed'] > 0 && $report['fetched'] === 0)
		{
			$this->log->add('critical', ANONYMOUS, '', 'LOG_ZODIAC_HORO_FAILED', false, [
				$report['failed'],
				$report['errors'][0] ?? '',
			]);
		}
	}
}
