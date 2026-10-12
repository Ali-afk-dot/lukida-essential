<?php

class appl extends appldb
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  protected   $appl;
  protected   $areas;
  protected   $formats;
  public      $formatsBandlist;   // PAIA
  protected   $formatsMulti;
  protected   $getAreas;
  protected   $searchAreas;
  protected   $searchOptions;

  public function __construct($f3)
  {
    parent::__construct($f3);
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    $this->appl            = $this->f3->get('application.type');
    $this->dbpw            = "";
    $this->formats         = array("Article","Book","Serial Volume","Sound Recording",
                                   "Journal","Musical Score","Microform","Map","Motion Picture",
                                   "Monograph Series", "Manuscript","unknown","Game",
                                   "Projected Medium","Mixed Materials","Data Media","Picture");
    $this->formatsBandlist = array("Journal", "Serial Volume", "Monograph Series");
    $this->formatsMulti    = array("Book", "Journal", "Monograph Series", "Serial Volume", 
                                   "unknown", "Musical Score", "Sound Recording", "Map", 
                                   "Microform", "Motion Picture", "Manuscript");
    $this->getAreas        = array("export", "findex", "hierarchy", "print", "resolve");
    // LIGHT: "classis" (Klassifikationssysteme) und "norms" (Normdaten) entfernt — esx-107-Features
    $this->searchAreas     = array("collections", "facets", "findex", "isils");
    // "isn" = ISBN und ISSN zusammen (31.08.2026). Beide APIs kennen den
    // Schluessel laengst (solr.php, case "isn"), er stand nur nicht zur
    // Auswahl. Diese Liste speist BEIDE Oberflaechen: NewUI holt sie zur
    // Laufzeit ueber den Endpunkt "config", die alte UI bekommt sie beim Bau
    // als VITE_OPTIONAREAS - deshalb ist die Uebersetzung dort mitgezogen.
    $this->searchOptions   = array("", "person", "signature", "title", "isn");

    if ( ACTION != "batch" )
    {
      // Read Configs
      // Hinweis: Die Auswahllisten des Suchschlitzes ("option.*") stehen NICHT
      // in der configs-Tabelle - sie sind feste Werte dieser Klasse
      // ($searchOptions/$formats, s.o.) und werden fuer den Batch in
      // applhostingdb.php::getSpecialConfigsDB() zusammengesetzt. Der
      // config-Endpunkt liefert sie deshalb ueber
      // user.php::getClientOptions() direkt aus denselben Quellen - samt
      // fertiger Beschriftung. (20.08.2026)
      $ClientConfigs = array('account', 'api', 'bibliographic', 'client', 'color', 'facet', 'init', 'item',
                             'bms', 'link', 'meta', 'session', 'style', 'display');

      $this->getConfigs($ClientConfigs);

      // Read Texts
      if ( MODULE == "user")
      {
        // "infos.%" liefert die vier Bausteine der Infoseite (orgheadline,
        // orgtext, systemheadline, systemtext). Sie kamen bisher aus dem
        // .env-Textblock des Clients und werden jetzt zentral in der
        // Textverwaltung gepflegt (2026-08-10).
        $ClientTexts   = array('level.%', 'infos.%'); 
      }
      else
      {
        $ClientTexts   = array();
      }
      $this->getTexts($ClientTexts);

      // Bausteine, die der Client als Markdown rendert, hier fertig aufloesen.
      if ( MODULE == "user" )  $this->markdownTexts(array("infos."));
    }
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************
  protected function getApplConfigs()
  {
    if ( MODULE == "user")
    {
      return array("levels" => $this->getLevelsDB());
    }
    return array();
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function getCollectionFromPPN($PPN)
  {
     $FirstDigit      = substr(strpbrk($PPN, "0123456789"),0,1);
     $FirstDigitPos   = strpos($PPN, $FirstDigit);
     return ($FirstDigitPos) ? strtoupper(substr($PPN,0,$FirstDigitPos)) : "KXP";
  }

  public function getCollAndIDFromPPN($PPN)
  {
     $FirstDigit      = substr(strpbrk($PPN, "0123456789"),0,1);
     $FirstDigitPos   = strpos($PPN, $FirstDigit);
     return array( "col" => ($FirstDigitPos) ? strtoupper(substr($PPN,0,$FirstDigitPos)) : "KXP",
                   "id"  => ($FirstDigitPos) ? substr($PPN,$FirstDigitPos) : $PPN );
  }

  public function getHost($URL)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    if ( $Host = parse_url($URL,PHP_URL_HOST) )
    {
      if ( substr($Host,0,4) == "www.")                  return substr($Host,4);
      return $Host;
    }
  }

  public function batch($Params)
  {
    // Fill missing vars for batch mode
    if (ACTION != "batch")  return;

    if ( isset($Params["client"]) ) $this->client = $Params["client"];
    if ( isset($Params["lang"]) )   $this->lang   = $Params["lang"];
    if ( isset($Params["user"]) )   $this->user   = $Params["user"];

    // Config-Grupen für .env
    $this->configs = $this->getAdminConfigsDB();
    $this->batchconfigs = array('account', 'api',  'bibliographic', 'client', 'color',    'facet',   'init', 
                                'item',    'link', 'bms',           'meta',   'resolver', 'session', 'style');
  
  	// Server-Configs sind     'development',      'export',        'mail',   'mail.prod','mail.test', 
  	//                         'other',            'project',       'solr'

  
    // Texte für .env
    $this->texts             = $this->getAdminTextsDB();
    $this->batchtexts        = array();
    $this->batchtextexcludes = array("classlocal.%", "event.%", "family.%", "lang.%", "organisation.%", "person.%", "place.%", "role.%");
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}

?>