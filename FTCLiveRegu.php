<?php

namespace FearTheC;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Bgs1InRace;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\Common\BasePlayerTimeStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use MCTeam\Common\RecordWidget;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;
use FearTheC\ReguWatcher\PlayerRuntimes;

/**
 * Live Regu
 *
 * @author Bonaventure Quentin
 */
class FTCLiveRegu implements ManialinkPageAnswerListener, CallbackListener, TimerListener, Plugin {

    const PLUGIN_ID = 208;
    const PLUGIN_VERSION = 0.1;
    const PLUGIN_NAME = 'FTCLiveRegu';
    const PLUGIN_AUTHOR = 'Quentin Bonaventure';

    // FTCLiveReguWidget Properties
    const MLID_CHAPOLIVE_WIDGET = 'FTCLiveRegu.Widget';
    const MLID_CHAPOLIVE_WIDGETTIMES = 'FTCLiveRegu.WidgetTimes';
    const SETTING_ACTIVATED = 'FTCLiveRegu-Widget Activated';
    const SETTING_POSX = 'FTCLiveRegu-Widget-Position: X';
    const SETTING_POSY = 'FTCLiveRegu-Widget-Position: Y';
    const SETTING_LINESCOUNT = 'FTCLiveRegu-Widget Displayed Lines Count';
    const SETTING_WIDTH = 'FTCLiveRegu-Widget-Size: Width';
    const SETTING_HEIGHT = 'FTCLiveRegu-Widget-Size: Height';
    const SETTING_LINE_HEIGHT = 'FTCLiveRegu-Widget-Lines: Height';

    const DB_TABLE = "ftc_regularity_watcher";
    const DB_TIMES_TABLE = "ftc_regularity_watcher_times";

    const FRAME_TITLE = "Regularity Watcher";
    const FRAME_DESCRIPTION = "";

    const ACTION_SPEC = 'Spec.Action';

    /**
     * @var ManiaControl $maniaControl
     */
    private $maniaControl = null;

    /**
     * @var \MCTeam\Common\RecordWidget $recordWidget
     */
    private $recordWidget = null;

    // $players = []
    private $players = [];

    // $ranking = array ($playerlogin => array("cp"=>$cp, "cptime" =>$cptime) )
    private $ranking = array();
    private $nbCp=0;

    /**
     * @see \ManiaControl\Plugins\Plugin::load()
     */
    public function load(ManiaControl $maniaControl) {
        $this->maniaControl = $maniaControl;
        $this->initTables();
        $this->recordWidget = new RecordWidget($this->maniaControl);

        // Callbacks
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'handlePlayerInfoChanged');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

	$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_BEGINMAP, $this, 'handleOnBeginMap');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONGIVEUP, $this, 'handlePlayerGiveUpCallback');    
        
        $this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleSpec');
        
        // Settings
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_ACTIVATED, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_POSX, -139.5);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_POSY, 40);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LINESCOUNT, 8);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_WIDTH, 42);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_HEIGHT, 40);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LINE_HEIGHT, 4);

        $this->displayWidgets();

        return true;
    }


    private function initTables()
    {
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
	$query  = "CREATE TABLE IF NOT EXISTS `" . self::DB_TABLE . "` (
				`index` int(11) NOT NULL AUTO_INCREMENT,
				`mapIndex` int(11) NOT NULL,
				`playerIndex` int(11) NOT NULL,
                                `current_cursor` int(4) NOT NULL DEFAULT 0,
				PRIMARY KEY (`index`),
				UNIQUE KEY `player_map_regu` (`mapIndex`,`playerIndex`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		$mysqli->query($query);

	if ($mysqli->error) {
		trigger_error($mysqli->error, E_USER_ERROR);
	}
		
        $query = "CREATE TABLE IF NOT EXISTS `".self::DB_TIMES_TABLE. "` (
                     `cursor_nb` int(4) NOT NULL,
                     `mapIndex` int(11) NOT NULL,
                     `playerIndex` int(11) NOT NULL,
                     `time` int(11),
                  UNIQUE KEY `player_map_regu_times` (`mapIndex`,`playerIndex`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
        $mysqli->query($query);

         if ($mysqli->error) {
		trigger_error($mysqli->error, E_USER_ERROR);
	}
    }



    /**
     * Display the Widget : only the title
     */
    private function displayWidgets() {
        if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ACTIVATED)) {
            $this->displayChapoLiveWidget();
        }
    }



     public function handleOnBeginMap() {
            $this->displayWidgets();
    }



    /**
     * Displays Widget : ony the title and CPs count
     *
     * @param bool $login
     */
    public function displayChapoLiveWidget($login = false) {
        $posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_POSX);
        $posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_POSY);
        $width = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_WIDTH);
        $height = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_HEIGHT);
        $labelStyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
        $quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
        $quadStyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();

        //cptotal
        $this->nbCp = 0;
        $mapdetails = $this->maniaControl->getClient()->getCurrentMapInfo();
        // Gamemodes: Script == 0, Rounds == 1, TA == 2, Team == 3, Laps == 4, Cup == 5, Stunts == 6, Default: unknown
        $gamemode = $this->maniaControl->getServer()->getGameMode();
        $forcedlaps = $this->maniaControl->getClient()->getCurrentGameInfo()->roundsForcedLaps;
        if ($gamemode == 1 || $gamemode == 3 || $gamemode == 5) {
            if ($forcedlaps > 0) {
                $this->nbCp = $mapdetails->nbCheckpoints * $forcedlaps;
            } else if ($mapdetails->nbLaps > 0) {
                $this->nbCp = $mapdetails->nbCheckpoints * $mapdetails->nbLaps;
            } else {
                //All other game modes
                $this->nbCp = $mapdetails->nbCheckpoints;
            }
        } else if ($mapdetails->nbLaps > 0 && $gamemode == 4) {
            $this->nbCp = $mapdetails->nbCheckpoints * $mapdetails->nbLaps;
        } else {
            $this->nbCp = $mapdetails->nbCheckpoints;
        }

        $maniaLink = new ManiaLink(self::MLID_CHAPOLIVE_WIDGET);

        // mainframe
        $frame = new Frame();
        $maniaLink->addChild($frame);
        $frame->setSize($width, $height);
        $frame->setPosition($posX, $posY);

        // Background Quad
        $backgroundQuad = new Quad();
        $frame->addChild($backgroundQuad);
        $backgroundQuad->setSize($width, $height);
        $backgroundQuad->setStyles($quadStyle, $quadSubstyle);

        $titleLabel = new Label();
        $frame->addChild($titleLabel);
        $titleLabel->setPosition(0, 17);
        $titleLabel->setWidth($width);
        $titleLabel->setStyle($labelStyle);
        $titleLabel->setTextSize(1.5);
        $titleLabel->setText(self::FRAME_TITLE);

        // Description
        $ti = new Label();
        $frame->addChild($ti);
        $ti->setPosition(0, 14);
        $ti->setWidth($width);
        $ti->setTextSize(0.5);
        $ti->setZ(1);
        $ti->setText(self::FRAME_DESCRIPTION);
        
        // Send manialink
        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
    }



    
    public function setRunTime($loginName,$runtime) {
        
        $this->ranking[$loginName] = array("cp" => $cp, "cptime" => $cptime);
    }


    
    /**
     * Displays Times Widget : Cp player Time
     */
    public function displayTimes() {

        $lines = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LINESCOUNT);
        $lineHeight = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LINE_HEIGHT);
        $posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_POSX);
        $posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_POSY);
        $width = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_WIDTH);


        $maniaLink = new ManiaLink(self::MLID_CHAPOLIVE_WIDGETTIMES);
        $frame = new Frame();
        $maniaLink->addChild($frame);
        $frame->setPosition($posX, $posY);


        // Obtain a list of columns
        $nbCPs = array();
        $CPTime = array();
        foreach ($this->ranking as $key => $row) {
            $nbCPs[$key] = $row['cp'];
            $CPTime[$key] = $row['cptime'];
        }

        // Sort the data with nbCPs descending, CPTime ascending
        array_multisort($nbCPs, SORT_DESC, $CPTime, SORT_ASC, $this->ranking);


        $cpt = 0;
        foreach ($this->ranking as $loginName => $record) {
            $cpt++;
            if ($cpt >= $lines) {
                break;
            }

            $time = Formatter::formatTime($record['cptime']);

            $player = $this->maniaControl->getPlayerManager()->getPlayer($loginName);

            $y = - $cpt * $lineHeight;

            $recordFrame = new Frame();
            $frame->addChild($recordFrame);
            $recordFrame->setPosition(0, $y + 13);
            
            //Image
            $quadCP = new Quad_Icons64x64_1();
            $recordFrame->addChild($quadCP);
            $quadCP->setX($width * -0.47 + 0.2);

            //Rank
            $rankLabel = new Label_Text();
            $recordFrame->addChild($rankLabel);
            $rankLabel->setHorizontalAlign($rankLabel::LEFT);
            $rankLabel->setX($width * -0.47);
            //$rankLabel->setSize($width * 0.06, $lineHeight);
            $rankLabel->setSize($width, $lineHeight);
            $rankLabel->setTextSize(1);
            //$rankLabel->setTextPrefix('$o');
            //$label->setUrl();
            
            if ($this->nbCp==$record['cp']) {
                $rankLabel->setText('');
                $quadCP->setSubStyle($quadCP::SUBSTYLE_Finish);
                $quadCP->setSize(5, 5);
            } else {
                $rankLabel->setText(' $F93' . $record['cp']);
                $quadCP->setSubStyle($quadCP::SUBSTYLE_ArrowBlue);
                $quadCP->setSize(2, 5);
            }
            
            $rankLabel->setTextEmboss(true);

            //Name
            $nameLabel = new Label_Text();
            $recordFrame->addChild($nameLabel);
            $nameLabel->setHorizontalAlign($nameLabel::LEFT);
            $nameLabel->setX($width * -0.4);
            $nameLabel->setSize($width * 0.6, $lineHeight);
            $nameLabel->setTextSize(1);
            $nameLabel->setText('   ' . $player->nickname);
            $nameLabel->setTextEmboss(true);

            //Time
            $timeLabel = new Label_Text();
            $recordFrame->addChild($timeLabel);
            $timeLabel->setHorizontalAlign($timeLabel::RIGHT);
            $timeLabel->setX($width * 0.47);
            $timeLabel->setSize($width * 0.25, $lineHeight);
            $timeLabel->setTextSize(1);
            $timeLabel->setText($time);
            $timeLabel->setTextEmboss(true);
            
            //Quad with Spec action
            $quad = new Quad();
            $recordFrame->addChild($quad);
            $quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCardList);
            $quad->setSize($width, $lineHeight);
            $quad->setAction(self::ACTION_SPEC . '.' . $player->login);
        }

        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
    }


    private function handlePlayerConnet(Player $player)
    {
        $playerTimes = $this->fetchPlayerTimes($player);
    }


    private function fetchPlayerTimes(Player $player)
    {
        $map = $this->maniaControl->getMapManager()->getCurrentMap();
        $mysqli = $this->maniaControl->getDatabase()->getMysqli();
        
        $query = "SELECT * FROM `".self::DB_TABLE."` 
                LEFT JOIN ftc_regularity_watcher_times times ON times.mapIndex = {$map->index} AND times.playerIndex = {$player->index}
                WHERE mapIndex = {$map->index}";

	$result = $mysqli->query($query);
        $records = $mysqli->fetch_assoc($query);
        
        if ($mysqli->error) {
            trigger_error("Couldn't retrieve player record for '{$player->login}'." . $mysqli->error);
            return null;
        }
        
        $result->free();

        if (empty($records)) {
            $query = "INSERT INTO `".self::DB_TABLE."` (mapIndex, playerIndex) VALUES ({$map->index}, {$player->index});";
            $mysqli->query($query);

        }

        return $records;

    }

    public function handlePlayerGiveUpCallback(BasePlayerTimeStructure $structure) {
            $this->displayTimes();
    }

    
    
    public function handleFinishCallback(OnWayPointEventStructure $structure) {
//        $this->setRunTime($structure->getLogin(),$structure->getRaceTime());

        $this->saveRuntime($structure);
	
        $this->displayTimes();
    }


    public function saveRuntime(OnWayPointEventStructure $structure)
    {
        
    }


    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload() {

        $this->closeWidget(self::MLID_CHAPOLIVE_WIDGET);
        $this->closeWidget(self::MLID_CHAPOLIVE_WIDGETTIMES);
    }



    /**
     * Handle PlayerConnect callback
     *
     * @param Player $player
     */
    public function handlePlayerConnect(Player $player) {
        if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ACTIVATED)) {
            $this->displayChapoLiveWidget($player->login);
            $this->displayTimes();
        }
    }



    /**
     * Handle PlayerDisconnect callback
     *
     * @param Player $player
     */
    public function handlePlayerDisconnect(Player $player) {
        unset($this->ranking[$player->login]);
        $this->displayTimes();
    }

    public function handlePlayerInfoChanged(Player $player) {
        unset($this->ranking[$player->login]);
//        if (!$player->isSpectator)
  //          $this->setOneRanking($player->login, 0, 0);
        $this->displayTimes();
    }

    public function handleSpec(array $callback) {
        $actionId    = $callback[1][2];
        $actionArray = explode('.', $actionId, 3);
        if(count($actionArray) < 2){
            return;
        }
        $action      = $actionArray[0] . '.' . $actionArray[1];

        if (count($actionArray) > 2) {

            switch ($action) {
                case self::ACTION_SPEC:
                    $adminLogin = $callback[1][1];
                    $targetLogin = $actionArray[2];
                    $player = $this->maniaControl->getPlayerManager()->getPlayer($adminLogin);
                    if ($player->isSpectator) {
                        $this->maniaControl->getClient()->forceSpectatorTarget($adminLogin, $targetLogin, -1);
                    } else {
                        $this->maniaControl->getClient()->forceSpectator($adminLogin,3);
                    }
            }
        }
    }


    
    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting) {
        if ($setting->belongsToClass($this)) {
            $this->displayWidgets();
        }
    }



    /**
     * @see \ManiaControl\Plugins\Plugin::getId()
     */
    public static function getId() {
        return self::PLUGIN_ID;
    }



    /**
     * @see \ManiaControl\Plugins\Plugin::getName()
     */
    public static function getName() {
        return self::PLUGIN_NAME;
    }



    /**
     * @see \ManiaControl\Plugins\Plugin::getVersion()
     */
    public static function getVersion() {
        return self::PLUGIN_VERSION;
    }


    /**
     * @see \ManiaControl\Plugins\Plugin::getAuthor()
     */
    public static function getAuthor() {
        return self::PLUGIN_AUTHOR;
    }



    /**
     * @see \ManiaControl\Plugins\Plugin::getDescription()
     */
    public static function getDescription() {
        return self::FRAME_DESCRIPTION;
    }



    public static function prepare(ManiaControl $maniaControl) {
        
    }


    
    /**
     * Close a Widget
     *
     * @param string $widgetId
     */
    public function closeWidget($widgetId) {
        $this->maniaControl->getManialinkManager()->hideManialink($widgetId);
    }


  public function getAvgRegu($playerId)
  {
    $rtCount = 0;
    $sum = 0;
    $failures = 0;
    foreach ($this->runtimes[$playerId] as $cursor => $runtime) {
      if ($runtime >= 0) {
        $sum += $runtime;
      } else {
        $failures++;
      }
      $rtCount++;
    }

    $avg = $sum / ($rtCount - $failures);
    $failuresRate = ($failures / $rtCount);

    return [$rtCount, $avg, $failuresRate];
  }


}
