<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class BaseRepresentative extends sfDoctrineRecord
{
  public function setTableDefinition()
  {
    $this->setTableName('representative');
    $this->hasColumn('bioguide_id', 'string', 20, array('type' => 'string', 'length' => '20'));

    $this->option('collate', 'utf8_unicode_ci');
    $this->option('charset', 'utf8');
  }

  public function setUp()
  {
    $extension0 = new Extension();
    $this->actAs($extension0);
  }
}