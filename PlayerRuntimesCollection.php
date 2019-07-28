<?php

namespace FearTheC\MCReguWatcher;

class PlayerRuntimesCollection
{

  const MAX_RUNTIMES = 50;

  private $runtimes = [];

  private $currentCursor;

  public function __construct($currentCursor)
  {
    $this->currentCursor = $currentCursor;
  }


  public static function init(array $runtimes, $currentCursor)
  {
    $this->$currentCursor = $currentCursor;

    foreach ($runtimes as $runtime) {
      $this->addRuntime($runtime);
    }
  }

  public function addRuntime($runtime, $cursor = -1)
  {
    if ($cursor == -1) {
      $this->incrementCursor();
      $cursor = $this->currentCursor;
    }  else {
      $this->currentCursor = $cursor;
    }

    $this->runtimes[$cursor] = $runtime;

    return [$this->currentCursor, $runtime];
  }

  private function getCurrentCursor()
  {
    return $this->currentCursor;
  }

  private function incrementCursor()
  {
    $this->currentCursor++;

    if ($this->currentCursor == self::MAX_RUNTIMES) {
      $this->currentCursor = 0;
    }
  }

  public function getAvgRegu()
  {
    $rtCount = 0;
    $sum = 0;
    $failures = 0;

    foreach ($this->runtimes as $cursor => $runtime) {
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
