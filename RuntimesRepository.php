<?php

namespace FearTheC\ReguWatcher;

use FearTheC\ReguWatcher\PlayerRuntimesCollection;
use ManiaControl\Players\Player;
use ManiaControl\Maps\Map;



class RuntimesRepository
{

  const DB_TABLE = "ftc_regularity_watcher";
  const DB_TIMES_TABLE = "ftc_regularity_watcher_times";

  const INIT_PLAYER_QUERY = <<<'EOT'
CREATE TABLE IF NOT EXISTS `%s` (
    `mapIndex` int(11) NOT NULL,
    `playerIndex` int(11) NOT NULL,
    `current_cursor` int(4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`mapIndex`,`playerIndex`)
  )
  ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1
EOT;

  const INIT_RUNTIMES_QUERY = <<<'EOT'
CREATE TABLE IF NOT EXISTS `%s` (
     `cursor_nb` int(4) NOT NULL,
     `mapIndex` int(11) NOT NULL,
     `playerIndex` int(11) NOT NULL,
     `time` int(11),
     UNIQUE KEY `player_map_regu_times` (`mapIndex`,`playerIndex`)
  )
  ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1
EOT;

  const INIT_PLAYER = <<<'EOT'
INSERT IGNORE INTO `%s` (mapIndex, playerIndex)
VALUES (%d, %d);
EOT;

  const SELECT_PLAYER = <<<'EOT'
SELECT * FROM `%s`
LEFT JOIN ftc_regularity_watcher_times times ON times.mapIndex = mapIndex AND times.playerIndex = playerIndex
WHERE playerIndex = %d AND mapIndex = %d
EOT;


  /**
   * @param \mysqli $mysqliClient
   */
  private $mysqliClient;

  public function __construct(\mysqli $mysqliClient)
  {
    $this->mysqliClient = $mysqliClient;
    $this->initTables();
  }


  private function initTables()
  {
    $this->query(sprintf(self::INIT_PLAYER_QUERY, self::DB_TABLE));
    $this->query(sprintf(self::INIT_RUNTIMES_QUERY, self::DB_TIMES_TABLE));
  }


  public function initPlayer(Player $player, Map $map)
  {
    $player = $this->selectPlayer($player, $map);
    var_dump($player);

    if (!$player) {
      $this->query(sprintf(self::INIT_PLAYER, self::DB_TABLE, $map->index, $player->index));
    }


  }


  public function selectPlayer(Player $player, Map $map)
  {
    $result = $this->query(sprintf(self::SELECT_PLAYER, self::DB_TABLE, $player->index, $map->index));
    return $result->fetch_all();
  }




  private function query($query)
  {
    $result = $this->mysqliClient->query($query);

    if ($this->mysqliClient->error) {
      trigger_error($this->mysqliClient->error, E_USER_ERROR);
    }

    return $result;
  }

}
