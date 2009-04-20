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
    if ($this->isDuplicateItem())
    {
      return;
    }
    $this->populateDatabase($feedId);
  }

  abstract public function isDuplicateItem();
  abstract public function populateDatabase($feedId);
}