<?php

namespace ManiaControl\Configurators;

use FML\Controls\Control;
use FML\Controls\Entry;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Script\Script;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;

/**
 * Class offering a Configurator for Server Settings
 *
 * @author steeffeen & kremsy
 */
class ServerSettings implements ConfiguratorMenu, CallbackListener {
	/**
	 * Constants
	 */
	const TITLE                    = 'Server Settings';
	const ACTION_PREFIX_SETTING    = 'ServerSettings.';
	const ACTION_SETTING_BOOL      = 'ServerSettings.ActionBoolSetting.';
	const CB_SERVERSETTING_CHANGED = 'ServerSettings.SettingChanged';

	/**
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Create a new Server Settings Instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Register for callbacks
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');
	}

	/**
	 *
	 * @see \ManiaControl\Configurators\ConfiguratorMenu::getTitle()
	 */
	public function getTitle() {
		return self::TITLE;
	}

	/**
	 *
	 * @see \ManiaControl\Configurators\ConfiguratorMenu::getMenu()
	 */
	public function getMenu($width, $height, Script $script) {
		$pagesId = 'ServerSettingsPages';
		$frame   = new Frame();

		$this->maniaControl->client->query('GetServerOptions');
		$serverSettings = $this->maniaControl->client->getResponse();

		// Config
		$pagerSize     = 9.;
		$settingHeight = 5.;
		$labelTextSize = 2;
		$pageMaxCount  = 13;

		// Pagers
		$pagerPrev = new Quad_Icons64x64_1();
		$frame->add($pagerPrev);
		$pagerPrev->setPosition($width * 0.39, $height * -0.44, 2);
		$pagerPrev->setSize($pagerSize, $pagerSize);
		$pagerPrev->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_ArrowPrev);

		$pagerNext = new Quad_Icons64x64_1();
		$frame->add($pagerNext);
		$pagerNext->setPosition($width * 0.45, $height * -0.44, 2);
		$pagerNext->setSize($pagerSize, $pagerSize);
		$pagerNext->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_ArrowNext);

		$script->addPager($pagerPrev, -1, $pagesId);
		$script->addPager($pagerNext, 1, $pagesId);

		$pageCountLabel = new Label();
		$frame->add($pageCountLabel);
		$pageCountLabel->setHAlign(Control::RIGHT);
		$pageCountLabel->setPosition($width * 0.35, $height * -0.44, 1);
		$pageCountLabel->setStyle('TextTitle1');
		$pageCountLabel->setTextSize(2);

		$script->addPageLabel($pageCountLabel, $pagesId);


		// Setting pages
		$pageFrames = array();
		$y          = 0.;
		$id         = 0;
		foreach($serverSettings as $name => $value) {
			//TODO Comment 4 lines maybe
			//Continue on CurrentMaxPlayers...
			$pos = strpos($name, "Current"); //TODO maybe current irgentwo anzeigen
			if($pos !== false) {
				continue;
			}

			if(!isset($pageFrame)) {
				$pageFrame = new Frame();
				$frame->add($pageFrame);
				if(!empty($pageFrames)) {
					$pageFrame->setVisible(false);
				}
				array_push($pageFrames, $pageFrame);
				$y = $height * 0.41;
				$script->addPage($pageFrame, count($pageFrames), $pagesId);
			}

			$settingFrame = new Frame();
			$pageFrame->add($settingFrame);
			$settingFrame->setY($y);

			$nameLabel = new Label_Text();
			$settingFrame->add($nameLabel);
			$nameLabel->setHAlign(Control::LEFT);
			$nameLabel->setX($width * -0.46);
			$nameLabel->setSize($width * 0.4, $settingHeight);
			$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
			$nameLabel->setTextSize($labelTextSize);
			$nameLabel->setText($name);
			$nameLabel->setTextColor("FFF");

			//$settingValue = $scriptSettings[$name];

			$substyle = '';
			if($value === false) {
				$substyle = Quad_Icons64x64_1::SUBSTYLE_LvlRed;
			} else if($value === true) {
				$substyle = Quad_Icons64x64_1::SUBSTYLE_LvlGreen;
			}

			$entry = new Entry();
			$settingFrame->add($entry);
			$entry->setStyle(Label_Text::STYLE_TextValueSmall);
			$entry->setHAlign(Control::CENTER);
			$entry->setX($width / 2 * 0.46);
			$entry->setTextSize(1);
			$entry->setSize($width * 0.48, $settingHeight * 0.9);
			$entry->setName(self::ACTION_PREFIX_SETTING . $name);
			$entry->setDefault($value);


			if($substyle != '') {
				$quad = new Quad_Icons64x64_1();
				$settingFrame->add($quad);
				$quad->setX($width / 2 * 0.46);
				$quad->setZ(-0.01);
				$quad->setSubStyle($substyle);
				$quad->setSize(4, 4);
				$quad->setHAlign(Control::CENTER);
				$quad->setAction(self::ACTION_SETTING_BOOL . $name);

				$entry->setVisible(false);
			}


			$y -= $settingHeight;
			if($id % $pageMaxCount == $pageMaxCount - 1) {
				unset($pageFrame);
			}

			$id++;
		}

		return $frame;
	}

	/**
	 *
	 * @see \ManiaControl\Configurators\ConfiguratorMenu::saveConfigData()
	 */
	public function saveConfigData(array $configData, Player $player) {
		//Note on ServerOptions the whole Options have to be saved, otherwise a error will appear

		$this->maniaControl->client->query('GetServerOptions');
		$serverSettings = $this->maniaControl->client->getResponse();

		$prefixLength = strlen(self::ACTION_PREFIX_SETTING);


		$actionArray = explode(".", $configData[2]);

		$boolSettingName = '';
		if(isset($actionArray[2])) {
			$boolSettingName = self::ACTION_PREFIX_SETTING . $actionArray[2];
		}

		//var_dump($configData[3]);
		$newSettings = array();
		foreach($configData[3] as $setting) {
			if(substr($setting['Name'], 0, $prefixLength) != self::ACTION_PREFIX_SETTING) {
				continue;
			}

			//Check if it was a boolbox
			//	var_dump($setting['Name'], $boolSettingName, "");
			if($setting['Name'] == $boolSettingName) {
				$setting['Value'] = ($setting['Value'] ? false : true);
				var_dump($setting['Value']);
				var_dump("test13");
			}

			$settingName = substr($setting['Name'], $prefixLength);

			$newSettings[$settingName] = $setting['Value'];
			settype($newSettings[$settingName], gettype($serverSettings[$settingName]));
		}

		$this->applyNewScriptSettings($newSettings, $player);
	}

	/**
	 * Handle ManialinkPageAnswer Callback
	 *
	 * @param array $callback
	 */
	public function handleManialinkPageAnswer(array $callback) {
		$actionId    = $callback[1][2];
		$boolSetting = (strpos($actionId, self::ACTION_SETTING_BOOL) === 0);
		if(!$boolSetting) {
			return;
		}

		$actionArray = explode(".", $actionId);
		$setting     = $actionArray[2];

		$login  = $callback[1][1];
		$player = $this->maniaControl->playerManager->getPlayer($login);


		//Save all Changes
		$this->saveConfigData($callback[1], $player);
		//$this->toggleBooleanSetting($setting, $player);
	}

	/**
	 * Toogle a Boolean Setting
	 *
	 * @param Player $player
	 * @param        $setting
	 */
	public function toggleBooleanSetting($setting, Player $player) {
		$this->maniaControl->client->query('GetServerOptions');
		$serverSettings = $this->maniaControl->client->getResponse();

		if(!isset($serverSettings[$setting])) {
			var_dump('no setting ' . $setting);
			return;
		}

		$newSettings           = array();
		$newSettings[$setting] = ($serverSettings[$setting] ? false : true);

		var_dump($newSettings);
		$this->applyNewScriptSettings($newSettings, $player);

	}

	/**
	 * Apply the Array of new Script Settings
	 *
	 * @param array  $newSettings
	 * @param Player $player
	 */
	private function applyNewScriptSettings(array $newSettings, Player $player) {
		if(!$newSettings) {
			return;
		}
		$success = $this->maniaControl->client->query('SetServerOptions', $newSettings);
		if(!$success) {
			$this->maniaControl->chat->sendError('Error occurred: ' . $this->maniaControl->getClientErrorText(), $player->login);
			return;
		}

		//var_dump($newSettings);
		// Notifications
		$settingsCount = count($newSettings);
		$settingIndex  = 0;
		$title         = $this->maniaControl->authenticationManager->getAuthLevelName($player->authLevel);
		//$chatMessage = '$ff0' . $title . ' $<' . $player->nickname . '$> set ScriptSetting' . ($settingsCount > 1 ? 's' : '') . ' ';
		foreach($newSettings as $setting => $value) {


			//$chatMessage .= '$<' . '$fff' . preg_replace('/^S_/', '', $setting) . '$z$s$ff0 ';
			//$chatMessage .= 'to $fff' . $this->parseSettingValue($value) . '$>';

			/*if ($settingIndex <= $settingsCount - 2) {
				$chatMessage .= ', ';
			}*/

			// Trigger own callback
			$this->maniaControl->callbackManager->triggerCallback(self::CB_SERVERSETTING_CHANGED, array(self::CB_SERVERSETTING_CHANGED, $setting, $value));

			$settingIndex++;
		}

		//	$chatMessage .= '!';
		//	$this->maniaControl->chat->sendInformation($chatMessage);
		//$this->maniaControl->log(Formatter::stripCodes($chatMessage));

		//TODO reopen menu
	}

	/**
	 * Parse the Setting Value to a String Representation
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function parseSettingValue($value) {
		if(is_bool($value)) {
			return ($value ? 'True' : 'False');
		}
		return (string)$value;
	}
}
