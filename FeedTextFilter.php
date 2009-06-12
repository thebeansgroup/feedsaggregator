<?php
/**
 * Utility class that gathers some function to process text.
 *
 * @abstract
 * @package    lib.feedsaggregator
 */
abstract class FeedTextFilter
{

  /**
   * A html_entity_decode function working with UTF8 characters
   * 
   * @access protected
   * @param string $string the string to decode
   * @return string
   */
  public static function html_entity_decode_utf8($string)
  {
    static $trans_tbl;
  
    // replace numeric entities
    $string = preg_replace('~&#x([0-9a-f]+);~ei', 'self::code2utf(hexdec("\\1"))', $string);
    $string = preg_replace('~&#([0-9]+);~e', 'self::code2utf(\\1)', $string);

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

  /**
   * Converts a Unicode character index to UTF-8 multi-byte encoding 
   * 
   * @access protected
   * @param integer $number character index
   * @return string|boolean UTF-8 representation, false if an error arises.
   */
  protected static function code2utf($number)
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
    public static function isValidUTF8(&$data)
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