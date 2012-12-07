<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
abstract class BaseLobbyIssue extends sfDoctrineRecord
{
  public function setTableDefinition()
  {
    $this->setTableName('lobby_issue');
    $this->hasColumn('name', 'string', 50, array('type' => 'string', 'notnull' => true, 'notblank' => true, 'length' => '50'));

    $this->option('collate', 'utf8_unicode_ci');
    $this->option('charset', 'utf8');
  }

  public function setUp()
  {
    $this->hasMany('LobbyFiling', array('refClass' => 'LobbyFilingLobbyIssue',
                                        'local' => 'issue_id',
                                        'foreign' => 'lobby_filing_id'));

    $this->hasMany('LobbyFilingLobbyIssue', array('local' => 'id',
                                                  'foreign' => 'issue_id'));
  }
}