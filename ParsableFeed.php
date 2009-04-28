<?php

interface ParsableFeed
{
  public function getHandlerIdentifier(); // it is used to compose the name of subclasses (usually a column 'name')
  public function getID(); // local column-value used by other tables
  public function getUrl();
  public function isActive();
  public function isCompressed();
  public function getCompressionType();
  public function refreshTimestamp();
}