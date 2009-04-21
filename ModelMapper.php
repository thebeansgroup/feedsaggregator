<?php

abstract class ModelMapper
{
  protected $feedConverter;

  public function __construct($feedConverter)
  {
    $this->feedConverter = $feedConverter;
  }

  public static function getInstance($feedConverter, $mainClassName)
  {
    $className = $mainClassName . 'ModelMapper';
    return new $className($feedConverter);
  }

  public function doMapping($feedId)
  {
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
      $this->insertItem($feedId);
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