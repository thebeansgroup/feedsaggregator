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
   * Returns an associative array all the elements the next item in the feed has
   *
   * @abstract
   * @param string $itemTag the tag name that wraps the item in the feed
   * @param array $elementsArray a list of *all* the tags an item can have
   * @return associative array
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
   * Returns an associative array with all of the elements for the item
   *
   * @access private
   * @param string $xml
   * @param array $elementsArray
   * @return associative array
   */
  private function parseInnerElements($xml, $elementsArray)
  {
    $elements = array();
    $attributes = array();
    $values = array();

    // first we need to check if we have any attributes. if we do we need to store them in a
    // seperate array and restore the proper node name for matching further down.
    // as an example:
    // product@in_stock, this is expressing an XML element <product in_stock="XYZ">
    // product@in_stock wont match the element <product> which is why we have to seperate
    // element and attribute name
    $i = 0;
    foreach ($elementsArray as $rawElement)
    {
      $elementsAndAttributes = explode ("@", $rawElement);
      $elements[$i] = $elementsAndAttributes[0];
      if (count($elementsAndAttributes) > 1)
      {
        $attributes[$elementsAndAttributes[0]] = $elementsAndAttributes[1];
      }
      $i++;
    }

    $innerParser = new XMLReader();
    $innerParser->xml($xml);
    while($innerParser->read())
    {
      switch ($innerParser->nodeType) {
        case (XMLReader::ELEMENT):
          $elementName = $innerParser->name;
          if (in_array($elementName, $elements) && array_key_exists($elementName, $attributes))
          {
            $values[$elementName . "@" . $attributes[$elementName]] = $innerParser->getAttribute($attributes[$elementName]);
          }
          elseif (in_array($elementName, $elements))
          {
            $values[$elementName] = $innerParser->readString();
          }
          break;
      }
    }
    return $values;
  }
}