<?php

abstract class ModelMapper
{
  protected $feedConverter;
  protected $dbConnection;
  protected $mainClassName;

  public function __construct($feedConverter, $mainClassName)
  {
    $this->mainClassName = $mainClassName;
    $this->feedConverter = $feedConverter;
    $peerClassName = $this->mainClassName . 'Peer';
    $this->dbConnection = Propel::getConnection(constant($peerClassName . '::DATABASE_NAME'), Propel::CONNECTION_WRITE);
  }

  public static function getInstance($feedConverter, $mainClassName)
  {
    $className = $mainClassName . 'ModelMapper';
    return new $className($feedConverter, $mainClassName);
  }

  public function doMapping($feedId)
  {
    $isDatabaseTransactionEnabled = true;
    $isItemToInsert = true;
    if ($oldItem = $this->getItemFromDataStore())
    {
      $this->refreshItem($oldItem);
      $isItemToInsert = false;
    }
    if ($this->itemDataAlreadyExists())
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
  * @return boolean|object - false if the item is not in the database yet, the object already
  *                          in the database otherwise
  */
  abstract public function getItemFromDataStore();
  abstract public function refreshItem($item);
 /**
  * @return boolean - true if we have already that item from any other feed, false otherwise
  */
  abstract public function itemDataAlreadyExists();
  abstract public function insertItem($feedId);
}