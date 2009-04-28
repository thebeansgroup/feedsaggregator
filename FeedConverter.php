<?php

abstract class FeedConverter
{
  protected $itemArray;
  protected $requiredGetters;

  public function __construct($itemArrayFromFeed)
  {
    $this->itemArray = $itemArrayFromFeed;
    $this->setRequiredGetters();
  }

  abstract protected function setRequiredGetters();

  public static function getInstance($itemArrayFromFeed, $mainClassName, $feedUniqueIdentifier)
  {
    $classname = $mainClassName . "FeedConverter" . '_' . $feedUniqueIdentifier;
    return new $classname($itemArrayFromFeed);
  }

  protected function __call($methodName, $args)
  {
    if (! in_array($methodName, $this->requiredGetters))
    {
      throw new Exception("Feeds Aggregator - The method $methodName is not provided.");
    }
    $directGetters = $this->getDirectGetters();
    if (array_key_exists($methodName, $directGetters))
    {
      $fieldName = $directGetters[$methodName];
      if ($fieldName)
      {
        if (isset($this->itemArray[$fieldName]))
        {
          return $this->itemArray[$fieldName];
        }
        else
        {
          return NULL;
        }
      }
      else // the value corresponding to the getter is empty-string. That should mean the getter is defined
           // in the subclass
      {
        throw new Exception("Feeds Aggregator - $methodName is not in the directGetters array.");
      }
    }
    else
    {
      throw new Exception("Feeds Aggregator - Couldn't get the method $methodName.");
    }
  }

  private function getFieldNameFromMethodName($methodName)
  {
    $str = substr($methodName, 3); // removing 'get'
    $str = $this->lcfirst($str);
    $str = preg_replace('/([A-Z])/', '_$1', $str);
    return strtolower($str);
  }

  private function lcfirst($str)
  {
    $str[0] = strtolower($str[0]);
    return (string)$str;
  }
}