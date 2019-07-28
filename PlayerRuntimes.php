<?php

namespace FearTheC\ReguWatcher;

use FearTheC\ReguWatcher\PlayerRuntimesCollection;

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
