<?php

namespace ManiaControl\Server;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\CommandLineHelper;
use ManiaControl\ManiaControl;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

/**
 * Class providing Access to the connected ManiaPlanet Server
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class Server implements CallbackListener {
	/*
	 * Constants
	 */
	const TABLE_SERVERS = 'mc_servers';

	/*
	 * Public Properties
	 */
	/** @var Config $config */
	public $config = null;
	public $index = -1;
	public $ip = null;
	public $port = -1;
	public $p2pPort = -1;
	public $login = null;
	public $titleId = null;
	public $dataDirectory = '';
	public $serverCommands = null;
	public $usageReporter = null;
	public $rankingManager = null;
	public $scriptManager = null;
	public $matchSettingsManager = null;

	/*
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Construct a new Server
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initTables();

		$this->serverCommands       = new ServerCommands($maniaControl);
		$this->usageReporter        = new UsageReporter($maniaControl);
		$this->rankingManager       = new RankingManager($maniaControl);
		$this->scriptManager        = new ScriptManager($maniaControl);
		$this->matchSettingsManager = new MatchSettingsManager($maniaControl);

		// Register for callbacks
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_ONINIT, $this, 'onInit');
	}

	/**
	 * Initialize necessary Database Tables
	 *
	 * @return bool
	 */
	private function initTables() {
		$mysqli    = $this->maniaControl->database->mysqli;
		$query     = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_SERVERS . "` (
				`index` int(11) NOT NULL AUTO_INCREMENT,
				`login` varchar(100) NOT NULL,
				PRIMARY KEY (`index`),
				UNIQUE KEY `login` (`login`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Servers' AUTO_INCREMENT=1;";
		$statement = $mysqli->prepare($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
			return false;
		}
		$statement->execute();
		if ($statement->error) {
			trigger_error($statement->error, E_USER_ERROR);
			return false;
		}
		$statement->close();
		return true;
	}

	/**
	 * Load the Server Configuration from the Config XML
	 */
	public function loadConfig() {
		// Server id parameter
		$serverId = CommandLineHelper::getParameter('-id');

		// Xml server tag with given id
		$serverTag = null;
		if ($serverId) {
			$serverTags = $this->maniaControl->config->xpath("server[@id='{$serverId}']");
			if ($serverTags) {
				$serverTag = $serverTags[0];
			}
			if (!$serverTag) {
				trigger_error("No Server configured with the ID '{$serverId}'!", E_USER_ERROR);
			}
		} else {
			$serverTags = $this->maniaControl->config->xpath('server');
			if ($serverTags) {
				$serverTag = $serverTags[0];
			}
			if (!$serverTag) {
				trigger_error('No Server configured!', E_USER_ERROR);
			}
		}

		// Host
		$host = $serverTag->xpath('host');
		if ($host) {
			$host = (string)$host[0];
		}
		if (!$host) {
			trigger_error("Invalid server configuration (host).", E_USER_ERROR);
		}

		// Port
		$port = $serverTag->xpath('port');
		if ($port) {
			$port = (string)$port[0];
		}
		if (!$port) {
			trigger_error("Invalid server configuration (port).", E_USER_ERROR);
		}

		// Login
		$login = $serverTag->xpath('login');
		if ($login) {
			$login = (string)$login[0];
		}
		if (!$login) {
			trigger_error("Invalid server configuration (login).", E_USER_ERROR);
		}

		// Password
		$pass = $serverTag->xpath('pass');
		if ($pass) {
			$pass = (string)$pass[0];
		}
		if (!$pass) {
			trigger_error("Invalid server configuration (password).", E_USER_ERROR);
		}

		// Create config object
		$this->config = new Config($serverId, $host, $port, $login, $pass);
	}

	/**
	 * Gets all Servers from the Database
	 *
	 * @return array
	 */
	public function getAllServers() {
		$mysqli = $this->maniaControl->database->mysqli;
		$query  = "SELECT * FROM `" . self::TABLE_SERVERS . "`";
		$result = $mysqli->query($query);
		if (!$result) {
			trigger_error($mysqli->error);
			return array();
		}

		$servers = array();
		while ($row = $result->fetch_object()) {
			array_push($servers, $row);
		}
		$result->close();

		return $servers;
	}

	/**
	 * Handle OnInit Callback
	 */
	public function onInit() {
		$this->updateProperties();
	}

	/**
	 * Refetch the Server Properties
	 */
	private function updateProperties() {
		// System info
		$systemInfo    = $this->maniaControl->client->getSystemInfo();
		$this->ip      = $systemInfo->publishedIp;
		$this->port    = $systemInfo->port;
		$this->p2pPort = $systemInfo->p2PPort;
		$this->login   = $systemInfo->serverLogin;
		$this->titleId = $systemInfo->titleId;

		// Database index
		$mysqli    = $this->maniaControl->database->mysqli;
		$query     = "INSERT INTO `" . self::TABLE_SERVERS . "` (
				`login`
				) VALUES (
				?
				) ON DUPLICATE KEY UPDATE
				`index` = LAST_INSERT_ID(`index`);";
		$statement = $mysqli->prepare($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return;
		}
		$statement->bind_param('s', $this->login);
		$statement->execute();
		if ($statement->error) {
			trigger_error($statement->error);
			$statement->close();
			return;
		}
		$this->index = $statement->insert_id;
		$statement->close();
	}

	/**
	 * Fetch Maps Directory
	 *
	 * @return string
	 */
	public function getMapsDirectory() {
		$dataDirectory = $this->getDataDirectory();
		if (!$dataDirectory) {
			return null;
		}
		return "{$dataDirectory}Maps" . DIRECTORY_SEPARATOR;
	}

	/**
	 * Fetch Game Data Directory
	 *
	 * @return string
	 */
	public function getDataDirectory() {
		if ($this->dataDirectory == '') {
			$this->dataDirectory = $this->maniaControl->client->gameDataDirectory();
		}
		return $this->dataDirectory;
	}

	/**
	 * Get Server Player Info
	 *
	 * @return \Maniaplanet\DedicatedServer\Structures\PlayerDetailedInfo
	 */
	public function getInfo() {
		return $this->maniaControl->client->getDetailedPlayerInfo($this->login);
	}

	/**
	 * Retrieve Validation Replay for the given Player
	 *
	 * @param $login
	 * @return string
	 */
	public function getValidationReplay($login) {
		try {
			$replay = $this->maniaControl->client->getValidationReplay($login);
		} catch (Exception $e) {
			// TODO temp added 19.04.2014
			$this->maniaControl->errorHandler->triggerDebugNotice("Exception line 330 Server.php" . $e->getMessage());

			trigger_error("Couldn't get validation replay of '{$login}'. " . $e->getMessage());
			return null;
		}
		return $replay;
	}

	/**
	 * Retrieve Ghost Replay for the given Player
	 *
	 * @param $login
	 * @return string
	 */
	public function getGhostReplay($login) {
		$dataDir = $this->getDataDirectory();
		if (!$this->checkAccess($dataDir)) {
			return null;
		}

		// Build file name
		$map      = $this->maniaControl->mapManager->getCurrentMap();
		$gameMode = $this->matchSettingsManager->getGameMode();
		$time     = time();
		$fileName = "GhostReplays/Ghost.{$login}.{$gameMode}.{$time}.{$map->uid}.Replay.Gbx";

		// Save ghost replay
		try {
			$this->maniaControl->client->saveBestGhostsReplay($login, $fileName);
		} catch (Exception $e) {
			// TODO temp added 19.04.2014
			$this->maniaControl->errorHandler->triggerDebugNotice("Exception line 360 Server.php" . $e->getMessage());

			trigger_error("Couldn't save ghost replay. " . $e->getMessage());
			return null;
		}

		// Load replay file
		$ghostReplay = file_get_contents("{$dataDir}Replays/{$fileName}");
		if (!$ghostReplay) {
			trigger_error("Couldn't retrieve saved ghost replay.");
			return null;
		}
		return $ghostReplay;
	}

	/**
	 * Checks if ManiaControl has Access to the given Directory
	 *
	 * @param string $directory
	 * @return bool
	 */
	public function checkAccess($directory) {
		if (!$directory) {
			return false;
		}
		return (is_dir($directory) && is_writable($directory));
	}

	/**
	 * Wait for the Server to have the given Status
	 *
	 * @param int $statusCode
	 * @return bool
	 */
	public function waitForStatus($statusCode = 4) {
		$response = $this->maniaControl->client->getStatus();
		// Check if server has the given status
		if ($response->code === 4) {
			return true;
		}
		// Server not yet in given status - Wait for it...
		$waitBegin   = time();
		$maxWaitTime = 50;
		$lastStatus  = $response->name;
		$this->maniaControl->log("Waiting for server to reach status {$statusCode}...");
		$this->maniaControl->log("Current Status: {$lastStatus}");
		while ($response->code !== 4) {
			sleep(1);
			$response = $this->maniaControl->client->getStatus();
			if ($lastStatus !== $response->name) {
				$this->maniaControl->log("New Status: {$response->name}");
				$lastStatus = $response->name;
			}
			if (time() - $maxWaitTime > $waitBegin) {
				// It took too long to reach the status
				trigger_error("Server couldn't reach status {$statusCode} after {$maxWaitTime} seconds! ");
				return false;
			}
		}
		return true;
	}


	/**
	 * Set whether the Server Runs a Team-Based Mode or not
	 *
	 * @deprecated Use MatchSettingsManager instead
	 * @param bool $teamMode
	 */
	public function setTeamMode($teamMode = true) {
		$this->matchSettingsManager->setTeamMode($teamMode);
	}

	/**
	 * Check if the Server Runs a Team-Based Mode
	 *
	 * @deprecated Use MatchSettingsManager instead
	 * @return bool
	 */
	public function isTeamMode() {
		$this->matchSettingsManager->isTeamMode();
	}


	/**
	 * Fetch the current Game Mode
	 *
	 * @deprecated Use MatchSettingsManager instead
	 * @param bool $stringValue
	 * @param int  $parseValue
	 * @return int | string
	 */
	public function getGameMode($stringValue = false, $parseValue = null) {
		$this->matchSettingsManager->getGameMode($stringValue, $parseValue);
	}
}
