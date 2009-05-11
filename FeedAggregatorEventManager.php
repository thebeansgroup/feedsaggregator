<?php

abstract class FeedAggregatorEventManager
{
  const START_AGGREGATION = 'Start Aggregation';
  const END_AGGREGATION = 'End Aggregation';

  public static $eventsTable;

  public static function register($eventName, $className, $methodName)
  {
    $eventsTable = self::$eventsTable;
    self::$eventsTable[$eventName] = array($className, $methodName); 
  }

  public static function fire($eventName)
  {
    call_user_func(self::$eventsTable[$eventName]);
  }

  public static function clearEvents()
  {
    self::$eventsTable = array();
  }
}