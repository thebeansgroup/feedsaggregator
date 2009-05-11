<?php

abstract class FeedsAggregatorEventManager
{
  const START_AGGREGATION = 'Start Aggregation';
  const END_AGGREGATION = 'End Aggregation';
  public static $allowedEvents = array('Start Aggregation', 'End Aggregation');


  public static $eventsTable;

  public static function register($eventName, $className, $methodName)
  {
    if (!self::eventExist($eventName))
    {
      throw new Exception('The event ' . $eventName . ' is not defined');
    }

    $eventsTable = self::$eventsTable;
    self::$eventsTable[$eventName] = array($className, $methodName); 
  }

  public static function fire($eventName)
  {
    if (!self::eventExist($eventName))
    {
      throw new Exception('The event ' . $eventName . ' is not defined');
    }

    call_user_func(self::$eventsTable[$eventName]);
  }

  public static function clearEvents()
  {
    self::$eventsTable = array();
  }

  private static function eventExist($eventName)
  {
    return in_array($eventName, self::$allowedEvents);
  }
}