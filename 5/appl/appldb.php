<?php

class appldb extends system
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  public function __construct($f3)
  {
    parent::__construct($f3);
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************

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

    // Return value
    return $Levl;
  }


  // ─── Bibliothekstexte ────────────────────────────────────────────────────
  //
  // "lang.*" (Sprachnamen) und "bibline.*" (Beschriftungen der
  // bibliografischen Zeilen). Client-UNABHAENGIG (03.09.2026).
  //
  // Bis dahin standen sie doppelt herum: einmal zentral in hosting.appltexts
  // und zusaetzlich je Client in admin.texts - 8.027 Schluessel mal 33 Clients,
  // 61 % aller Zeilen dieser Tabelle. Jetzt liegen sie EINMAL in
  // admin.bibtexts; jedes Modul liest sie ueber die Sicht in seinem eigenen
  // Schema, schemauebergreifende Rechte braucht es dafuer nicht.
  //
  // Fehlt die Tabelle oder die Sicht (Migration noch nicht eingespielt), kommt
  // eine leere Menge zurueck - die Texte fehlen dann, aber nichts bricht.
  //
  // HIER und nicht in base (12.09.2026): welche Texte es gibt und wo sie
  // liegen, ist Sache der Anwendung. Base ruft die beiden Haken nur auf, wenn
  // es sie gibt (system.php::getTexts, systemdb.php::getBatchAdminTextsDB),
  // und kuemmert sich um Zwischenspeicher und Reihenfolge.
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

  // Dieselben Texte fuer das gebuendelte Ausliefern an einen Client.
  //
  // Das ALTE User-Modul setzt "bibline.<code>" im Browser selbst zusammen und
  // bekommt seine Texte aus der gebauten .env - ohne diesen Zusatz blieben
  // dort nach der Verlagerung alle Zeilenbeschriftungen leer. Dieselben Filter
  // wie bei den Clienttexten, damit "lang.%" weiterhin draussen bleibt (rund
  // 7.955 Schluessel, die kein Client im Buendel braucht).
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

  // Zeilen aus shortcut/german/english in die Gestalt bringen, die getText()
  // erwartet. Beide Haken oben liefern dieselbe Form - deshalb einmal hier.
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


  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}

?>