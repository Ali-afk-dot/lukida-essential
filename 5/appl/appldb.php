<?php

class appldb extends system
{
  
  
  
  public function __construct($f3)
  {
    parent::__construct($f3);
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);
  }

  
  
  

  
  
  

  protected function getLevelsDB()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    $Data = array('client' => $this->client);
    $SQL  = "select level from adminlevels where client = :client order by sort";

    $Rows = $this->execDB($SQL, $Data);
    $Levl = array();
    foreach ($Rows as $Row) 
    {
      $Levl[] = $Row["level"];
    }

    
    return $Levl;
  }


  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  protected function getApplTextsDB()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    $SQL = "select shortcut, german, english from " . $this->schemaOf(MODULE)
         . (MODULE == "admin" ? ".bibtexts" : ".adminbibtexts");

    try {
      $Rows = $this->execDB($SQL);
    } catch (\Throwable $Fehler) {
      return [];
    }

    return $this->textZeilen($Rows);
  }

  
  
  
  
  
  
  
  protected function getApplBatchTextsDB($Selection = [], $NotSelection = [], $Complete = false)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    $SQL = "SELECT shortcut, german, english FROM " . $this->schemaOf("hosting") . ".adminbibtexts";
    $Wo  = array();
    if (!$Complete && count($Selection))    $Wo[] = "( shortcut     like '" . implode("' OR shortcut like '", $Selection) . "')";
    if (!$Complete && count($NotSelection)) $Wo[] = "( shortcut not like '" . implode("' AND shortcut not like '", $NotSelection) . "')";
    if (count($Wo))  $SQL .= " WHERE " . implode(" AND ", $Wo);

    try {
      return $this->textZeilen($this->ExecDB($SQL));
    } catch (\Throwable $Fehler) {
      return [];
    }
  }

  
  
  private function textZeilen($Rows)
  {
    $Texts = [];
    foreach ((array) $Rows as $Row) {
      if (!isset($Row["shortcut"]))  continue;
      $Texts[$Row["shortcut"]] = array(
        "german"  => $Row["german"]  !== null && trim($Row["german"])  !== '' ? trim($Row["german"])  : "",
        "english" => $Row["english"] !== null && trim($Row["english"]) !== '' ? trim($Row["english"]) : ""
      );
    }
    return $Texts;
  }


  
  
  

  
  
  
}

?>