<?php

/**
 * Interface for defining a feed that can be parsed by the framework
 *
 * @package    lib.feedsaggregator
 */
interface ParsableFeed
{
  /**
   * Returns the identifier for the FeedHandler class
   *
   * @return string
   */
  public function getHandlerName();
  
  /**
   * Returns the identifier for the FeedHandler class
   *
   * @return string
   */
  public function getConverterName();
  
  /**
   * Returns the id of the row in the table describing the feed
   * 
   * @return integer
   */
  public function getID();
  
  /**
   * Returns the URL of the feed
   *
   * @return string
   */
  public function getUrl();
  
  /**
   * Returns whether the feed is active and available for parsing
   *
   * @return boolean whether the feed is active and available for parsing
   */
  public function isActive();
  
  /**
   * Returns whether the feed is compressed
   *
   * @return boolean
   */
  public function isCompressed();
  
  /**
   * Returns the compression type
   *
   * @return string
   */
  public function getCompressionType();
  
  /**
   * Refreshes the timestamp of the feed to show when it was last parsed
   */
  public function refreshTimestamp();
  
  /**
   * Records the number of seconds the system has taken to process the feed 
   * 
   * @param integer $seconds
   */
  public function recordProcessingTime($seconds);  
}