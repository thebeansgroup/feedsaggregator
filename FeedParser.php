<?php

/**
 * Parent class for parsing feeds.
 *
 * @abstract
 * @package    lib.feedsaggregator
 */
abstract class FeedParser
{
  /**
   * Opens the feed
   * 
   * @abstract
   * @param string $filepath
   */
  abstract public function open($filepath);
  /**
   * Closes the feed
   * 
   * @abstract
   */
  abstract public function close();
  /**
   * Returns an associative array all the elements the next item in the feed has
   *
   * @abstract
   * @param string $itemTag the tag name that wraps the item in the feed
   * @param array $elementsArray a list of *all* the tags an item can have
   * @return associative array
   */
  abstract public function parseNextItem($itemTag, $elementsArray);
}