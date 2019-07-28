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



}
