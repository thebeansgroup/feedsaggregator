<?php

/**
 * Parent class for making transformations to the feed fields to make them ready to be used by the ModelMapper class.
 *
 * @abstract
 * @package    lib.feedsaggregator
 */
abstract class FeedConverter
{
  /**
   * Contains all the fields coming from the feed (after being processed by FeedHandler).
   * This is the array that this class may transformed.
   * ModelMapper will use this array to populate the database.
   * 
   * @var array
   */
  protected $itemArray;
  /**
   * Contains all the 'get' methods required by the ModelMapper to work
   * @var array
   */
  protected $requiredGetters;

  /**
   * Constructor
   * 
   * @param array $itemArrayFromFeed the array from the feed (after the transformations by FeedHandler)
   */
  public function __construct($itemArrayFromFeed)
  {
    $this->itemArray = $itemArrayFromFeed;
    $this->setRequiredGetters();
  }

  /**
   * Sets all the 'get' methods required by the ModelMapper to work
   * 
   * @abstract
   */
  abstract protected function setRequiredGetters();
  /**
   * Returns the array of the 'get' methods that are a direct mapping from the FeedHandler (they don't require further transformations)
   * 
   * @abstract
   * @return array 
   */
  abstract protected function getDirectGetters();

  /**
   * Provide a non-singleton instance of the proper subclass to be used 
   * @static
   * @param array $itemArrayFromFeed the array of fields from the feed
   * @param string $mainClassName the name of the main class (defined from outside this library)
   * @param string $feedConverterName the converter name
   * @return object an instance of the proper object
   */
  public static function getInstance($itemArrayFromFeed, $mainClassName, $feedConverterName)
  {
    $classname = $mainClassName . "FeedConverter" . '_' . $feedConverterName;
    return new $classname($itemArrayFromFeed);
  }

  /**
   * This is a standard PHP __call magic method.
   * Powers the 'directGetters' mechanism. If a field from the feed is ready to be used by the ModelMapper we don't need further transformations.
   * 
   * @param string $methodName
   * @param array $args
   * @return string
   */
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
      else // the value corresponding to the getter is an empty-string. 
           // That means the getter should have been defined in the FeedConverter subclass
      {
        throw new Exception("Feeds Aggregator - $methodName is not in the directGetters array.");
      }
    }
    else
    {
      throw new Exception("Feeds Aggregator - Couldn't get the method $methodName.");
    }
  }

  /**
   * Gets the field name from the name of the getter
   * 
   * @access private
   * @param string $methodName
   * @return string
   */
  private function getFieldNameFromMethodName($methodName)
  {
    $str = substr($methodName, 3); // removing 'get'
    $str = $this->lcfirst($str);
    $str = preg_replace('/([A-Z])/', '_$1', $str);
    return strtolower($str);
  }

  /**
   * Returns the input string with the first character lowercase
   * 
   * @access private
   * @param string $str
   * @return string
   */
  private function lcfirst($str)
  {
    $str[0] = strtolower($str[0]);
    return (string)$str;
  }
}