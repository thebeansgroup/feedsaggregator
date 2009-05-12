<?php
/**
 * Parent class that defines the configuration for the aggregation
 *
 * @abstract
 * @package    lib.feedsaggregator
 */
abstract class FeedsAggregatorConfig
{
  /**
   * @param string $mainClassName
   * @return object child of this class
   */
  public static function getInstance($mainClassName)
  {
    $classname = $mainClassName . __CLASS__;
    return new $classname();
  }

  /**
   * Register the callbacks for the events
   * 
   * @abstract
   */
  abstract public function registerEvents();
}