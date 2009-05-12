<?php
/**
 * It is the front controller for the whole aggregation 
 *
 * @package    lib.feedsaggregator
 */
class FeedsAggregator
{
  /**
   * It is the number of messages logged before a notification email is delivered
   */
  const NUMBER_OF_ERROR_MESSAGES_BEFORE_SENDING_NOTIFICATION = 100;

  /**
   * The feeds to aggregate
   * 
   * @access private
   * @var array of Feed Objects
   */
  private $feeds;
  /**
   * The name of the main class related to the objects created on the datastore from the feeds.
   * I.e.: GbJobs, SybDeal
   * 
   * @access private
   * @var string
   */
  private $mainClassName;
  /**
   * A label for the environmnet the framework is running within (i.e.: 'dev', 'prod')
   * Useful for the error reporting.
   * 
   * @access private
   * @var string
   */
  private $environment;

  /**
   * Constructor
   * 
   * @param array $feeds (see the class properties)
   * @param string $mainClassName (see the class properties) 
   * @param $environment (see the class properties)
   */
  public function __construct($feeds, $mainClassName, $environment)
  {
    $this->feeds = $feeds;
    $this->mainClassName = $mainClassName;
    $this->environment = $environment;
  }

  /**
   * Performs the actual aggregation
   */
  public function aggregate()
  {
    try
    {
      $feedAggregatorConfig = FeedsAggregatorConfig::getInstance($this->mainClassName);
      $feedAggregatorConfig->registerEvents();
    }
    catch(Exception $e)
    {
      self::reportError("Error aggregating \n\n" . $e);
    }

    try
    {
      FeedsAggregatorEventManager::fire(FeedsAggregatorEventManager::START_AGGREGATION);
    }
    catch(Exception $e)
    {
      self::reportError("Error aggregating \n\n" . $e);
    }

    set_time_limit(0);

    foreach ($this->feeds as $feed)
    {
      $processingStartTime = time();
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
      $feed->recordProcessingTime(time() - $processingStartTime);
    }
    FeedsAggregator::reportError('', true, $this->environment);
    echo "<br /><br /><br />Aggregation completed.<br /><br /><br />";
    try
    {
      FeedsAggregatorEventManager::fire(FeedsAggregatorEventManager::END_AGGREGATION);
    }
    catch(Exception $e)
    {
      self::reportError("Error aggregating \n\n" . $e);
    }
    FeedsAggregatorEventManager::clearEvents();
  }

  /**
   * Sends logged errors via email.
   * It doesn't send an email for every error occured but it does use this class constant:
   * NUMBER_OF_ERROR_MESSAGES_BEFORE_SENDING_NOTIFICATION
   * 
   * @param string $msg the message to log
   * @param boolean $flush whether to flush the logs after appending the new message
   * @param string $environment  (see the class properties)
   */
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
  }
}