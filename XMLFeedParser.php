<?php

/**
 * Parses feeds in XML format (suitable also for RSS feeds).
 *
 * @package    lib.feedsaggregator
 */
class XMLFeedParser extends FeedParser
{
  /**
   * @var XMLReader
   */
  private $parser;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->parser = new XMLReader();
  }

  /**
   * Opens the feed
   * 
   * @abstract
   * @param string $filepath
   */
  public function open($filepath)
  {
    if (!$this->parser->open($filepath))
    {
      throw new Exception("Feeds Aggregator - XML Reader - failed to open file $filepath");
    }
  }

  /**
   * Closes the feed
   * 
   * @abstract
   */  
  public function close()
  {
    $this->parser->close();
  }

  /**
   * @abstract
   * @param string $itemTag the tag name that wraps the item in the feed
   * @param array $elementsArray a list of *all* the tags an item can have
   * @return associative array all the elements the next item in the feed has 
   */  
  public function parseNextItem($itemTag, $elementsArray)
  {
      while($this->parser->read())
      {
        switch ($this->parser->nodeType) {
            case (XMLReader::ELEMENT):
              if( $this->parser->name == $itemTag )
              {
                return $this->parseInnerElements($this->parser->readOuterXML(), $elementsArray);
              }
            break;
        }
      }
  }

  /**
   * @access private
   * @param string $xml
   * @param array $elementsArray
   * @return associative array with all of the elements for the item 
   */
  private function parseInnerElements($xml, $elementsArray)
  {
    $values = array();

    $innerParser = new XMLReader();
    $innerParser->xml($xml);
    while($innerParser->read())
    {
      switch ($innerParser->nodeType) {
          case (XMLReader::ELEMENT):
            $elementName = $innerParser->name;
            if (in_array($elementName, $elementsArray))
            {
              $values[$elementName] = $innerParser->readString();
            }
          break;
      }
    }
    return $values;
  }
}