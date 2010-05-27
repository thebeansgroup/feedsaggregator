<?php

/**
 * Parent class for doing basic transformations on the fields in the feed.
 *
 * @package    lib.feedsaggregator
 */
abstract class FeedHandler
{
  /**
   * @var ParsableFeed
   */
  protected $feed;
  /**
   * @var string
   */
  protected $feedFilepath;
  /**
   * @var string
   */
  protected $completeUrl;
  /**
   * @var FeedParser
   */
  protected $feedParser;
  /**
   * Contains all the fields from an item of the feed
   *
   * @var array
   */
  protected $itemArray;

  /**
   * Returns the tag that wraps the item in the feed (typically the child of the root)
   *
   * @abstract
   * @return string
   */
  abstract protected function getItemTag();

  /**
   * Returns *all* the tags desciribing an item in the feed.
   * Some items may not have some of these elements
   *
   * @abstract
   * @return array
   */
  abstract protected function getElementsArray();

  /**
   * Returns the potential elements we want to 'artificailly' add to every item in the feed
   *
   * @abstract
   * @return associative array
   */
  abstract protected function getExtraElementsArray();

  /**
   * Returns the list of all the elements that an item in the feed is allowed
   * not to have (optional elements)
   *
   * @abstract
   * @return array
   */
  abstract protected function getOptionalElementsArray();

  /**
   * Returns whether we want to discard an item from our aggregation because not
   * relevant to our purpose (it is a good way to do an initial filtering)
   *
   * @abstract
   * @param array $itemArrayFromFeed - the array representing a raw item from the feed
   * @return boolean
   */
  abstract public function discardItem(array $itemArrayFromFeed);

  /**
   * Constructor
   *
   * @param ParsableFeed $feed
   */
  protected function __construct(ParsableFeed $feed)
  {
    $this->feed = $feed;

    if ($this->feed->isDynamicUrl())
    {
      $this->completeUrl = $this->getDynamicUrl($this->feed->getUrl());
    }
    else
    {
      $this->completeUrl = $this->feed->getUrl();
    }

    $feedParserClassname = strtoupper($feed->getType()) . 'FeedParser';
    if (!class_exists($feedParserClassname))
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the parser");
    }
    $this->feedParser = new $feedParserClassname($this->feed);
  }

  /**
   * @param ParsableFeed $feed
   * @return FeedHandler child specific for a particular feed
   */
  public static function getInstance(ParsableFeed $feed)
  {
    $feedName = $feed->getHandlerName();
    if (! $feedName)
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the name for the feed {$feed->getUrl()}");
    }
    $classname = 'FeedHandler_' . $feedName;
    if (!class_exists($classname))
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the handler for the feed {$feed->getUrl()}");
    }
    return new $classname($feed);
  }

  /**
   * Downloads the feed and store it temporary in the /tmp directory
   *
   * @param string $prefixTempFile - the prefix to use for the feed temporary file
   */
  public function downloadFeed($prefixTempFile)
  {
    // this is the part of the path that is fixed for all the feeds downloaded for this project.
    // After this fixed part, we append a random string
    // THIS IS NOT only the directory where to download the feeds
    $temporaryFeedFileFixedPath = '/tmp/feedaggregator-' . $prefixTempFile . '-';

    if (! $this->completeUrl)
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the URL for the feed {$this->feed->getId()}");
    }
    $outputFilepath = $temporaryFeedFileFixedPath . rand() . '-' . time();

    $usernameOption = '';
    if ($this->feed->getUsername())
    {
      $usernameOption = "--user='" . $this->feed->getUsername() . "'";
    }
    $passwordOption = '';
    if ($this->feed->getPassword())
    {
      $passwordOption = "--password='" . $this->feed->getPassword() . "'";
    }

    exec("wget --quiet $usernameOption $passwordOption --output-document=$outputFilepath '$this->completeUrl'");

    exec("chmod 775 $outputFilepath");

    if (!filesize($outputFilepath))
    {

      throw new Exception("Feeds Aggregator - Couldn't download the feed {$this->feed->getId()}");
    }

    if ($this->feed->isCompressed())
    {
      switch ($this->feed->getCompressionType())
      {
        case 'gzip':
        // append '.gz' to the file name to keep gunzip happy
          rename($outputFilepath, $outputFilepath.'.gz');
          system('/usr/bin/gunzip '.$outputFilepath.'.gz');
          break;
        case 'zip':
        // append '.zip' to the file name to keep unzip happy
          rename($outputFilepath, $outputFilepath.'.zip');
          $outputFilepath .= '.zip';
          $outDir =  dirname($outputFilepath);
          $zipContents = shell_exec("/usr/bin/unzip -o $outputFilepath -d $outDir | tr -d '\n' | sed -e 's/.*inflating: //'");
          unlink($outputFilepath);
          $outputFilepath = trim($zipContents);
          break;
        case 'tar':
        // append '.tar.gz'
          rename($outputFilepath, $outputFilepath.'.tar.gz');
          $output = array();
          exec('/bin/tar xvzf '.$outputFilepath.'.tar.gz -C '.$this->xpdo->filePath, $output);
          unlink($fname.'.tar.gz');
          $outDir =  dirname($outputFilepath);
          $outputFilepath = $outDir . '/' . $output[0];
          if (!file_exists($outputFilepath))
            FeedsAggregator::reportError("Error untarring file for feed ".$this->feed->getId());
          break;
      }
    }
    $this->feedFilepath = $outputFilepath;
  }

  /**
   * Opens the feed
   */
  public function openFeed()
  {
    $this->feedParser->open($this->feedFilepath);
  }

  /**
   * Provides the next parsed item after having sanitized it and checked it against missing mandatory fields
   *
   * @return array|boolean - the item in an associative array or false when there are no more items to retrieve
   */
  public function getNextItem()
  {
    $item = $this->feedParser->parseNextItem($this->getItemTag(), $this->getElementsArray(), $this);
    if (!count($item)) // no more items to retrieve
    {
      return false;
    }
    // check whether some mandatory fields are empty
    foreach($item as $element => $value)
    {
      if ( (!is_array($value) && trim($value) == '') ||  (is_array($value) && count($value) == 0) )
      {
        if (!in_array($element, $this->getOptionalElementsArray())) // the item is NOT optional

        {
          throw new Exception("The mandatory element $element is empty in the feed {$this->feedFilepath} for the item with this details " .  print_r($item, true));
        }
      }
    }

    // check whether some mandatory element are missing in the item coming fron the feed
    $mandatoryFieldsArray = array_diff($this->getElementsArray(), $this->getOptionalElementsArray());
    foreach ($mandatoryFieldsArray as $mandatoryField)
    {
      if (!array_key_exists($mandatoryField, $item))
      {
        throw new Exception("The mandatory field $mandatoryField is missing in the feed {$this->feedFilepath} for the item with this details " .  print_r($item, true));
      }
    }
    // check every field is UTF8
    foreach($item as $element => $value)
    {
      if (!is_array($value))
      {
        if (!FeedTextFilter::isValidUTF8($value))
        {
          throw new Exception("The element $element in the feed {$this->feedFilepath} is NOT UTF8 for the item with this details " .  print_r($item, true));
        }
      }
    }

    // apply different filters to the items
    $item = array_merge($item, $this->getExtraElementsArray());
    foreach($item as $elementName => $elementValue)
    {
      if (is_array($elementValue))
      {
        foreach ($elementValue as $elementArrayValue)
        {
          $elementArrayValue = $this->generalFilter($elementArrayValue);
        }
      }
      else
      {
        $item[$elementName] = $this->generalFilter($elementValue);
      }
    }
    foreach($item as $elementName => $elementValue)
    {
      $filterMethodName = $this->getFilterMethodName($elementName);
      if (method_exists($this, $filterMethodName))
      {
        if (is_array($elementValue))
        {
          foreach ($elementValue as $elementArrayValue)
          {
            $elementArrayValue = $this->$filterMethodName($elementArrayValue);
          }
        }
        else
        {
          $item[$elementName] = $this->$filterMethodName($elementValue);
        }
      }
    }
    return $item;
  }

  /**
   * A filter suitable for every element inside the tags of an item
   *
   * @param string $value the string to filter
   * @return string filtered string
   */
  protected function generalFilter($value)
  {
    $value = trim($value);
    $value = strip_tags($value);
    $value = FeedTextFilter::html_entity_decode_utf8($value);
    FeedTextFilter::validateUTF8($value);
    return $value;
  }

  /**
   * Closes the feed
   */
  public function closeFeed()
  {
    $this->feedParser->close();
  }

  /**
   * Deletes the feed from the hard disk
   */
  public function deleteFeed()
  {
    if (is_file($this->feedFilepath))
    {
      unlink($this->feedFilepath);
    }
  }

  /**
   * A setter for the $itemArray
   *
   * @param array $itemArray
   */
  public function setItemArray($itemArray)
  {
    $this->itemArray = $itemArray;
  }

  /*
   * returns the current feed
   *
   * @return ParsableFeed
   */
  public function getFeed()
  {
    return $this->feed;
  }

  /**
   * Returns the filter method name built according the element name
   *
   * @param string $elementName
   * @return string
   */
  private function getFilterMethodName($elementName)
  {
    $elementName = preg_replace('/_([a-z])/e', "strtoupper('$1')", $elementName);
    return 'filter' . ucfirst($elementName);
  }

  /**
   * Runns before the feed url is used so that the url can be dynamically altered
   */
  abstract public function getDynamicUrl($incompleteUrl);
}