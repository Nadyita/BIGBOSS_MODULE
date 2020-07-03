<?php

namespace Budabot\User\Modules;

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
 *		command     = 'bb',
 *		accessLevel = 'all',
 *		description = 'Show next spawntime(s)',
 *		help        = 'bb.txt'
 *	)
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
 *		help        = 'tara.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'taradel',
 *		accessLevel = 'member',
 *		description = 'Delete the timer for Tarasque until someone re-creates it',
 *		help        = 'tara.txt'
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
 *
 *	@DefineCommand(
 *		command     = 'reaperdel',
 *		accessLevel = 'member',
 *		description = 'Delete the timer for The Hollow Reaper until someone re-creates it',
 *		help        = 'reaper.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'loren',
 *		accessLevel = 'all',
 *		description = 'Show next Loren Warr spawntime(s)',
 *		help        = 'loren.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'lorenkill',
 *		accessLevel = 'member',
 *		description = 'Update Loren Warr killtimer to now',
 *		help        = 'loren.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'lorenupdate',
 *		accessLevel = 'member',
 *		description = 'Update Loren killtimer to the given time',
 *		help        = 'loren.txt'
 *	)
 *
 *	@DefineCommand(
 *		command     = 'lorendel',
 *		accessLevel = 'member',
 *		description = 'Delete the timer for Loren Warr until someone re-creates it',
 *		help        = 'loren.txt'
 *	)
 */
class BigBossController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public $moduleName;

	/**
	 * @var \Budabot\Core\Text
	 * @Inject
	 */
	public $text;

	/**
	 * @var \Budabot\Core\SettingManager
	 * @Inject
	 */
	public $settingManager;

	/**
	 * @var \Budabot\Core\Util
	 * @Inject
	 */
	public $util;

	/**
	 * @var \Budabot\Core\DB
	 * @Inject
	 */
	public $db;

	/**
	 * @var \Budabot\Core\Budabot
	 * @Inject
	 */
	public $chatBot;

	/**
	 * @var \Budabot\Core\Modules\DiscordController $discordController
	 * @Inject
	 */
	public $discordController;

	const TARA = 'Tarasque';
	const REAPER = 'The Hollow Reaper';
	const LOREN = 'Loren Warr';

	/** @Setup */
	public function setup() {
		$this->settingManager->add(
			$this->moduleName,
			'bigboss_channels_prespawn',
			'Channels to display bigboss pre-spawn alers',
			'edit',
			'text',
			'3',
			'none;guild;priv;guild+priv;discord;discord+guild;discord+priv;discord+priv+guild',
			'0;1;2;3;4;5;6;7',
			'mod',
			'tara.txt'
		);
		$this->settingManager->add(
			$this->moduleName,
			'bigboss_channels_spawn',
			'Channels to display bigboss spawn alerts',
			'edit',
			'text',
			'7',
			'none;guild;priv;guild+priv;discord;discord+guild;discord+priv;discord+priv+guild',
			'0;1;2;3;4;5;6;7',
			'mod',
			'tara.txt'
		);
		$this->settingManager->add(
			$this->moduleName,
			'bigboss_channels_vulnerable',
			'Channels to display bigboss vulnerable alerts',
			'edit',
			'text',
			'3',
			'none;guild;priv;guild+priv;discord;discord+guild;discord+priv;discord+priv+guild',
			'0;1;2;3;4;5;6;7',
			'mod',
			'tara.txt'
		);
		$this->db->loadSQLFile($this->moduleName, 'bigboss_timers');
	}

	protected function getBigBossTimers($mobName=null) {
		if ($mobName !== null) {
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
			if ($mobName !== null) {
				return $row;
			}
		}
		if ($mobName !== null) {
			return false;
		}
		usort($data, function($a, $b) {
			if ($a->next_spawn === $b->next_spawn) {
				return 0;
			}
			return $a->next_spawn < $b->next_spawn ? -1 : 1;
		});
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
		return $this->formatBigBossMessage($timer, false);
	}

	public function formatBigBossMessage($timer, $short=true) {
		$spawnTimeMessage = '';
		if (time() < $timer->next_spawn) {
			$timeUntilSpawn = $this->util->unixtimeToReadable($timer->next_spawn-time());
			$spawnTimeMessage = " spawns in <highlight>$timeUntilSpawn<end>";
			if ($short) {
				return "{$timer->mob_name}{$spawnTimeMessage}.";
			}
			$spawnTimeMessage .= " and";
		} else {
			$spawnTimeMessage = " spawned and";
		}
		$timeUntilKill = $this->util->unixtimeToReadable($timer->next_killable-time());
		$killTimeMessage = " will be vulnerable in <highlight>$timeUntilKill<end>";
		if ($short) {
			return "{$timer->mob_name}{$spawnTimeMessage}{$killTimeMessage}.";
		}

		$nextSpawnsMessage = $this->getNextSpawnsMessage($timer);
		$spawntimes = $this->text->makeBlob("Spawntimes for {$timer->mob_name}", $nextSpawnsMessage);
		$msg = "{$timer->mob_name}${spawnTimeMessage}${killTimeMessage}. $spawntimes";
		return $msg;
	}

	public function bigBossDeleteCommand($sender, $mobName) {
		$row = $this->db->queryRow("SELECT * FROM bigboss_timers WHERE mob_name = ?", $mobName);
		if ($row === null) {
			$msg = "There is currently no timer for <highlight>$mobName<end>.";
		} else {
			$this->db->exec("DELETE FROM bigboss_timers WHERE mob_name = ?", $mobName);
			$msg = "The timer for <highlight>$mobName<end> has been deleted.";
		}
		return $msg;
	}

	public function bigBossKillCommand($sender, $mobName, $timeUntilSpawn, $timeUntilKillable) {
		$data = $this->db->queryRow("SELECT * FROM bigboss_timers WHERE mob_name = ?", $mobName);
		if ($data) {
			$this->db->exec(
				"UPDATE bigboss_timers SET ".
				"timer=?, spawn=?, killable=?, time_submitted=?, submitter_name=? WHERE mob_name=?",
				$timeUntilSpawn,
				time() + $timeUntilSpawn,
				time() + $timeUntilKillable,
				time(),
				$sender,
				$mobName
			);
		} else {
			$this->db->exec(
				"INSERT INTO bigboss_timers ".
				"(mob_name, timer, spawn, killable, time_submitted, submitter_name) ".
				"VALUES (?, ?, ?, ?, ?, ?)",
				$mobName,
				$timeUntilSpawn,
				time() + $timeUntilSpawn,
				time() + $timeUntilKillable,
				time(),
				$sender
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
				$downTime,
				$newKillTime-$timeUntilKillable,
				$newKillTime,
				time(),
				$sender,
				$mobName
			);
		} else {
			$this->db->exec(
				"INSERT INTO bigboss_timers ".
				"       (mob_name, timer, spawn, killable, time_submitted, submitter_name) ".
				"VALUES (?, ?, ?, ?, ?, ?)",
				$mobName,
				$downTime,
				$newKillTime-$timeUntilKillable,
				$newKillTime,
				time(),
				$sender
			);
		}
		$msg = "The timer for <highlight>$mobName<end> has been updated.";
		return $msg;
	}

	/**
	 * @HandlesCommand("bb")
	 * @Matches("/^bb$/i")
	 */
	public function bbCommand($message, $channel, $sender, $sendto, $args) {
		$timers = $this->getBigBossTimers();
		if ($timers === false || !count($timers)) {
			$msg = "I currently don't have an accurate timer for any boss.";
			$sendto->reply($msg);
			return $msg;
		}
		$messages = array_map([$this, 'formatBigBossMessage'], $timers);
		$msg = $messages[0];
		if (count($messages) > 1) {
			$msg = "I'm currently monitoring the following bosses:\n".
				join("\n", $messages);
		}
		$sendto->reply($msg);
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
	 * @HandlesCommand("taradel")
	 * @Matches("/^taradel$/i")
	 */
	public function taraDeleteCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossDeleteCommand($sender, static::TARA);
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

	/**
	 * @HandlesCommand("reaperdel")
	 * @Matches("/^reaperdel$/i")
	 */
	public function reaperDeleteCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossDeleteCommand($sender, static::REAPER);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("loren")
	 * @Matches("/^loren$/i")
	 */
	public function lorenCommand($message, $channel, $sender, $sendto, $args) {
		$sendto->reply($this->getBigBossMessage(static::LOREN));
	}

	/**
	 * @HandlesCommand("lorenkill")
	 * @Matches("/^lorenkill$/i")
	 */
	public function lorenKillCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossKillCommand($sender, static::LOREN, 9*3600, 9.25*3600);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("lorenupdate")
	 * @Matches("/^lorenupdate ([a-z0-9 ]+)$/i")
	 */
	public function lorenUpdateCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossUpdateCommand($sender, $args[1], static::LOREN, 9*3600, 0.25*3600);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("lorendel")
	 * @Matches("/^lorendel$/i")
	 */
	public function lorenDeleteCommand($message, $channel, $sender, $sendto, $args) {
		$msg = $this->bigBossDeleteCommand($sender, static::LOREN);
		$sendto->reply($msg);
	}

	/**
	 * Announce an event
	 *
	 * @param string $msg The nmessage to send
	 * @param int $step 1 => spawns soon, 2 => has spawned, 3 => vulnerable
	 * @return void
	 */
	protected function announceBigBossEvent($msg, $step) {
		$setting = 'bigboss_channels_spawn';
		if ($step === 1) {
			$setting = 'bigboss_channels_prespawn';
		} elseif ($step === 3) {
			$setting = 'bigboss_channels_vulnerable';
		}
		$channels = $this->settingManager->get($setting);
		if ($channels & 1) {
			$this->chatBot->sendGuild($msg, true);
		}
		if ($channels & 2) {
			$this->chatBot->sendPrivate($msg, true);
		}
		if ($channels & 4 && $this->discordController) {
			$this->discordController->sendMessage($msg);
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
				$this->announceBigBossEvent($msg, 1);
			}
			if ($timer->next_spawn <= time() && $timer->next_spawn > time()-10) {
				$msg = "<highlight>".$timer->mob_name."<end> has spawned and will be vulnerable in ".
					"<highlight>".$this->util->unixtimeToReadable($timer->next_killable-time())."<end>.";
				$this->announceBigBossEvent($msg, 2);
			}
			$nextKillTime = time() + $timer->timer+$invulnerableTime;
			if ($timer->next_killable == time() || ($timer->next_killable <= $nextKillTime && $timer->next_killable > $nextKillTime-10)) {
				$msg = "<highlight>".$timer->mob_name."<end> is no longer immortal.";
				$this->announceBigBossEvent($msg, 3);
			}
		}
	}
}
