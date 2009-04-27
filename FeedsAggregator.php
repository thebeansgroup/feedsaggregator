<?php

class FeedsAggregator
{
  private $feeds;
  private $mainClassName;
  private $environment;

  public function __construct($feeds, $mainClassName, $environment)
  {
    $this->feeds = $feeds;
    $this->mainClassName = $mainClassName;
    $this->environment = $environment;
  }

  public function aggregate()
  {
    set_time_limit(0);

    foreach ($this->feeds as $feed)
    {
      try
      {
        $feedHandler = FeedHandler::getInstance($feed);
  
        $feedHandler->downloadFeed();
        $feedHandler->openFeed();
        while ($itemArrayFromFeed = $feedHandler->getNextItem())
        {
          $feedConverter = FeedConverter::getInstance($itemArrayFromFeed, $this->mainClassName, strtolower($feed->getUniqueIdentifier()));
  
          $modelMapper = ModelMapper::getInstance($feedConverter, $this->mainClassName);
          $modelMapper->doMapping($feed->getId());
        }
        $feedHandler->closeFeed();
        $feedHandler->deleteFeed();
  
        $feed->refreshTimestamp();
        $feed->save();
      }
      catch(Exception $e)
      {
        self::reportError("Error parsing the feed with id {$feed->getUniqueIdentifier()} \n\n" . $e);
      }
    }
    FeedsAggregator::reportError('', true, $this->environment);
    echo "<br /><br /><br />Aggregation completed.<br /><br /><br />";
  }

  public static function reportError($msg = '', $flush = false, $environment = '')
  {
    static $str;
    if ($msg)
    {
      $str .= $msg . "\n\n\n||||||||||||||||||| END ERROR MESSAGE ||||||||||||||||||\n\n\n        ";
    }
    if ($flush && $str)
    {
      mail('developers@studentbeans.com', "Feeds Aggregator error - {$_SERVER['HTTP_HOST']} - " . $environment, $str);
      echo $str;
    }
    return $str;
  }
}