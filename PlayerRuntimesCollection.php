<?php

namespace FearTheC\MCReguWatcher;

class PlayerRuntimesCollection
{

  const MAX_RUNTIMES = 50;

  private $runtimes = [];

  private $currentCursor;

  private function __construct(array $runtimes)
  {

  }


  public static function init(array $runtimes, $currentCursor)
  {
    $this->$currentCursor = $currentCursor;

    foreach ($runtimes as $runtime) {
      $this->addRuntime($runtime);
    }
  }

  public function addRuntime($runtime)
  {
    $this->incrementCursor();
    $this->runtimes[$this->currentCursor] = $runtime;
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
