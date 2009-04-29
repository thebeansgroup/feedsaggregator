<?php

abstract class FeedHandler
{
  protected $feed;
  protected $feedFilepath;
  protected $feedParser;
  protected $itemArray;

  abstract protected function getItemTag();
  abstract protected function getElementsArray();
  abstract protected function getExtraElements();
  abstract protected function getOptionalElementsArray();

  public function __construct(ParsableFeed $feed)
  {
    $this->feed = $feed;

    $feedParserClassname = strtoupper($feed->getType()) . 'FeedParser';
    if (!class_exists($feedParserClassname))
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the parser");
    }
    $this->feedParser = new $feedParserClassname();
  }

  public static function getInstance(ParsableFeed $feed)
  {
    $feedName = $feed->getHandlerName();
    if (! $feedName)
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the name for the feed {$feed->getUrl()}");
    }
    $classname = 'FeedHandler_' . $feedName;
    if (!class_exists($classname))
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the handler for the feed {$feed->getUrl()}");
    }
    return new $classname($feed);
  }

  public function downloadFeed()
  {
    $feedUrl = $this->feed->getUrl();
    if (! $feedUrl)
    {
      throw new Exception("Feeds Aggregator - Couldn't retrieve the URL for the feed {$this->feed->getId()}");
    }
    $outputFilepath = '/tmp/jobs-' . rand() . '-' . time();
    exec("wget --output-document=$outputFilepath '$feedUrl'");
    exec("chmod 775 $outputFilepath");

    if (!filesize($outputFilepath))
    {
      throw new Exception("Feeds Aggregator - Couldn't download the feed {$this->feed->getId()}");
    }

    if ($this->feed->isCompressed())
    {
        switch ($this->feed->getCompressionType())
        {
            case 'gzip':
                // append '.gz' to the file name to keep gunzip happy
                rename($outputFilepath, $outputFilepath.'.gz');
                system('/usr/bin/gunzip '.$outputFilepath.'.gz');
                break;
            case 'zip':
                // append '.zip' to the file name to keep unzip happy
                rename($outputFilepath, $outputFilepath.'.zip');
                $outputFilepath .= '.zip';
                $outDir =  dirname($outputFilepath);
                $zipContents = shell_exec("/usr/bin/unzip -o $outputFilepath -d $outDir | tr -d '\n' | sed -e 's/.*inflating: //'");
                unlink($outputFilepath);
                $outputFilepath = $zipContents;
                break;
            case 'tar':
                // append '.tar.gz'
                rename($outputFilepath, $outputFilepath.'.tar.gz');
                $output = array();
                exec('/bin/tar xvzf '.$outputFilepath.'.tar.gz -C '.$this->xpdo->filePath, $output);
                unlink($fname.'.tar.gz');
                $outDir =  dirname($outputFilepath);
                $outputFilepath = $outDir . '/' . $output[0];
                if (!file_exists($outputFilepath))
                    FeedsAggregator::reportError("Error untarring file for feed ".$this->feed->getId());
                break;
        }
    }
    $this->feedFilepath = $outputFilepath;
  }

  public function openFeed()
  {
    $this->feedParser->open($this->feedFilepath);
  }

  public function getNextItem()
  {
    $item = $this->feedParser->parseNextItem($this->getItemTag(), $this->getElementsArray());
    if (!count($item)) // no more items to retrieve
    {
      return false;
    }
    // check whether some mandatory fields are empty
    foreach($item as $element => $value)
    {
      if (trim($value) == '')
      {
        if (!in_array($element, $this->getOptionalElementsArray())) // the item is NOT optional
        {
          throw new Exception("The mandatory element $element is empty in the feed {$this->feedFilepath} for the item with this details " .  print_r($item, true));
        }
      }
    }

    // check whether some mandatory element are missing in the item coming fron the feed
    $mandatoryFieldsArray = array_diff($this->getElementsArray(), $this->getOptionalElementsArray());
    foreach ($mandatoryFieldsArray as $mandatoryField)
    {
      if (!array_key_exists($mandatoryField, $item))
      {
        throw new Exception("The mandatory field $mandatoryField is missing in the feed {$this->feedFilepath} for the item with this details " .  print_r($item, true));
      }
    }

    // check every field is UTF8
    foreach($item as $element => $value)
    {
      if (!self::isValidUTF8($value))
      {
        throw new Exception("The element $element in the feed {$this->feedFilepath} is NOT UTF8 for the item with this details " .  print_r($item, true));
      }
    }

    $item = array_merge($item, $this->getExtraElements());
    foreach($item as $elementName => $elementValue)
    {
      $item[$elementName] = $this->generalFilter($elementValue);
    }
    foreach($item as $elementName => $elementValue)
    {
      $filterMethodName = $this->getFilterMethodName($elementName);
      if (method_exists($this, $filterMethodName))
      {
        $item[$elementName] = $this->$filterMethodName($elementValue);
      }
    }
    return $item;
  }

  protected function generalFilter($value)
  {
    $value = trim($value);
    return $this->html_entity_decode_utf8($value);
  }

  public function closeFeed()
  {
    $this->feedParser->close();
  }

  public function deleteFeed()
  {
    if (is_file($this->feedFilepath))
    {
      unlink($this->feedFilepath);
    }
  }

  public function setItemArray($itemArray)
  {
    $this->itemArray = $itemArray;
  }

  private function getFilterMethodName($elementName)
  {
    $elementName = preg_replace('/_([a-z])/e', "strtoupper('$1')", $elementName);
    return 'filter' . ucfirst($elementName);
  }

  protected function html_entity_decode_utf8($string)
  {
    static $trans_tbl;
  
    // replace numeric entities
    $string = preg_replace('~&#x([0-9a-f]+);~ei', '$this->code2utf(hexdec("\\1"))', $string);
    $string = preg_replace('~&#([0-9]+);~e', '$this->code2utf(\\1)', $string);

    // replace literal entities
    if (!isset($trans_tbl))
    {
      $trans_tbl = array();
    
      foreach (get_html_translation_table(HTML_ENTITIES) as $val=>$key)
      {
        $trans_tbl[$key] = utf8_encode($val);
      }
    }
  
    return strtr($string, $trans_tbl);
  }

  protected function code2utf($number)
  {
    if ($number < 0)
      return FALSE;
    
    if ($number < 128)
      return chr($number);
    
    // Removing / Replacing Windows Illegals Characters
    if ($number < 160)
    {
          if ($number==128) $number=8364;
      elseif ($number==129) $number=160; // (Rayo:) #129 using no relevant sign, thus, mapped to the saved-space #160
      elseif ($number==130) $number=8218;
      elseif ($number==131) $number=402;
      elseif ($number==132) $number=8222;
      elseif ($number==133) $number=8230;
      elseif ($number==134) $number=8224;
      elseif ($number==135) $number=8225;
      elseif ($number==136) $number=710;
      elseif ($number==137) $number=8240;
      elseif ($number==138) $number=352;
      elseif ($number==139) $number=8249;
      elseif ($number==140) $number=338;
      elseif ($number==141) $number=160; // (Rayo:) #129 using no relevant sign, thus, mapped to the saved-space #160
      elseif ($number==142) $number=381;
      elseif ($number==143) $number=160; // (Rayo:) #129 using no relevant sign, thus, mapped to the saved-space #160
      elseif ($number==144) $number=160; // (Rayo:) #129 using no relevant sign, thus, mapped to the saved-space #160
      elseif ($number==145) $number=8216;
      elseif ($number==146) $number=8217;
      elseif ($number==147) $number=8220;
      elseif ($number==148) $number=8221;
      elseif ($number==149) $number=8226;
      elseif ($number==150) $number=8211;
      elseif ($number==151) $number=8212;
      elseif ($number==152) $number=732;
      elseif ($number==153) $number=8482;
      elseif ($number==154) $number=353;
      elseif ($number==155) $number=8250;
      elseif ($number==156) $number=339;
      elseif ($number==157) $number=160; // (Rayo:) #129 using no relevant sign, thus, mapped to the saved-space #160
      elseif ($number==158) $number=382;
      elseif ($number==159) $number=376;
    } //if
    
    if ($number < 2048)
      return chr(($number >> 6) + 192) . chr(($number & 63) + 128);
    if ($number < 65536)
      return chr(($number >> 12) + 224) . chr((($number >> 6) & 63) + 128) . chr(($number & 63) + 128);
    if ($number < 2097152)
      return chr(($number >> 18) + 240) . chr((($number >> 12) & 63) + 128) . chr((($number >> 6) & 63) + 128) . chr(($number & 63) + 128);
    
    return FALSE;
  }

    /**
     * Makes sure that input is valid UTF8. If not, it converts it to
     * UTF8.
     * 
     * @param string $data The input to validate
     */
    public static function validateUTF8(&$data)
    {
        if (!self::isValidUTF8($data))
            $data = utf8_encode($data);
    }

    /**
     * Returns whether the input is valid UTF8.
     *
     * @param mixed $data The input to test
     * @return bool
     */
    private static function isValidUTF8(&$data)
    {
    	// we only need to validate string values and arrays potentially containing
    	// string values
    	if (!is_string($data) && !is_array($data))
    		return true;
    		
        static $utf8Validator;
        
        if (!isset($utf8Validator))
            $utf8Validator = self::getValidUTF8Expression();
        
        // if the $data is actually an array, make sure to 
        if (is_array($data))
        {
        	foreach ($data as $k => $v)
        	{
        		if (!preg_match("!{$utf8Validator}!", $v))
        			return false;
        	}
        	
        	return true;

        }
        else	// if the value is a string, just return the results of the test           
        	return preg_match("!{$utf8Validator}!", $data) ? 0 : 1;
    }

    /**
     * Returns a regular expression that can be used to test whether
     * input is valid UTF8. 
     *
     * This could just be a very ugly single-line
     * class variable, but putting it in a method, and treating it as
     * a singleton in the invoking method (isValidUTF8) keeps it neat 
     * and doesn't cost too much.
     *
     * @return string The regular expression
     */
    private static function getValidUTF8Expression()
    {
        $utf8Validator = '[\xC0-\xDF]([^\x80-\xBF]|$)';
        $utf8Validator .= '|[\xE0-\xEF].{0,1}([^\x80-\xBF]|$)';
        $utf8Validator .= '|[\xF0-\xF7].{0,2}([^\x80-\xBF]|$)';
        $utf8Validator .= '|[\xF8-\xFB].{0,3}([^\x80-\xBF]|$)';
        $utf8Validator .= '|[\xFC-\xFD].{0,4}([^\x80-\xBF]|$)';
        $utf8Validator .= '|[\xFE-\xFE].{0,5}([^\x80-\xBF]|$)';
        $utf8Validator .= '|[\x00-\x7F][\x80-\xBF]';
        $utf8Validator .= '|[\xC0-\xDF].[\x80-\xBF]';
        $utf8Validator .= '|[\xE0-\xEF]..[\x80-\xBF]';
        $utf8Validator .= '|[\xF0-\xF7]...[\x80-\xBF]';
        $utf8Validator .= '|[\xF8-\xFB]....[\x80-\xBF]';
        $utf8Validator .= '|[\xFC-\xFD].....[\x80-\xBF]';
        $utf8Validator .= '|[\xFE-\xFE]......[\x80-\xBF]';
        $utf8Validator .= '|^[\x80-\xBF]';
        
        return $utf8Validator;
    }
}