<?php

namespace FearTheC\MCReguWatcher;

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
use FearTheC\MCReguWatcher\PlayerRuntimes;
use FearTheC\MCReguWatcher\RuntimesRepository;

/**
 * Live Regu
 *
 * @author Bonaventure Quentin
 */
class ReguWatcherPlugin implements ManialinkPageAnswerListener, CallbackListener, TimerListener, Plugin {

  const PLUGIN_ID = 208;
  const PLUGIN_VERSION = 0.1;
  const PLUGIN_NAME = 'FTCReguWatcher';
  const PLUGIN_AUTHOR = 'Quentin Bonaventure';

  // FTCReguWatcherWidget Properties
  const MLID_CHAPOLIVE_WIDGET = 'FTCReguWatcher.Widget';
  const MLID_CHAPOLIVE_WIDGETTIMES = 'FTCReguWatcher.WidgetTimes';
  const SETTING_ACTIVATED = 'FTCReguWatcher-Widget Activated';
  const SETTING_POSX = 'FTCReguWatcher-Widget-Position: X';
  const SETTING_POSY = 'FTCReguWatcher-Widget-Position: Y';
  const SETTING_LINESCOUNT = 'FTCReguWatcher-Widget Displayed Lines Count';
  const SETTING_WIDTH = 'FTCReguWatcher-Widget-Size: Width';
  const SETTING_HEIGHT = 'FTCReguWatcher-Widget-Size: Height';
  const SETTING_LINE_HEIGHT = 'FTCReguWatcher-Widget-Lines: Height';

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

  /**
   * RuntimesRepository $repository
   */
  private $repository;



  /**
   * @see \ManiaControl\Plugins\Plugin::load()
   */
  public function load(ManiaControl $maniaControl) {
    $this->maniaControl = $maniaControl;

    $this->repository = new RuntimesRepository($mysqli = $this->maniaControl->getDatabase()->getMysqli());
    $this->recordWidget = new RecordWidget($this->maniaControl);

    $players = $this->maniaControl->getPlayerManager()->getPlayers();
    foreach ($players as $player)
    {
      $playerRuntimes = $this->repository->initPlayer($player, $this->getCurrentMap());
      $this->players[$playerRuntimes->getPlayerId()] = $playerRuntimes;
    }

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


  /**
   * @see \ManiaControl\Plugins\Plugin::unload()
   */
  public function unload() {
      $this->closeWidget(self::MLID_CHAPOLIVE_WIDGET);
      $this->closeWidget(self::MLID_CHAPOLIVE_WIDGETTIMES);
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



  public function handlePlayerInfoChanged(Player $player) {
      unset($this->ranking[$player->login]);
//        if (!$player->isSpectator)
//          $this->setOneRanking($player->login, 0, 0);
      $this->displayTimes();
  }



  public function handlePlayerConnect(Player $player)
  {
    $playerRuntimes = $this->repository->initPlayer($player, $this->getCurrentMap());
    $this->players[$playerRuntimes->getPlayerId()] = $playerRuntimes;
          $this->displayTimes();
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



  public function handlePlayerGiveUpCallback(BasePlayerTimeStructure $structure) {
    $player = $this->players[$structure->getPlayer()->index];
    $currentCursor = $player->addRuntime(-1);

    $this->repository->saveRuntime($structure->getPlayer(), $this->getCurrentMap(), -1, $currentCursor);

    $this->displayTimes();
  }



  public function handleFinishCallback(OnWayPointEventStructure $structure) {
    $player = $this->players[$structure->getPlayer()->index];
    $currentCursor = $player->addRuntime($structure->getRaceTime());

    $this->repository->saveRuntime($structure->getPlayer(), $this->getCurrentMap(), $structure->getRaceTime(), $currentCursor);

    $this->displayTimes();
  }


  public function saveRuntime(Player $player, Map $map, $time) {
    // $this->repository->saveRuntime($player, $map, $time);
  }

  private function getCurrentMap()
  {
    return $this->maniaControl->getMapManager()->getCurrentMap();
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


      $i = 0;
      foreach ($this->players as $player) {
        $playerId = $player->getPlayerId();
        $i++;
        $time = Formatter::formatTime("55482");

        $y = -$i*$lineHeight;

        $playerReguFrame = new Frame();
        $frame->addChild($playerReguFrame);
        $playerReguFrame->setPosition(0, $y+13);

        //Name
        $nameLabel = new Label_Text();
        $playerReguFrame->addChild($nameLabel);
        $nameLabel->setHorizontalAlign($nameLabel::LEFT);
        $nameLabel->setX($width * -0.4);
        $nameLabel->setSize($width * 0.6, $lineHeight);
        $nameLabel->setTextSize(1);
        var_dump($playerId);
        $nameLabel->setText('   ' . $playerId);
        $nameLabel->setTextEmboss(true);
      }


      $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, false);
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




}
