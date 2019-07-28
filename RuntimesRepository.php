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
SELECT * FROM `%s` AS player
LEFT JOIN ftc_regularity_watcher_times times ON times.mapIndex = player.mapIndex AND times.playerIndex = player.playerIndex
WHERE player.playerIndex = %d AND player.mapIndex = %d
EOT;

  const INSERT_RUNTIME = <<<'EOT'
INSERT INTO ftc_regularity_watcher_times (mapIndex, playerIndex, time, cursor_nb)
VALUES (%1$d, %2$d, %3$d, %4$d)
ON DUPLICATE KEY UPDATE time = %3$d
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

    if (!$player) {
      $this->query(sprintf(self::INIT_PLAYER, self::DB_TABLE, $map->index, $player->index));
    }


  }


  public function selectPlayer(Player $player, Map $map)
  {
    $result = $this->query(sprintf(self::SELECT_PLAYER, self::DB_TABLE, $player->index, $map->index));
    return $result->fetch_all();
  }


  public function saveRuntime(Player $player, Map $map, int $time, $cursor)
  {
    $result = $this->query(sprintf(self::INSERT_RUNTIME, $player->index, $map->index, $time, $cursor));
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
