<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class BaseSchool extends sfDoctrineRecord
{
  public function setTableDefinition()
  {
    $this->setTableName('school');
    $this->hasColumn('endowment', 'integer', null, array('type' => 'integer'));
    $this->hasColumn('students', 'integer', null, array('type' => 'integer'));
    $this->hasColumn('faculty', 'integer', null, array('type' => 'integer'));
    $this->hasColumn('tuition', 'integer', null, array('type' => 'integer'));
    $this->hasColumn('is_private', 'boolean', null, array('type' => 'boolean'));

    $this->option('collate', 'utf8_unicode_ci');
    $this->option('charset', 'utf8');
  }

  public function setUp()
  {
    $extension0 = new Extension();
    $this->actAs($extension0);
  }
}