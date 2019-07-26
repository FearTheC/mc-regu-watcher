<?php

namespace FearTheC\ReguWatcher;

use FearTheC\ReguWatcher\PlayerRuntimesCollection;

class RuntimesRepository
{

  const DB_TABLE = "ftc_regularity_watcher";
  const DB_TIMES_TABLE = "ftc_regularity_watcher_times";

  const INIT_PLAYER_QUERY = <<<'EOT'
CREATE TABLE IF NOT EXISTS `" . self::DB_TABLE . "` (
    `index` int(11) NOT NULL AUTO_INCREMENT,
    `mapIndex` int(11) NOT NULL,
    `playerIndex` int(11) NOT NULL,
    `current_cursor` int(4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`index`),
    UNIQUE KEY `player_map_regu` (`mapIndex`,`playerIndex`)
  )
  ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1
EOT;

  const INIT_RUNTIMES_QUERY = <<<'EOT'
CREATE TABLE IF NOT EXISTS `".self::DB_TIMES_TABLE. "` (
     `cursor_nb` int(4) NOT NULL,
     `mapIndex` int(11) NOT NULL,
     `playerIndex` int(11) NOT NULL,
     `time` int(11),
     UNIQUE KEY `player_map_regu_times` (`mapIndex`,`playerIndex`)
  )
  ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1
EOT;

  private $mysqliClient;

  public function __construct($mysqliClient)
  {
    $this->$mysqliClient = $mysqliClient;
    $this->initTables();
  }


  private function initTables()
  {
    $this->query(self::INIT_PLAYER_QUERY);
    $this->query(self::INIT_RUNTIMES_QUERY);
  }


  private function query($query)
  {
    $result = $this->$mysqliClient->query($query);

    if ($this->$mysqliClient->error) {
      trigger_error($mysqli->error, E_USER_ERROR);
    }

    return $result;
  }

}
