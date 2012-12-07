<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class Education extends BaseEducation
{
  public function getDetails()
  {
    $ret = '';
  
    if ($this->Degree->exists())
    {
      $ret .= $this->Degree->abbreviation ? $this->Degree->abbreviation : $this->Degree->name;
      
      if ($this->field)
      {
        $ret .= ' (' . $this->field . ')';
      }
    }

    return $ret;
  }
}