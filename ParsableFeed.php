<?php

/**
 * Interface for defining a feed that can be parsed by the framework
 *
 * @package    lib.feedsaggregator
 */
interface ParsableFeed
{
  /**
   * @return string the identifier for the FeedHandler class
   */
  public function getHandlerName();
  
  /**
   * @return string the identifier for the FeedHandler class
   */
  public function getConverterName();
  
  /**
   * @return integer the id of the row in the table describing the feed
   */
  public function getID();
  
  /**
   * @return string the URL of the feed
   */
  public function getUrl();
  
  /**
   * @return boolean whether the feed is active and available for parsing
   */
  public function isActive();
  
  /**
   * @return boolean whether the feed is compressed
   */
  public function isCompressed();
  
  /**
   * @return string the compression type
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