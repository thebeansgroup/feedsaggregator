<?php

abstract class ModelMapper
{
  protected $feedConverter;
  protected $dbConnection; 

  public function __construct($feedConverter)
  {
    $this->feedConverter = $feedConverter;
    $this->dbConnection = Propel::getConnection(GbJobPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
  }

  public static function getInstance($feedConverter, $mainClassName)
  {
    $className = $mainClassName . 'ModelMapper';
    return new $className($feedConverter);
  }

  public function doMapping($feedId)
  {
    $isDatabaseTransactionEnabled = true;
    $isItemToInsert = true;
    if ($oldItem = $this->isItemAlreadyInTheDatabase())
    {
      $this->refreshItem($oldItem);
      $isItemToInsert = false;
    }
    if ($oldItems = $this->isItemDuplicated())
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
        throw $e;
      }
    }
  }

 /**
  * @return boolean|object - false if the item is not in the database yet, the object already
  *                          in the database otherwise
  */
  abstract public function isItemAlreadyInTheDatabase();
  abstract public function refreshItem($oldItem);
 /**
  * @return boolean - true if we have already that item from any other feed, false otherwise
  */
  abstract public function isItemDuplicated();
  abstract public function insertItem($feedId);
}