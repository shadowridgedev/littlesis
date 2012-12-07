<?php
/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class DonationTable extends Doctrine_Table
{

	static public function getDonationByFecId($fec_id){
		$query = Doctrine_Query::create();
		
		$query->from('Donation d');
		$query->where('d.fec_donation_id = ?', $fec_id);		
		
		return $query;
	}	


  static function consolidateRelationshipsByEntities($entity1Id, $entity2Id)
  {
    $rels = LsDoctrineQuery::create()
      ->from('Relationship r')
      ->leftJoin('r.FecFiling f')
      ->leftJoin('r.Reference ref ON (ref.object_model = \'Relationship\' AND ref.object_id = r.id)')
      ->where('r.entity1_id = ? AND r.entity2_id = ?', array($entity1Id, $entity2Id))
      ->andWhere('r.category_id = ?', RelationshipTable::DONATION_CATEGORY)
      ->andWhere('f.id IS NOT NULL')
      ->execute()
      ->getData();

    //can't consolidate if no relationships
    if (!count($rels))
    {
      return;
    }

    //keep array for relationships to delete
    $toDelete = array();

    //separate relationships with crp data from those without
    $crpRels = array();
    $otherRels = array();
    
    foreach ($rels as $rel)
    {
      $isCrp = false;
      
      foreach ($rel->FecFiling as $filing)
      {
        if ($filing['crp_cycle'] && $filing['crp_id'])
        {
          $isCrp = true;
          break;
        }
      }
      
      if ($isCrp)
      {
        $crpRels[] = $rel;
      }
      else
      {
        $otherRels[] = $rel;
      }
    }

    //if there are relationships with crp data, delete the others (and their filings and references)
    if (count($crpRels))
    {
      foreach ($otherRels as $rel)
      {
        foreach ($rel->FecFiling as $filing)
        {
          $toDelete[] = $filing;
        }
        
        foreach ($rel->Reference as $ref)
        {
          $toDelete[] = $ref;
        }
        
        $toDelete[] = $rel;
      }

      $rels = $crpRels;
    }
    
    //identify base relationship
    $baseRel = array_shift($rels);
    $baseRelFilingCount = count($baseRel->FecFiling->getData());

    //organize base relationship filings
    $baseFilings = array();
    $updateBaseRel = false;

    foreach ($baseRel->FecFiling as $filing)
    {
      $key = FecFilingTable::generateUniqueKey($filing);

      if (!isset($baseFilings[$key]))
      {
        $baseFilings[$key] = $filing;
      }
      else
      {
        $toDelete[] = $filing;
        $updateBaseRel = true;
      }
    }

    //transfer unique filings from remaining relationships
    foreach ($rels as $rel)
    {
      foreach ($rel->FecFiling as $filing)
      {
        $key = FecFilingTable::generateUniqueKey($filing);
        
        if (!isset($baseFilings[$key]))
        {
          $baseFilings[$key] = $filing;
          $updateBaseRel = true;
        }
        else
        {
          $toDelete[] = $filing;
        }
      }      
    }
    
    //change relationship_id of transfered filings
    foreach ($baseFilings as $filing)
    {
      if ($filing['relationship_id'] != $baseRel['id'])
      {
        $filing['relationship_id'] = $baseRel['id'];
        $filing->save();
      }
    }

    //change object_id of references
    foreach ($rels as $rel)
    {
      foreach ($rel->Reference as $ref)
      {
        $ref['object_id'] = $baseRel['id'];
        $ref->save();
      }
      
      $toDelete[] = $rel;
    }
    
    //delete duplicates
    foreach ($toDelete as $record)
    {
      $record->delete();
    }
        
    //update base relationship based on new filings, if there are new filings
    if ($updateBaseRel)
    {
      $baseRel->updateFromFecFilings();
    }
    
    return $baseRel;
  }
	
}