<?php

/**
 * Parent class for managing events during the parsing of a feed.
 *
 * @abstract
 * @package    lib.feedsaggregator
 */
abstract class FeedsAggregatorEventManager
{
  /**
   * A constant to identify the 'start aggregation' event
   * 
   * @var string
   */
  const START_AGGREGATION = 'Start Aggregation';
  
  /**
   * A constant to identify the 'end aggregation' event
   * 
   * @var string
   */
  const END_AGGREGATION = 'End Aggregation';
  
  /**
   * The list of all the possible events
   * 
   * @var array
   */
  public static $allowedEvents = array(self::START_AGGREGATION, self::END_AGGREGATION);

  /**
   * Stores the available event and the associated callback method.
   * 
   * @static
   * @var array
   */
  public static $eventsTable;

  /**
   * Registers an event and associates the callback method.
   * 
   * @static
   * @param string $eventName the event to register
   * @param string $className the class where the callback is located
   * @param string $methodName the callback method
   */  
  public static function register($eventName, $className, $methodName)
  {
    if (!self::eventExists($eventName))
    {
      throw new Exception('The event ' . $eventName . ' is not defined');
    }

    $eventsTable = self::$eventsTable;
    self::$eventsTable[$eventName] = array($className, $methodName); 
  }

  /**
   * Fires an event.
   * 
   * @static
   * @param string $eventName the event to fire
   */
  public static function fire($eventName)
  {
    if (!self::eventExists($eventName))
    {
      throw new Exception('The event ' . $eventName . ' is not defined');
    }

    if (isset(self::$eventsTable[$eventName]))
    {
      return call_user_func(self::$eventsTable[$eventName]);
    }
  }
  
  /**
   * Clears all the registered events.
   * 
   * @static
   */
  public static function clearEvents()
  {
    self::$eventsTable = array();
  }

  
  /**
   * Returns whether the eventName input exists
   * @access private
   * @param string $eventName
   * @return boolean 
   */
  private static function eventExists($eventName)
  {
    return in_array($eventName, self::$allowedEvents);
  }
}