<?php

abstract class FeedAggregatorConfig
{
  public static function getInstance($mainClassName)
  {
    $classname = $mainClassName . "FeedAggregatorConfig";
    return new $classname();
  }

  abstract public function registerEvents();
}