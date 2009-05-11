<?php

abstract class FeedsAggregatorConfig
{
  public static function getInstance($mainClassName)
  {
    $classname = $mainClassName . __CLASS__;
    return new $classname();
  }

  abstract public function registerEvents();
}