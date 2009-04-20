<?php

abstract class FeedParser
{
  abstract public function open($filepath);
  abstract public function close();
  abstract public function parseNextItem($itemTag, $elementsArray);
}