<?php

class XMLFeedParser extends FeedParser
{
  private $parser;
  private $itemContent;

  public function __construct()
  {
    $this->parser = new XMLReader();
  }

  public function open($filepath)
  {
    if (!$this->parser->open($filepath))
    {
      throw new Exception("Feeds Aggregator - XML Reader - failed to open file $filepath");
    }
  }

  public function close()
  {
    $this->parser->close();
  }

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