<?php

namespace FearTheC\MCReguWatcher;

use FearTheC\MCReguWatcher\PlayerRuntimesCollection;

class PlayerRuntimes
{


  /**
   * @param PlayerRuntimesCollection;
   */
  private $runtimesCollection;

  private $playerId;


  public function __construct(PlayerRuntimesCollection $runtimes, $playerId)
  {
    $this->playerId = $playerId;
    $this->runtimesCollection = $runtimes;
  }

  public function getPlayerId()
  {
    return $this->playerId;
  }

  public function addRuntime($runtime, $cursor = -1) : int
  {
    $this->runtimesCollection->addRuntime($runtime, $cursor);

    return $this->runtimesCollection->getCurrentCursor();
  }

}
