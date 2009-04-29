<?php

interface ParsableFeed
{
  public function getHandlerName();
  public function getConverterName();
  public function getID(); // local column-value used by other tables
  public function getUrl();
  public function isActive();
  public function isCompressed();
  public function getCompressionType();
  public function refreshTimestamp();
}