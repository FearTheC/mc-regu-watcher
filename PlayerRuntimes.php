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

  private $cursor;


  public function __construct(PlayerRuntimesCollection $runtimes, $playerId, $cursor = 0)
  {
    $this->playerId = $playerId;
    $this->runtimesCollection = $runtimes;
    $this->cursor = $cursor;
  }



}
