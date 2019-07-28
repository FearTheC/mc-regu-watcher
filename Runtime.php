<?php

namespace FearTheC\MCReguWatcher;

use ManiaControl\Maps\Map;
use ManiaControl\Players\Player;

class Runtime
{

  private $playerId;

  private $mapId;

  private $time;

  private $cursor;

  public function __construct(Player $player, Map $map, $time, $cursor)
  {
    $this->playerId = $player->index;
    $this->mapId = $player->index;
    $this->time = $time;
    $this->cursor = $cursor;
  }

}
