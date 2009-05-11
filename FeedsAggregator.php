<?php

class FeedsAggregator
{
  const NUMBER_OF_ERROR_MESSAGES_BEFORE_SENDING_NOTIFICATION = 100;

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
    $feedAggregatorConfig = FeedAggregatorConfig::getInstance($this->mainClassName);
    $feedAggregatorConfig->registerEvents();

    FeedAggregatorEventManager::fire(FeedAggregatorEventManager::START_AGGREGATION);

    set_time_limit(0);

    foreach ($this->feeds as $feed)
    {
      try
      {
        $feedHandler = FeedHandler::getInstance($feed);
  
        $feedHandler->downloadFeed();
        $feedHandler->openFeed();

        while (true)
        {
          try
          {
            $itemArrayFromFeed = $feedHandler->getNextItem();
          }
          catch (Exception $e)
          {
            self::reportError("Error parsing the feed with id {$feed->getId()} \n\n" . $e);
            continue;
          }

          if (! is_array($itemArrayFromFeed))
          {
            break;
          }

          try
          {
            $feedConverter = FeedConverter::getInstance($itemArrayFromFeed, $this->mainClassName, $feed->getConverterName());
    
            $modelMapper = ModelMapper::getInstance($feedConverter, $this->mainClassName);
            $modelMapper->doMapping($feed->getId());
          }
          catch (Exception $e)
          {
            self::reportError("Error during converting and mapping the feed with id {$feed->getId()} \n\n" . $e);
            continue;
          }
        }
        $feed->refreshTimestamp();
        $feed->save();
      }
      catch(Exception $e)
      {
        self::reportError("Error parsing the feed with id {$feed->getHandlerName()} \n\n" . $e);
      }
      if (is_object($feedHandler))
      {
        $feedHandler->closeFeed();
        $feedHandler->deleteFeed();
      }
    }
    FeedsAggregator::reportError('', true, $this->environment);
    echo "<br /><br /><br />Aggregation completed.<br /><br /><br />";
    FeedAggregatorEventManager::fire(FeedAggregatorEventManager::END_AGGREGATION);
    FeedAggregatorEventManager::clearEvents();
  }

  public static function reportError($msg = '', $flush = false, $environment = '')
  {
    static $str;
    static $counter = 0;

    if ($counter == self::NUMBER_OF_ERROR_MESSAGES_BEFORE_SENDING_NOTIFICATION)
    {
      $flush = true;
      $counter = 0;
      $str = '';
    }

    if ($msg)
    {
      $str .= $msg . "\n\n\n||||||||||||||||||| END ERROR MESSAGE ||||||||||||||||||\n\n\n        ";
    }
    if ($flush && $str)
    {
      mail('developers@studentbeans.com', "Feeds Aggregator error - {$_SERVER['HTTP_HOST']} - " . $environment, $str);
      echo $str;
    }
    $counter++;
    return $str;
  }
}