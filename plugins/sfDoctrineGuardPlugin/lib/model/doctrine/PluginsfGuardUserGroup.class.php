<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class PluginsfGuardUserGroup extends BasesfGuardUserGroup
{
  public function save(Doctrine_Connection $conn = null)
  {
    parent::save($conn);

    $this->getsfGuardUser($conn)->reloadGroupsAndPermissions();
  }
}