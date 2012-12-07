<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class BaseLsListEntity extends sfDoctrineRecord
{
  public function setTableDefinition()
  {
    $this->setTableName('ls_list_entity');
    $this->hasColumn('list_id', 'integer', null, array('type' => 'integer', 'notnull' => true));
    $this->hasColumn('entity_id', 'integer', null, array('type' => 'integer', 'notnull' => true));
    $this->hasColumn('rank', 'integer', null, array('type' => 'integer'));


    $this->index('item_uniqueness', array('fields' => array(0 => 'list_id', 1 => 'entity_id'), 'type' => 'unique'));
    $this->option('collate', 'utf8_unicode_ci');
    $this->option('charset', 'utf8');
  }

  public function setUp()
  {
    $this->hasOne('LsList', array('local' => 'list_id',
                                  'foreign' => 'id',
                                  'onDelete' => 'CASCADE',
                                  'onUpdate' => 'CASCADE'));

    $this->hasOne('Entity', array('local' => 'entity_id',
                                  'foreign' => 'id',
                                  'onDelete' => 'CASCADE',
                                  'onUpdate' => 'CASCADE'));

    $timestampable0 = new Doctrine_Template_Timestampable();
    $lsversionable0 = new LsVersionable();
    $softdelete0 = new Doctrine_Template_SoftDelete(array('name' => 'is_deleted'));
    $this->actAs($timestampable0);
    $this->actAs($lsversionable0);
    $this->actAs($softdelete0);
  }
}