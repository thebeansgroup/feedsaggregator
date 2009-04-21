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

      $feed->setLastParsedAt(date('Y-m-d H:i:s', time()));
      $feed->save();
    }
    FeedsAggregator::reportError('', true, $this->environment);
    echo "Aggregation completed.";
  }

  public static function reportError($msg = '', $flush = false, $environment = '')
  {
    static $str;
    if ($msg)
    {
      $str .= $msg . '        ';
    }
    if ($flush && $str)
    {
      mail('developers@studentbeans.com', 'Feeds Aggregator error - ' . $environment, $str);
    }
    return $str;
  }
}