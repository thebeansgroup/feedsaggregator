<?php

/**
 * This class is in charge to store in the datastore the elements coming from the feed
 * (after they have been processed by the FeedHandler and FeedConverter)
 *
 * @abstract
 * @package    lib.feedsaggregator
 */
abstract class ModelMapper
{
  /**
   * @var FeedConverter
   */
  protected $feedConverter;
  /**
   * @var Propel Connection
   */
  protected $dbConnection;
  /**
   * @var string
   */
  protected $mainClassName;

  /**
   * Constructor
   * 
   * @param FeedConverter $feedConverter
   * @param string $mainClassName
   */
  public function __construct(FeedConverter $feedConverter, $mainClassName)
  {
    $this->mainClassName = $mainClassName;
    $this->feedConverter = $feedConverter;
    $peerClassName = $this->mainClassName . 'Peer';
    $this->dbConnection = Propel::getConnection(constant($peerClassName . '::DATABASE_NAME'), Propel::CONNECTION_WRITE);
  }

  /**
   * @param FeedConverter $feedConverter
   * @param string $mainClassName
   * @return ModelMapper a suitable child class
   */
  public static function getInstance(FeedConverter $feedConverter, $mainClassName)
  {
    $className = $mainClassName . 'ModelMapper';
    
    return new $className($feedConverter, $mainClassName);
  }

  /**
   * It does the actual mapping of the items coming from the feed to the datastore
   *
   * @param integer $feedId
   */
  public function doMapping($feedId)
  {
    $isDatabaseTransactionEnabled = true;
    $isItemToInsert = true;
    if ($oldItem = $this->getItemFromDataStore($feedId))
    {
      $this->refreshItem($oldItem);
      $isItemToInsert = false;
    }
    else if($this->itemDataAlreadyExists($feedId))
    {
      $isItemToInsert = false;
    }

    if ($isItemToInsert)
    {
      try
      {
        $this->dbConnection->beginTransaction();
      }
      catch(Exception $e)
      {
        $isDatabaseTransactionEnabled = false;
      }

      try
      {
        $this->insertItem($feedId);
        if ($isDatabaseTransactionEnabled)
        {
          $this->dbConnection->commit();
        }
      }
      catch(Exception $e)
      {
        if ($isDatabaseTransactionEnabled)
        {
          $this->dbConnection->rollBack();
        }
        $feedConverterDump = print_r($this->feedConverter, true);
        $errorMsg = "Feeds Aggregator Error - Unable to map an item in the feed with feedID=$feedId . These are the details of the item:\n$feedConverterDump\n\nException Object: \n$e";
        FeedsAggregator::reportError($errorMsg);
      }
    }
  }

 /**
  * Checks whether the item is already in the datastore
  *
  * @abstract
  * @return boolean|object - false if the item is not in the database yet, the object already
  *                          in the database otherwise
  */
  abstract public function getItemFromDataStore($feedId=null);
  
 /**
  * Refreshes the 'last-parsed' timestamp of the item
  *
  * @abstract
  * @param object $item - the item to refresh the 'last-parsed' timestamp of
  */
  abstract public function refreshItem($item);
  
 /**
  * Checks whether there is already an item with the same data in the datastore
  *
  * @abstract
  * @return boolean - true if we have already that item from any other feed, false otherwise
  */
  abstract public function itemDataAlreadyExists($feedId=null);
  
 /**
  * Inserts the item in the datastore
  * 
  * @abstract
  * @param integer $feedId
  */
  abstract public function insertItem($feedId);
}