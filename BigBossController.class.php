<?php

namespace Budabot\User\Modules;

use Budabot\Core\xml;
use DateTime;
use DateTimeZone;

/**
 * Authors:
 *	- Nadyita (RK5)
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'tara',
 *		accessLevel = 'all',
 *		description = 'Show next Tarasque spawntime(s)',
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'tarakill',
 *		accessLevel = 'member',
 *		description = 'Update Tarasque killtimer to now',
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'taraupdate',
 *		accessLevel = 'member',
 *		description = 'Update Tarasque killtimer to the given time',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaper',
 *		accessLevel = 'all',
 *		description = 'Show next Reaper spawntime(s)',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaperkill',
 *		accessLevel = 'member',
 *		description = 'Update Reaper killtimer to now',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'reaperupdate',
 *		accessLevel = 'member',
 *		description = 'Update Reaper killtimer to the given time',
 *		help        = 'reaper.txt'
 *	)
 */
class BigBossController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/** @Inject */
	public $text;

	/** @Inject */
	public $settingManager;

	/** @Inject */
	public $util;

	/** @Inject */
	public $db;

	/** @Inject */
	public $chatBot;

	const TARA = 'Tarasque';
	const REAPER = 'The Hollow Reaper';

	/** @Setup */
	public function setup() {
		$this->settingManager->add($this->moduleName, 'bigboss_channels', 'Channels to display bigboss alerts', 'edit', 'text', 'both', 'guild;priv;both', '', 'mod', 'tara.txt');
		$this->db->loadSQLFile($this->moduleName, 'bigboss_timers');
	}

	protected function getBigBossTimers($mobName=NULL) {
		if ($mobName !== NULL) {
			$data = $this->db->query("SELECT * FROM bigboss_timers WHERE mob_name = ?", $mobName);
		} else {
			$data = $this->db->query("SELECT * FROM bigboss_timers");
		}
		foreach ($data as $row) {
			$invulnerableTime = $row->killable - $row->spawn;
			$row->next_killable = $row->killable;
			$row->next_spawn    = $row->spawn;
			while ($row->next_killable < time()) {
				$row->next_killable += $row->timer + $invulnerableTime;
				$row->next_spawn    += $row->timer + $invulnerableTime;
			}
			if ($mobName !== NULL) {
				return $row;
			}
		}
		if ($mobName !== NULL) {
			return false;
		}
		return $data;
	}

	protected function niceTime($timestamp) {
		$time = new DateTime();
		$time->setTimestamp($timestamp);
		$time->setTimezone(new DateTimeZone('UTC'));
		return $time->format("D, H:i T (Y-m-d)");
	}

	protected function getNextSpawnsMessage($timer, $howMany=10) {
		$multiplicator = $timer->timer + $timer->killable - $timer->spawn;
		$times = [];
		for ($i = 0; $i < $howMany; $i++) {
			$spawnTime = $timer->next_spawn + $i*$multiplicator;
			$times[] = $this->niceTime($spawnTime);
		}
		$msg = "Timer updated".
		       " by <highlight>".$timer->submitter_name."<end>".
		       " at <highlight>".$this->niceTime($timer->time_submitted)."<end>.\n\n".
		       "<tab>- ".join("\n\n<tab>- ", $times);
		return $msg;
	}

	protected function getBigBossMessage($mobName) {
		$timer = $this->getBigBossTimers($mobName);
		if ($timer === false) {
			$msg = "I currently don't have an accurate timer for <highlight>$mobName<end>.";
			return $msg;
		}
		$spawnTimeMessage = '';
		if (time() < $timer->next_spawn) {
			$timeUntilSpawn = $this->util->unixtimeToReadable($timer->next_spawn-time());
			$spawnTimeMessage = " spawns in <highlight>$timeUntilSpawn<end> and";
		} else {
			$spawnTimeMessage = " spawned and";
		}
		$timeUntilKill = $this->util->unixtimeToReadable($timer->next_killable-time());
		$killTimeMessage = " will be vulnerable in <highlight>$timeUntilKill<end>";

		$nextSpawnsMessage = $this->getNextSpawnsMessage($timer);
		$spawntimes = $this->text->makeBlob("Spawntimes for $mobName", $nextSpawnsMessage);
		$msg = "$mobName${spawnTimeMessage}${killTimeMessage}. $spawntimes";
		return $msg;
	}

	public function bigBossKillCommand($sender, $mobName, $timeUntilSpawn, $timeUntilKillable) {
		$data = $this->db->queryRow("SELECT * FROM bigboss_timers WHERE mob_name = ?", $mobName);
		if ($data) {
			$this->db->exec(
				"UPDATE bigboss_timers SET ".
				"timer=?, spawn=?, killable=?, time_submitted=?, submitter_name=? WHERE mob_name=?",
				$timeUntilSpawn, time() + $timeUntilSpawn, time() + $timeUntilKillable, time(), $sender, $mobName
			);
		} else{
			$this->db->exec(
				"INSERT INTO bigboss_timers ".
				"(mob_name, timer, spawn, killable, time_submitted, submitter_name) ".
				"VALUES (?, ?, ?, ?, ?, ?)",
				$mobName, $timeUntilSpawn, time() + $timeUntilSpawn, time() + $timeUntilKillable, time(), $sender
			);
		}
		$msg = "The timer for <highlight>$mobName<end> has been updated.";
		return $msg;
	}

	public function bigBossUpdateCommand($sender, $arg, $mobName, $downTime, $timeUntilKillable) {
		$data = $this->db->queryRow("SELECT * FROM bigboss_timers WHERE mob_name = ?", $mobName);
		$newKillTime = $this->util->parseTime($arg);
		if ($newKillTime < 1) {
			$msg = "You must enter a valid time parameter for the time until <highlight>${mobName}<end> will be vulnerable.";
			return $msg;
		}
		$newKillTime += time();

		if ($data) {
			$this->db->exec(
				"UPDATE bigboss_timers SET ".
				"timer=?, spawn=?, killable=?, time_submitted=?, submitter_name=? WHERE mob_name=?",
				$downTime, $newKillTime-$timeUntilKillable, $newKillTime, time(), $sender, $mobName
			);
		} else {
			$this->db->exec(
				"INSERT INTO bigboss_timers ".
				"       (mob_name, timer, spawn, killable, time_submitted, submitter_name) ".
				"VALUES (?, ?, ?, ?, ?, ?)",
				$mobName, $downTime, $newKillTime-$timeUntilKillable, $newKillTime, time(), $sender
			);
		}
		$msg = "The timer for <highlight>$mobName<end> has been updated.";
		return $msg;
	}

	/**
	 * @HandlesCommand("tara")
	 * @Matches("/^tara$/i")
	 */
	public function taraCommand($message, $channel, $sender, $sendto, $args) {
		$sendto->reply($this->getBigBossMessage(static::TARA));
	}

	/**
	 * @HandlesCommand("tarakill")
	 * @Matches("/^tarakill$/i")
	 */
	public function taraKillCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossKillCommand($sender, static::TARA, 9*3600, 9.5*3600);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("taraupdate")
	 * @Matches("/^taraupdate ([a-z0-9 ]+)$/i")
	 */
	public function taraUpdateCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossUpdateCommand($sender, $args[1], static::TARA, 9*3600, 0.5*3600);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("reaper")
	 * @Matches("/^reaper$/i")
	 */
	public function reaperCommand($message, $channel, $sender, $sendto, $args) {
		$sendto->reply($this->getBigBossMessage(static::REAPER));
	}

	/**
	 * @HandlesCommand("reaperkill")
	 * @Matches("/^reaperkill$/i")
	 */
	public function reaperKillCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossKillCommand($sender, static::REAPER, 9*3600, 9.25*3600);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("reaperupdate")
	 * @Matches("/^reaperupdate ([a-z0-9 ]+)$/i")
	 */
	public function reaperUpdateCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossUpdateCommand($sender, $args[1], static::REAPER, 9*3600, 0.25*3600);
		$sendto->reply($msg);
	}

	protected function announceBigBossEvent($msg) {
		if ($this->settingManager->get('bigboss_channels') == "priv") {
			$this->chatBot->sendPrivate($msg, true);
		} elseif ($this->settingManager->get('bigboss_channels') == "guild") {
			$this->chatBot->sendGuild($msg, true);
		} elseif ($this->settingManager->get('bigboss_channels') == "both") {
			$this->chatBot->sendPrivate($msg, true);
			$this->chatBot->sendGuild($msg, true);
		}
	}

	/**
	 * @Event("timer(10sec)")
	 * @Description("Check timer to announce big boss events")
	 */
	public function checkTimerEvent($eventObj) {
		$timers = $this->getBigBossTimers();
		foreach ($timers as $timer) {
			$invulnerableTime = $timer->killable - $timer->spawn;
			if ($timer->next_spawn <= time()+15*60 && $timer->next_spawn > time()+15*60-10) {
				$msg = "<highlight>".$timer->mob_name."<end> will spawn in ".
				       "<highlight>".$this->util->unixtimeToReadable($timer->next_spawn-time())."<end>.";
				$this->announceBigBossEvent($msg);
			}
			if ($timer->next_spawn <= time() && $timer->next_spawn > time()-10) {
				$msg = "<highlight>".$timer->mob_name."<end> has spawned and will be vulnerable in ".
				       "<highlight>".$this->util->unixtimeToReadable($timer->next_killable-time())."<end>.";
				$this->announceBigBossEvent($msg);
			}
			$nextKillTime = time() + $timer->timer+$invulnerableTime;
			if ($timer->next_killable == time() || ($timer->next_killable <= $nextKillTime && $timer->next_killable > $nextKillTime-10)) {
				$msg = "<highlight>".$timer->mob_name."<end> is now vulnerable.";
				$this->announceBigBossEvent($msg);
			}
		}
	}
}
