<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class BaseAddress extends sfDoctrineRecord
{
  public function setTableDefinition()
  {
    $this->setTableName('address');
    $this->hasColumn('entity_id', 'integer', null, array('type' => 'integer', 'notnull' => true, 'notblank' => true));
    $this->hasColumn('street1', 'string', 100, array('type' => 'string', 'notnull' => true, 'notblank' => true, 'length' => '100'));
    $this->hasColumn('street2', 'string', 100, array('type' => 'string', 'notblank' => true, 'length' => '100'));
    $this->hasColumn('street3', 'string', 100, array('type' => 'string', 'notblank' => true, 'length' => '100'));
    $this->hasColumn('city', 'string', 50, array('type' => 'string', 'notnull' => true, 'notblank' => true, 'length' => '50'));
    $this->hasColumn('county', 'string', 50, array('type' => 'string', 'length' => '50'));
    $this->hasColumn('state_id', 'integer', null, array('type' => 'integer', 'notnull' => true));
    $this->hasColumn('country_id', 'integer', null, array('type' => 'integer', 'notnull' => true, 'default' => '1'));
    $this->hasColumn('postal', 'string', 5, array('type' => 'string', 'length' => '5'));
    $this->hasColumn('latitude', 'string', 20, array('type' => 'string', 'length' => '20'));
    $this->hasColumn('longitude', 'string', 20, array('type' => 'string', 'length' => '20'));
    $this->hasColumn('category_id', 'integer', null, array('type' => 'integer'));

    $this->option('collate', 'utf8_unicode_ci');
    $this->option('charset', 'utf8');
  }

  public function setUp()
  {
    $this->hasOne('AddressState as State', array('local' => 'state_id',
                                                 'foreign' => 'id',
                                                 'onUpdate' => 'CASCADE'));

    $this->hasOne('AddressCountry as Country', array('local' => 'country_id',
                                                     'foreign' => 'id',
                                                     'onUpdate' => 'CASCADE'));

    $this->hasOne('AddressCategory as Category', array('local' => 'category_id',
                                                       'foreign' => 'id',
                                                       'onDelete' => 'SET NULL',
                                                       'onUpdate' => 'CASCADE'));

    $this->hasOne('Entity', array('local' => 'entity_id',
                                  'foreign' => 'id',
                                  'onDelete' => 'CASCADE',
                                  'onUpdate' => 'CASCADE'));

    $timestampable0 = new Doctrine_Template_Timestampable();
    $referenceable0 = new Referenceable();
    $lsversionable0 = new LsVersionable();
    $softdelete0 = new Doctrine_Template_SoftDelete(array('name' => 'is_deleted'));
    $this->actAs($timestampable0);
    $this->actAs($referenceable0);
    $this->actAs($lsversionable0);
    $this->actAs($softdelete0);
  }
}