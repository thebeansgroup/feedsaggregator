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
   * @var ParsableFeed
   */
  protected $feed;

  /**
   * Constructor
   */
  public function __construct(ParsableFeed $feed)
  {
    $this->parser = new XMLReader();
    $this->feed = $feed;
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
      switch ($this->parser->nodeType)
      {
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
  private function parseInnerElements($xml, $elementsArray, FeedHandler $feedHandler = null)
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
        $attributes[$elementsAndAttributes[0]][] = $elementsAndAttributes[1];
      }
      $i++;
    }

    $innerParser = new XMLReader();
    $innerParser->xml($xml);
    while($innerParser->read())
    {
      switch ($innerParser->nodeType)
      {
        case (XMLReader::ELEMENT):
          $elementName = $innerParser->name;
          if (array_key_exists($elementName, $attributes))
          {
            foreach ($attributes[$elementName] as $attribute)
            {
              // check if this element has already been found before
              if (isset($values[$elementName . "@" . $attribute]))
              {
                $values[$elementName . "@" . $attribute] =
                        $this->addValueToArray($values[$elementName . "@" . $attribute],
                        $innerParser->getAttribute($attribute)
                );
              }
              else
              {
                $values[$elementName . "@" . $attribute] = $innerParser->getAttribute($attribute);
              }
            }
          }
          elseif (in_array($elementName, $elements))
          {
            // check if this element has already been found before
            if (isset($values[$elementName]))
            {
              $values[$elementName] = $this->addValueToArray($values[$elementName], $innerParser->readString());
            }
            else
            {
              $values[$elementName] = $innerParser->readString();
            }
          }
          break;
      }
    }
    // get the nodes which are to be retrieved by XPath
    $xPathNodes = $this->getXPathNodes($elements);
    if (count($xPathNodes) > 0)
    {
      // we need the feed handler to be able to get the xpath expressions
      $feedHandler = FeedHandler::getInstance($this->feed);

      // get the dom object for the current node
      $dom = new DOMDocument();
      $dom->loadXML($xml);
      $xpath = new DOMXPath($dom);
      foreach ($xPathNodes as $xPathNode)
      {
        $xPathFunction = $this->getXPathFunction($xPathNode);
        $xPathExpression = $feedHandler->$xPathFunction();
        $nodeList = $xpath->query($xPathExpression);
        foreach ($nodeList as $node)
        {
          if (isset($values[$xPathNode]))
          {
            $values[$xPathNode] = $this->addValueToArray($values[$xPathNode], $node->nodeValue);
          }
          else
          {
            $values[$xPathNode] = $node->nodeValue;
          }
        }
      }
    }
    return $values;
  }

  /*
   * This function checks whether the current variable is already an array. if it is it adds the value to it and returns
   * the current array, otherwise it creates an array and returns that
   *
   * @param $current string|array
   * @param $value string
   * @return array
  */
  private function addValueToArray($current, $value)
  {
    if (is_array($current))
    {
      $current[] = $value;
      return $current;
    }
    else
    {
      $currentArray = array();
      $currentArray[] = $current;
      $currentArray[] = $value;
      return $currentArray;
    }
  }

  private function getXPathNodes(array $array)
  {
    $matches = array();
    foreach ($array as $node)
    {
      preg_match("!(_{2}.+)!", $node, $match);
      if (count($match) > 0)
      {
        $matches[] = $match[0];
      }
    }
    return $matches;
  }

  private function getXPathFunction($xPathNode)
  {
    $xPathNode = preg_replace('!_{2}!', '', $xPathNode);
    return 'get' . ucfirst(strtolower($xPathNode)) . 'XPath';
  }
}