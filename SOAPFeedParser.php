<?php

/**
 * Parses a SOAP response from a WebService
 * A file WSDL must be provided.
 *
 * In the case of SOAP feed, the URL will be formatted like this:
 * _ the URL of the WSDL file
 * _ a space
 * _ the method to invoke
 *
 * @package    lib.feedsaggregator
 */
class SOAPFeedParser extends FeedParser
{
  /**
   * @var ParsableFeed
   */
  protected $feed;

  /**
   *
   * @var string soapResponse
   */
  protected $soapResponse;

  private $elements;

  /**
   * Constructor
   */
  public function __construct(ParsableFeed $feed)
  {
    $this->feed = $feed;

    // We need to get the WSDL file through Apache.

    // In the case of SOAP feed, the URL will be formatted like this:
    // _ the URL of the WSDL file
    // _ a space
    // _ the method to invoke
    list($wsdlPath, $methodToInvoke) = explode(' ', $feed->getUrl());

    $wsdlPath = "http://vacancies.webservices.targetjobs.co.uk:8004/TargetJobsSimplifiedVacancyServiceSoap?wsdl";

    $params = array('login' => $feed->getUsername(),
                    'password' => $feed->getPassword());

    $client = new SoapClient($wsdlPath, $params);

    $this->soapResponse = $client->$methodToInvoke();
  }

  /**
   * Opens the feed
   *
   * @abstract
   * @param string $filepath
   */
  public function open($filepath)
  {
  }

  /**
   * Closes the feed
   *
   * @abstract
   */
  public function close()
  {
  }

  /**
   * Returns an associative array all the elements the next item in the feed has
   *
   * @abstract
   * @param string $itemTag the tag name that wraps the item in the feed
   * @param array $elementsArray a list of *all* the tags an item can have
   * @return associative array| false when there are not more elements
   */
  public function parseNextItem($itemTag, $elementsArray)
  {
    static $callCounter = 0;

    if (!$callCounter) // first call
    {
        $this->elements = $this->soapResponse->$itemTag;
    }

    if (isset($this->elements[$callCounter]))
    {
        $itemObject = $this->elements[$callCounter];
    }
    else
    {
        return false;
    }

    $callCounter++;

    return get_object_vars($itemObject);
  }
}