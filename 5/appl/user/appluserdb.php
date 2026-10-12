<?php

class appluserdb extends systemuser
{
  // Nachschlagewerke (Klassifikationen, Schlagwortsysteme, GND) aendern sich
  // selten - 30 Tage sind reichlich und begrenzen zugleich den Schaden, falls
  // sich doch etwas aendert. Steht in DIESER Klasse, weil sowohl getNormsDB()
  // hier als auch getCDBClass() in der abgeleiteten Klasse sie brauchen
  // (03.09.2026).
  const LOOKUP_CACHE_MAXALTER = 2592000;

  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  public function __construct($f3)
  {
    parent::__construct($f3);
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************
  protected function getDashboardDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Results = array();

    // Cockpit

    // Blocks

    // Return fetched data
    return $Results;
  }


  // LIGHT: getPushMessagesDB entfernt (Push-Dienste nicht unterstützt)


  protected function getResolversDB($PPN)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $SQL  = "SELECT data, more FROM resolvers where client = :client and ppn = :ppn  limit 1";     // and checkdate >= :checkdate  ALX
    $Data = array(
      "client"    => $this->client,
      "ppn"       => $PPN
      // "checkdate" => date("Y-m-d", mktime(0, 0, 0, date("m")-3, date("d"),date("Y")))        ALX
    );
    $Result = $this->ExecDB($SQL, $Data, true);
    if (count($Result)) {
      $Result = (array) json_decode($Result[0]["data"], true);
    }
    return $Result;
  }

  protected function setResolversDB($PPN, $Data, $More)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if ($PPN == "")  return (-1);

    // Zu lange Kennung gar nicht erst schreiben (09.09.2026).
    //
    // resolve() nimmt "id" unveraendert aus dem POST und prueft nur, dass sie
    // nicht leer ist. Die Spalte ppn der zentralen Tabelle ist varchar(50);
    // laenger, und der Eintrag scheitert mit "1406 Data too long for column
    // 'ppn'" - so gemeldet am 07.09.2026 von esx-35. Gemessen an den 1.245.176
    // vorhandenen Zeilen ist die laengste echte Kennung 28 Zeichen lang, ein
    // laengerer Wert kann also keinen Datensatz treffen.
    //
    // Nur der Zwischenspeicher wird uebersprungen, nicht die Antwort: die
    // entsteht ohnehin frisch, und Aufrufer sollen von einem Speicherproblem
    // nichts merken. Rueckgabe -1 wie beim leeren Wert - der Aufrufer wertet
    // sie nicht aus.
    if (strlen($PPN) > 50)
    {
      $this->debug("LOG", "setResolversDB: Kennung mit " . strlen($PPN)
                        . " Zeichen uebersprungen (Spalte ppn fasst 50)");
      return (-1);
    }

    // "Einfuegen, sonst ersetzen". MySQL loescht bei REPLACE die alte Zeile
    // und legt eine neue an (die Kennung springt), PostgreSQL aktualisiert
    // ueber ON CONFLICT die vorhandene - fuer diesen Zwischenspeicher ist
    // beides gleichwertig. Eindeutiger Schluessel ist (client, ppn).
    $SQL  = "insert into resolvers (client, ppn, data, more, checkdate)"
          . " values (:client, :ppn, :data, :more, :checkdate)";
    $SQL .= $this->isPG(true)
          ? " on conflict (client, ppn) do update set data = EXCLUDED.data,"
            . " more = EXCLUDED.more, checkdate = EXCLUDED.checkdate"
          : " on duplicate key update data = :data2, more = :more2, checkdate = :checkdate2";
    $Data = array(
      "client"    => $this->client,
      "ppn"       => $PPN,
      "data"      => json_encode($Data, true),
      "more"      => json_encode($More, true),
      "checkdate" => date("Y-m-d H:i:s")
    );
    // MySQL kann denselben benannten Wert nicht zweimal binden - im
    // Aktualisierungsteil stehen deshalb Zweitnamen (Hausbrauch, vgl.
    // ":lastlogin2"). PostgreSQL braucht sie nicht, und ueberzaehlige
    // Parameter wuerde PDO dort bemaengeln.
    if ( !$this->isPG(true) )
    {
      $Data["data2"]      = $Data["data"];
      $Data["more2"]      = $Data["more"];
      $Data["checkdate2"] = $Data["checkdate"];
    }
    return ($this->ExecDB($SQL, $Data, true));
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************

  // Zielmailadresse EINES Dialogs (Tabelle lukida_admin.dialogs, hier ueber den
  // root-eigenen View lukida_user.admindialogs - schemauebergreifende GRANTs
  // gibt es zwischen den Lukida-Datenbanken bewusst nicht).
  //
  // Bis 11.08.2026 ging jede Bestellung aus einem Dialog an dieselbe Adresse:
  // "mail.<mode>.<MailType>" aus der Client-Konfiguration, wobei die
  // clienteigene action.php den MailType fest vorgab. Welcher Dialog benutzt
  // wurde, war fuer das Ziel ohne Belang.
  //
  // Gesucht wird ueber den Aktionstyp, weil genau der vom Client mitgeschickt
  // wird (action.type); der Dialogcode steht dem Server nicht zur Verfuegung.
  // Getrennte Spalten fuer prod und test wie bei "mail.prod"/"mail.test" -
  // sonst gingen Testbestellungen an die echte Bibliotheksadresse.
  // Leerer Wert = kein eigenes Ziel, der Aufrufer faellt auf die Konfiguration
  // zurueck. Auch ein fehlender View fuehrt hierher (execDB liefert dann []),
  // das Verhalten bleibt also das bisherige.
  public function getDialogMailDB($Type)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!trim((string) $Type))  return "";

    // Fehlt der View (noch nicht eingespielt), NICHT blind abfragen: execDB()
    // verschickt bei einer PDOException eine Fehlermail - das waere eine Mail
    // je Bestellung. Stattdessen einmal nachsehen und still zurueckfallen.
    $Exists = $this->ExecDB("SELECT 1 AS ok FROM information_schema.TABLES"
                          . " WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'admindialogs'",
                            array("schema" => $this->schemaName("user")));
    if (!count($Exists))  return "";

    $Column = (MODE == "production") ? "mailprod" : "mailtest";
    $SQL    = "SELECT " . $Column . " AS mail";
    $SQL   .= " FROM " . $this->schemaOf("user") . ".admindialogs";
    $SQL   .= " WHERE client = :client AND active = :active";
    // JSON-Zugriff: MySQL braucht JSON_UNQUOTE(JSON_EXTRACT(...)),
    // PostgreSQL schreibt dafuer den Operator ->> (2026-08-14).
    $SQL   .= $this->isPG()
            ? " AND action->>'type' = :type"
            : " AND JSON_UNQUOTE(JSON_EXTRACT(action, '$.type')) = :type";
    $Rows   = $this->ExecDB($SQL, array("client" => $this->client, "active" => "y", "type" => trim($Type)));

    return (count($Rows) == 1 && isset($Rows[0]["mail"])) ? trim($Rows[0]["mail"]) : "";
  }

  // Ist eine Spalte im View/der Tabelle vorhanden? Gebraucht fuer Spalten, die
  // ueber eine Migration dazukommen: waere sie im SELECT enthalten, bevor der
  // View sie fuehrt, wuerde die ganze Abfrage scheitern - execDB() faengt die
  // PDOException zwar ab, liefert dann aber [] , und es gaebe ueberhaupt keine
  // Faelle mehr. Ergebnis wird je Anfrage gemerkt (2026-08-11).
  private $columncache = array();

  private function hasColumnDB($Table, $Column)
  {
    $Key = $Table . "." . $Column;
    if (isset($this->columncache[$Key]))  return $this->columncache[$Key];

    $Rows = $this->ExecDB("SELECT 1 AS ok FROM information_schema.COLUMNS"
                        . " WHERE table_schema = :schema AND table_name = :tbl AND column_name = :col",
                          array("schema" => $this->schemaName("user"),
                                "tbl"    => $Table,
                                "col"    => $Column));
    return ($this->columncache[$Key] = (count($Rows) > 0));
  }

  // Dialoge dieses Clients samt Feldern, so wie User2026 sie zum Aufbau der
  // gemeinsamen Dialogkomponente braucht (2026-08-11). Bis dahin steckten die
  // Masken fest in je einer Vue-Komponente des Altmoduls (ItemDlgLibrary,
  // ItemDlgDelivery, ItemDlgDelivery2 ...).
  //
  // Zielmailadressen werden hier bewusst NICHT mitgeliefert - sie gehen den
  // Browser nichts an und werden serverseitig aufgeloest (getDialogMailDB).
  // Fehlt der View noch, bleibt die Liste leer und der Client verhaelt sich
  // wie bisher.
  public function getDialogsDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!$this->hasColumnDB("admindialogfields", "dialogcode"))  return array();

    $SQL  = "SELECT dialogcode, fieldcode, seq, fieldtype, fieldoptions, editable, autofill, rules";
    $SQL .= " FROM " . $this->schemaOf("user") . ".admindialogfields";
    $SQL .= " WHERE client = :client AND active = :active ORDER BY dialogcode, seq";
    $Rows = $this->ExecDB($SQL, array("client" => $this->client, "active" => "y"));

    $Dias = array();
    foreach ($Rows as $Row) {
      $Opts = (array) json_decode($Row["fieldoptions"], true);

      // Auswahlbeschriftungen serverseitig aufloesen (19.08.2026): eine Option
      // traegt "textcode" (Textschluessel) und "value" (Vorgabe). Bisher loeste
      // der Client den Code ueber config.t(); jetzt kommt die fertige
      // Beschriftung der angeforderten Sprache als "label" mit - der Client
      // muss die Texte nicht mehr selbst kennen.
      if ( isset($Opts["options"]) && is_array($Opts["options"]) ) {
        foreach ($Opts["options"] as &$Opt) {
          if ( isset($Opt["textcode"]) && trim($Opt["textcode"]) !== "" ) {
            $T = $this->getText($Opt["textcode"]);
            $Opt["label"] = ($T !== "") ? $T : (isset($Opt["value"]) ? $Opt["value"] : "");
          } elseif ( isset($Opt["value"]) ) {
            $Opt["label"] = $Opt["value"];
          }
        }
        unset($Opt);
      }

      $Dias[$Row["dialogcode"]]["code"]     = $Row["dialogcode"];
      $Dias[$Row["dialogcode"]]["fields"][] = array(
        "fieldcode"    => $Row["fieldcode"],
        "label"        => $this->getText($Row["fieldcode"]),
        "seq"          => (int) $Row["seq"],
        "fieldtype"    => $Row["fieldtype"],
        "fieldoptions" => $Opts,
        "editable"     => $Row["editable"] == "y",
        "autofill"     => $Row["autofill"] == "y",
        "rules"        => (array) json_decode($Row["rules"], true),
      );
    }
    return $Dias;
  }

  public function getAdminCasesDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      'client'    => $this->client,
      'active'    => 'y'
    );
    $View = "admincases";
    $Has5 = $this->hasColumnDB($View, "detailaction5");
    $SQL  = "SELECT seq, name, active, filters, light, itemcomments, itembutton, itemicon, detailwindow, detailinfos, detailbutton, detailaction, ids, comment";
    if ($Has5)  $SQL .= ", detailaction5";
    $SQL .= " FROM " . $this->schemaOf("user") . ".admincases";
    $SQL .= " WHERE client = :client and active = :active order by seq, name";
    $Rows = $this->ExecDB($SQL, $Data);

    $Cases = array();
    foreach ($Rows as $Row) {
      if (!isset($Row["seq"]))  continue;

      $Cases[$Row["seq"]][] = array(
        "name"         => $Row["name"],
        "active"       => ($Row["active"] == "y") ? true : false,
        "filters"      => json_decode($Row["filters"], true),
        "light"        => $Row["light"],
        "itemcomments" => json_decode($Row["itemcomments"], true),
        "itembutton"   => $Row["itembutton"],
        "itemicon"     => $Row["itemicon"],
        "detailwindow" => $Row["detailwindow"],
        "detailinfos"  => json_decode($Row["detailinfos"], true),
        "detailbutton" => $Row["detailbutton"],
        // API 5 arbeitet mit der neuen Steuerspalte: dort steht fuer Dialoge
        // einheitlich "o.dialog.<code>", waehrend "detailaction" die Altwerte
        // (o.mailorder, o.delivery ...) fuer das Altmodul unveraendert behaelt.
        // Ist sie leer - etwa weil die Migration noch nicht eingespielt ist -,
        // gilt weiterhin die alte Spalte (2026-08-11).
        "detailaction" => (isset($Row["detailaction5"]) && trim($Row["detailaction5"]))
                            ? $Row["detailaction5"] : $Row["detailaction"],
        "ids"          => $Row["ids"],
        "comment"      => $Row["comment"]
      );
    }
    return $Cases;
  }

  public function getAdminCasesMediaDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      'client'    => $this->client,
      'active'    => 'y'
    );
    $View = "admincasesmedia";
    $Has5 = $this->hasColumnDB($View, "detailaction5");
    $SQL  = "SELECT seq, name, active, filters, light, itemcomments, itembutton, itemicon, detailwindow, detailinfos, detailbutton, detailaction, ids, comment";
    if ($Has5)  $SQL .= ", detailaction5";
    $SQL .= " FROM " . $this->schemaOf("user") . ".admincasesmedia";
    $SQL .= " WHERE client = :client and active = :active order by seq, name";
    $Rows = $this->ExecDB($SQL, $Data);

    $Cases = array();
    foreach ($Rows as $Row) {
      if (!isset($Row["seq"]))  continue;

      $Cases[$Row["seq"]][] = array(
        "name"         => $Row["name"],
        "active"       => ($Row["active"] == "y") ? true : false,
        "filters"      => json_decode($Row["filters"], true),
        "light"        => $Row["light"],
        "itemcomments" => json_decode($Row["itemcomments"], true),
        "itembutton"   => $Row["itembutton"],
        "itemicon"     => $Row["itemicon"],
        "detailwindow" => $Row["detailwindow"],
        "detailinfos"  => json_decode($Row["detailinfos"], true),
        "detailbutton" => $Row["detailbutton"],
        // API 5 arbeitet mit der neuen Steuerspalte: dort steht fuer Dialoge
        // einheitlich "o.dialog.<code>", waehrend "detailaction" die Altwerte
        // (o.mailorder, o.delivery ...) fuer das Altmodul unveraendert behaelt.
        // Ist sie leer - etwa weil die Migration noch nicht eingespielt ist -,
        // gilt weiterhin die alte Spalte (2026-08-11).
        "detailaction" => (isset($Row["detailaction5"]) && trim($Row["detailaction5"]))
                            ? $Row["detailaction5"] : $Row["detailaction"],
        "ids"          => $Row["ids"],
        "comment"      => $Row["comment"]
      );
    }
    return $Cases;
  }

  public function getAdminLevelsDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // "holdingshaptic" kommt ueber eine Migration dazu (09.09.2026,
    // 60_spalte_holdingshaptic.sql). Stuende es im SELECT, bevor die Sicht es
    // fuehrt, scheiterte die GANZE Abfrage - execDB() faengt die Ausnahme zwar
    // ab, liefert dann aber [], und es gaebe ueberhaupt keine Suchraeume mehr.
    // Bis die Migration eingespielt ist, verhaelt sich der Server wie bisher.
    $HasHaptic = $this->hasColumnDB("adminlevels", "holdingshaptic");

    $SQL  = "SELECT level, collectionsaddfull, collectionsadd, collectionsrem, holdingilns, holdingisils, locations, classlocals, buildings, dois, ipranges";
    if ($HasHaptic)  $SQL .= ", holdingshaptic";
    $SQL .= " FROM " . $this->schemaOf("user") . ".adminlevels where client=:client";
    $Data = array(
      "client" => $this->client,
    );
    $Rows = $this->ExecDB($SQL, $Data);

    $Levels = array();
    foreach ($Rows as $Row) {
      // "ipranges" ist eine JSON-Liste (Spaltentyp jsonb), keine
      // Komma-Zeichenkette wie holdingilns und Geschwister - das Admin-Modul
      // legt sie so ab (MultiSelectField.vue: "collections* und ipranges als
      // jsonb-Feld"), und hosting.fields fuehrt sie als type "json".
      //
      // Zerlegt wurde sie hier trotzdem mit explode(","). Aus dem Leerwert "[]"
      // wurde damit die einelementige Liste ["[]"], und die zaehlt in
      // countLevelFilters() als GEFUELLTES Filterkriterium - obwohl der
      // Kommentar dort ausdruecklich sagt, leere JSON-Listen zaehlten nicht
      // mit. Sichtbar war es an der Stufenzaehlung: MagdeburgMZB meldete
      // "start" mit 3 Kriterien und "all" mit 1, obwohl "all" gar nicht
      // filtert (09.09.2026).
      //
      // Bisher folgenlos, weil der Aufschlag jede Stufe gleich trifft und kein
      // Client IP-Bereiche pflegt - nachgesehen, alle Zeilen stehen auf "[]".
      // Mit dem ersten gepflegten Bereich waere eine Stufe MIT von einer OHNE
      // nicht mehr zu unterscheiden gewesen, und die Oberflaeche haette die
      // falsche als "naechstweitere" vorgeschlagen.
      $IPRanges = json_decode((string) $Row["ipranges"], true);
      if (!is_array($IPRanges))  $IPRanges = array();

      $Levels[$Row["level"]] = array(
        "collectionsaddfull" => json_decode($Row["collectionsaddfull"], true),
        "collectionsadd"     => json_decode($Row["collectionsadd"], true),
        "collectionsrem"     => json_decode($Row["collectionsrem"], true),
        "ipranges"           => $IPRanges,
        "holdingilns"        => $Row["holdingilns"]  !== null && trim($Row["holdingilns"]) !== ''   ? explode(",", trim($Row["holdingilns"]))  : array(),
        "holdingisils"       => $Row["holdingisils"] !== null && trim($Row["holdingisils"]) !== ''   ? explode(",", trim($Row["holdingisils"])) : array(),
        // Bestaende, die NUR mit haptischem Titel zaehlen (09.09.2026).
        "holdingshaptic"     => $HasHaptic && $Row["holdingshaptic"] !== null && trim($Row["holdingshaptic"]) !== '' ? explode(",", trim($Row["holdingshaptic"])) : array(),
        "locations"          => $Row["locations"]    !== null && trim($Row["locations"]) !== ''   ? explode(",", trim($Row["locations"]))    : array(),
        "classlocals"        => $Row["classlocals"]  !== null && trim($Row["classlocals"]) !== ''   ? explode(",", trim($Row["classlocals"]))  : array(),
        "buildings"          => $Row["buildings"]    !== null && trim($Row["buildings"])    !== ''  ? explode(",", trim($Row["buildings"]))    : array(),
        "dois"               => $Row["dois"]         !== null && trim($Row["dois"]) !== ''   ? explode(",", trim($Row["dois"]))         : array()
      );
    }
    return $Levels;
  }

  public function getAdminTextDB($Shortcut)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      'client'    => $this->client,
      'shortcut'  => $Shortcut
    );
    $SQL  = "SELECT german, english FROM " . $this->schemaOf("user") . ".admintexts";
    $SQL .= " WHERE client = :client and shortcut = :shortcut";

    // Return fetched data
    return (array) $this->execDB($SQL, $Data);
  }

  // Wartungsfenster der BMS - FRISCH aus der Datenbank (04.09.2026).
  //
  // Bewusst nicht ueber getAdminBMSDB() und dessen Zwischenspeicher: eine
  // Wartung wird kurz vorher eingetragen und soll sofort gelten, nicht erst
  // nach dem naechsten Tageswechsel. Die Abfrage ist klein und laeuft nur
  // beim config-Aufruf, also einmal je Sitzung.
  // Allgemeine Meldungen dieses Clients - FRISCH aus der Datenbank
  // (04.09.2026), aus demselben Grund wie beim Wartungsfenster: sie werden
  // kurzfristig eingetragen und sollen sofort erscheinen, nicht erst nach dem
  // naechsten Tageswechsel.
  //
  // Gefiltert wird gleich in der Abfrage auf den laufenden Zeitraum - was
  // vorbei ist, darf als Zeile stehen bleiben.
  public function getMessagesDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $SQL  = "SELECT id, message FROM " . $this->schemaOf("user") . ".adminmessages";
    $SQL .= " WHERE client = :client AND datefrom <= :jetzt AND dateto >= :jetzt2";
    $SQL .= " ORDER BY datefrom, id";

    $Jetzt = date("Y-m-d H:i:s");
    $Rows  = $this->ExecDB($SQL, array("client" => $this->client, "jetzt" => $Jetzt, "jetzt2" => $Jetzt));

    $Out = array();
    foreach ($Rows as $Row) {
      $Text = trim((string) $Row["message"]);
      if ($Text === "") continue;
      $Out[] = array("id" => (string) $Row["id"], "text" => $Text);
    }
    return $Out;
  }

  public function getMaintenanceDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Ohne Filter auf "active": die Sicht user.adminbms fuehrt diese Spalte
    // gar nicht, das Benutzermodul sieht sie also nirgends (auch
    // getAdminBMSDB() filtert nur "testonly"). Gepflegt wird das Fenster
    // ohnehin nur an aktiven BMS - die Maske in Admin2026 bietet keine
    // anderen an.
    $SQL  = "SELECT bmsid, options FROM " . $this->schemaOf("user") . ".adminbms";
    $SQL .= " WHERE client = :client";
    if (MODE == "production") $SQL .= " AND testonly = 'n'";
    $SQL .= " ORDER BY bmsid";

    $Rows = $this->ExecDB($SQL, array("client" => $this->client));

    $Out = array();
    foreach ($Rows as $Row) {
      $Opt = json_decode((string) $Row["options"], true);
      if (!is_array($Opt) || !isset($Opt["maintenance"]) || !is_array($Opt["maintenance"])) continue;
      $Out[(string) $Row["bmsid"]] = $Opt["maintenance"];
    }
    return $Out;
  }

  public function getAdminBMSDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array('client'    => $this->client);
    $SQL  = "SELECT bmsid, namecode, type, isil, iln, apitest, apiprod, options, logins";
    $SQL .= " FROM " . $this->schemaOf("user") . ".adminbms";
    $SQL .= " WHERE client = :client";
    if (MODE == "production") $SQL .=  " and testonly = 'n'";

    $Data = array(
      "client" => $this->client,
    );
    $Rows = $this->ExecDB($SQL, $Data);

    // Return fetched data
    $BMS = array();
    foreach ($Rows as $Row) {
      $BMS[$Row["bmsid"]] = array(
        "namecode" => $Row["namecode"],
        "type"     => $Row["type"],
        "isil"     => $Row["isil"],
        "iln"      => $Row["iln"],
        "apitest"  => $Row["apitest"],
        "apiprod"  => $Row["apiprod"],
        "options"  => json_decode($Row["options"], true)
      );
      if (in_array(MODE, array("local", "development"))) $BMS[$Row["bmsid"]] += array("logins"   => json_decode($Row["logins"], true));
    }
    return $BMS;
  }

  public function getAdminLinksDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array('client'    => $this->client);
    $SQL  = "SELECT shortcut, german, english, link FROM " . $this->schemaOf("user") . ".adminlinks";
    $SQL .= " WHERE client = :client";

    $Data = array(
      "client" => $this->client,
    );
    $Rows = $this->ExecDB($SQL, $Data);

    // Return fetched data
    $Links = array();
    foreach ($Rows as $Row) {
      $Tmp = (isset($Row["link"]) && trim($Row["link"])) ? explode(",", trim($Row["link"])) : array();
      if (!isset($Tmp[0]))  continue;
      if (!isset($Tmp[1]))  $Tmp[1] = $Tmp[0];
      $Links[$Row["shortcut"]] = array(
        "german"      => $Row["german"]  !== null && trim($Row["german"])  !== '' ? trim($Row["german"])  : "",
        "germanlink"  => $Tmp[0],
        "english"     => $Row["english"] !== null && trim($Row["english"]) !== '' ? trim($Row["english"]) : "",
        "englishlink" => $Tmp[1]
      );
    }
    return $Links;
  }

  public function getCollectionsDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $SQL  = "SELECT collection, name, link FROM collections";
    $Data = array();
    $Rows = $this->ExecDB($SQL, $Data, true);

    $Collections = array();
    foreach ($Rows as $Row) {
      $Col = strtoupper($Row["collection"]);
      $Collections[$Col] = array(
        "name" => $Row["name"] !== null && trim($Row["name"]) !== '' ? trim($Row["name"]) : "",
        "link" => $Row["link"] !== null && trim($Row["link"]) !== '' ? trim($Row["link"]) : ""
      );
    }
    return $Collections;
  }

  public function getClassisDB($Search)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $All  = (isset($Search["search"]["type"])   && strtolower(trim($Search["search"]["type"])) == "code") ? false                       : true;
    $Syst = (isset($Search["search"]["system"]) && trim($Search["search"]["system"]))                     ? $Search["search"]["system"] : "";
    $Code = (isset($Search["search"]["code"])   && trim($Search["search"]["code"]))                       ? $Search["search"]["code"]   : "";
    $Quer = (isset($Search["search"]["query"])  && trim($Search["search"]["query"]))                      ? $Search["search"]["query"]  : "";

    if (($All && ! $Quer) || (!$All && !$Code)) return array();

    $SQL  = "SELECT shortcut, name, category, link, version, code, description, parents, description_eng, parents_eng FROM classis";
    $SQL .= ($All)  ? " where search like :query" : " where code=:code";
    $SQL .= ($Syst) ? " and shortcut=:system"     : "";
    $SQL .= ($All)  ? " order by shortcut, code"  : "";
    $SQL .= $this->sqlLimit(true);
    $Data = array(
      "start"  => $Search["start"],
      "limit"  => $Search["limit"]
    );
    if ($Syst) {
      $Data += array(
        "system" => $Syst
      );
    }
    if ($All) {
      $Data += array(
        "query"  => "%" . $Quer . "%"
      );
    } else {
      $Data += array(
        "code"   => $Code
      );
    }
    $Rows = $this->ExecDB($SQL, $Data, true);
    $Data = array();
    foreach ($Rows as $Row) {
      $Data[] = array(
        "shortcut"    => $Row["shortcut"],
        "name"        => $Row["name"],
        "category"    => $Row["category"],
        "link"        => $Row["link"],
        "description" => ($this->lang == 'e')
          ? $Row["description_eng"]
          : $Row["description"],
        "code"        => $Row["code"],
        "parents"     => ($this->lang == 'e' && $Row["parents_eng"])
          ? json_decode($Row["parents_eng"], true)
          : json_decode($Row["parents"], true)
      );
    }
    return $Data;
  }

  public function findCollectionsDB($Search)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $All  = (isset($Search["search"]["type"])   && strtolower(trim($Search["search"]["type"])) == "code") ? false                       : true;
    $Code = (isset($Search["search"]["code"])   && trim($Search["search"]["code"]))                       ? $Search["search"]["code"]   : "";
    $Quer = (isset($Search["search"]["query"])  && trim($Search["search"]["query"]))                      ? $Search["search"]["query"]  : "";

    if (($All && ! $Quer) || (!$All && !$Code)) return array();

    $SQL  = "SELECT collection, name, link FROM collections";
    $SQL .= ($All)  ? " where search like :query" : " where collection=:code";
    $SQL .= ($All)  ? " order by collection"  : "";
    $SQL .= $this->sqlLimit(true);
    $Data = array(
      "start"  => $Search["start"],
      "limit"  => $Search["limit"]
    );
    if ($All) {
      $Data += array(
        "query"  => "%" . $Quer . "%"
      );
    } else {
      $Data += array(
        "code"   => $Code
      );
    }
    $Rows = $this->ExecDB($SQL, $Data, true);
    $Data = array();
    foreach ($Rows as $Row) {
      $Data[] = array(
        "collection"  => $Row["collection"],
        "name"        => $Row["name"],
        "link"        => $Row["link"]
      );
    }
    return $Data;
  }

  public function getIsilsDB($Search)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $All  = (isset($Search["search"]["type"])   && strtolower(trim($Search["search"]["type"])) == "code") ? false                       : true;
    $Code = (isset($Search["search"]["code"])   && trim($Search["search"]["code"]))                       ? $Search["search"]["code"]   : "";
    $Quer = (isset($Search["search"]["query"])  && trim($Search["search"]["query"]))                      ? $Search["search"]["query"]  : "";

    if (($All && ! $Quer) || (!$All && !$Code)) return array();

    $SQL  = "SELECT isil, name, type, infos FROM isils";
    $SQL .= ($All)  ? " where search like :query" : " where isil=:code";
    $SQL .= ($All)  ? " order by isil"  : "";
    $SQL .= $this->sqlLimit(true);
    $Data = array(
      "start"  => $Search["start"],
      "limit"  => $Search["limit"]
    );
    if ($All) {
      $Data += array(
        "query"  => "%" . $Quer . "%"
      );
    } else {
      $Data += array(
        "code"   => $Code
      );
    }
    $Rows = $this->ExecDB($SQL, $Data, true);
    $Data = array();
    foreach ($Rows as $Row) {
      $Data[] = array(
        "isil"  => $Row["isil"],
        "name"  => $Row["name"],
        "type"  => json_decode($Row["type"], true),
        "infos" => json_decode($Row["infos"], true)
      );
    }
    return $Data;
  }

  public function getNormsDB($Type, $GNDID)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Wie bei getCDBClass(): die Auskunft kommt aus der ZENTRALEN Datenbank auf
    // einem anderen Rechner und ist weder client- noch satzabhaengig - eine
    // GND-Nummer bedeutet ueberall dasselbe. Deshalb derselbe gemeinsame,
    // dauerhafte Bestand; auch die Fehlanzeige wird gemerkt, sonst kostet
    // gerade sie jedes Mal aufs Neue (03.09.2026).
    $Datei = $this->prepareLookupCachePath("gnd_" . $Type, $GNDID);
    if (file_exists($Datei) && time() - (int) @filemtime($Datei) < self::LOOKUP_CACHE_MAXALTER) {
      if (MODE != "production") $this->updStatDB("Cache", "Norms.yes");
      $Gemerkt = json_decode(file_get_contents($Datei), true);
      if (is_array($Gemerkt)) return $Gemerkt;
    }
    if (MODE != "production") $this->updStatDB("Cache", "Norms.no");

    $SQL  = "SELECT data, more from entityfacts where type = :type and gndid = :gndid";
    $Data = array(
      "type"  => $Type,
      "gndid" => $GNDID
    );
    $Rows = $this->ExecDB($SQL, $Data, true);

    $Erg = count($Rows)
           ? array("data" => json_decode($Rows[0]["data"], true),
                   "more" => json_decode($Rows[0]["more"], true))
           : array();

    @file_put_contents($Datei, json_encode($Erg, true));
    @chmod($Datei, 0666);

    return $Erg;
  }

  public function getMailDB($User)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!isset($User))  return (-1);

    $U    = $this->ident("user");
    $SQL  = "select id, " . $U . ", type, subject, message, send, data from mails";
    $SQL .= " where client = :client and " . $U . " = :user";
    // $SQL .= " and send >= NOW() - INTERVAL 90 DAY";
    $SQL .= " order by send desc";
    $Data = array(
      "client"  => $this->client,
      "user"    => $this->getUserHash($User),
    );
    $Rows = $this->ExecDB($SQL, $Data);

    $Data = array();
    foreach ($Rows as $Row) {
      $Data[] = array(
        "id"      => $Row["id"],
        "user"    => $Row["user"],
        "type"    => $Row["type"],
        "subject" => $Row["subject"],
        "message" => $Row["message"],
        "send"    => $Row["send"],
        "data"    => json_decode($Row["data"], true)
      );
    }
    return $Data;
  }

  // GDPR retention: one row per client+patron, holding only the hash and the
  // last login date - this is the sole record a daily cleanup batch (see
  // batches/CleanupInactiveUsers.sh) checks to decide whether historys/
  // profiles/favors/mails for that patron are older than the 1-year
  // inactivity retention period and must be deleted.
  public function updUserLoginDB($Login)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!isset($Login) || !trim($Login))  return (-1);

    $U    = $this->ident("user");
    $SQL  = "insert into users (client, " . $U . ", lastlogin) values (:client, :user, :lastlogin)";
    $SQL .= $this->sqlDup("lastlogin = :lastlogin2", array("client", "user"));
    $Data = array(
      "client"    => $this->client,
      "user"      => $this->getUserHash($Login),
      "lastlogin" => date("Y-m-d H:i:s")
    );
    $Data["lastlogin2"] = $Data["lastlogin"];
    return ($this->ExecDB($SQL, $Data));
  }

  public function storeMailDB($User, $Data, $Type, $Other = array())
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // $this->debug("SMDB", $User);
    // $this->debug("SMDB", $Data);

    if (!isset($User["username"]) || !trim($User["username"]))  return (-1);

    $SQL  = "insert into mails ( client,  " . $this->ident("user") . ",  type,  subject,  message,  data)";
    $SQL .=           " values (:client, :user, :type, :subject, :message, :data)";
    $Data = array(
      "client"  => $this->client,
      "user"    => $this->getUserHash($User["username"]),
      "type"    => $Type,
      "subject" => $Data["subject"],
      "message" => $Data["message"],
      "data"    => json_encode($Other, true)
    );
    return ($this->ExecDB($SQL, $Data));
  }

  public function removeMailsDB($User)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!isset($User) || !trim($User))  return (-1);

    $SQL  = "delete from mails where client = :client and " . $this->ident("user") . " = :user";
    $Data = array(
      "client"  => $this->client,
      "user"    => $this->getUserHash($User),
    );
    return ($this->ExecDB($SQL, $Data));
  }

  public function storeProjectDB($Project, $PPN, $Final, $Score)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!isset($Project) || !isset($PPN) || count($Final) != count($Score))  return (-1);

    // "projects" hat ausser der Kennung keinen eindeutigen Schluessel - REPLACE
    // fuegt dort also ohnehin nur ein. Unter PostgreSQL steht deshalb schlicht
    // INSERT; ein ON CONFLICT haette kein Ziel (2026-08-14).
    $SQL  = ($this->isPG() ? "insert" : "replace") . " into projects (client, project, sessionid, ppn";
    for ($I = 1; $I <= 50; $I++) {
      $SQL .= ", ppn" . str_pad($I, 2, "0", STR_PAD_LEFT);
      $SQL .= ", ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "score";
      $SQL .= ", ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "count";
    }
    $SQL .= ", clientip, screen, createdt, updatedt) values (:client, :project, :sessionid, :ppn";
    for ($I = 1; $I <= 50; $I++) {
      $SQL .= ", :ppn" . str_pad($I, 2, "0", STR_PAD_LEFT);
      $SQL .= ", :ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "score";
      $SQL .= ", :ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "count";
    }
    $SQL .= ", :clientip, :screen, :createdt, :updatedt)";

    $Data = array(
      "client"    => $this->client,
      "project"   => $Project,
      "sessionid" => $this->checkPostedStr("tokens", "session"),
      "ppn"       => $PPN,
      "clientip"  => $this->clientip,
      "screen"    => $this->checkPostedStr("project", "screen"),
      "createdt"  => date("Y-m-d H:i:s"),
      "updatedt"  => date("Y-m-d H:i:s")
    );
    for ($I = 1; $I <= 50; $I++) {
      if (isset($Final[$I - 1])) {
        $Data["ppn" . str_pad($I, 2, "0", STR_PAD_LEFT)]           = $Final[$I - 1];
        $Data["ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "count"] = 0;
        $Data["ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "score"] = $Score[$Final[$I - 1]];
      } else {
        $Data["ppn" . str_pad($I, 2, "0", STR_PAD_LEFT)]           = "";
        $Data["ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "count"] = 0;
        $Data["ppn" . str_pad($I, 2, "0", STR_PAD_LEFT) . "score"] = 0;
      }
    }

    // $this->debug("ALXW",$SQL);
    // $this->debug("ALXW",$Data);

    return ($this->ExecDB($SQL, $Data));

    // $this->debug("ALXPROJSTR", $SQL);
  }

  public function updateProjectDB($Project, $PPN)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!isset($Project) || !isset($PPN))  return (-1);

    $SQL  = "select * from projects where client = :client and project = :project and sessionid = :sessionid";
    $Data = array(
      "client"    => $this->client,
      "project"   => $Project,
      "sessionid" => $this->checkPostedStr("tokens", "session")
    );
    // $this->debug("ALX", $Data);

    $Rows = $this->ExecDB($SQL, $Data);

    // $this->debug("ALX§)§", $Rows);
    // return;

    foreach ($Rows as $Row) {
      $I2    = "";
      $Found = false;
      for ($I = 1; $I <= 50; $I++) {
        $I2 = "ppn" . str_pad($I, 2, "0", STR_PAD_LEFT);
        if (isset($Row[$I2]) && $Row[$I2] == $PPN) {
          $Found = true;
          break;
        }
      }
      if ($Found) {
        $SQL  = "update projects set " . $I2 . "count = :" . $I2 . "count, updatedt = :updatedt";
        $SQL .= " where id = :id";
        $Data = array(
          "id"         => $Row["id"],
          $I2 . "count" => $Row[$I2 . "count"] + 1,
          "updatedt"   => date("Y-m-d H:i:s")
        );
        // $this->debug("ALXPROJSTR", $SQL);
        // $this->debug("ALXPROJSTR", $Data);
        $this->ExecDB($SQL, $Data);
      }
    }

    return;

    // $this->debug("ALXPROJSTR", $SQL);
  }



  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}
