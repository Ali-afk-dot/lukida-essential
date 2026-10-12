<?php

class user extends appluserdb
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************

  // Configs
  public $bmscon;
  public $cases;
  public $casesmedia;
  public $collections;
  public $hierarchy;
  public $ilngroups;
  public $bms;
  public $bmsid;
  public $levels;
  public $links;
  public $locations;

  // Internal params
  public $params;

  // Process data
  public $items;
  public $logs;
  public $search;
  public $works;    // Main loo

  // Return container
  public $results;

  // To delete
  public $combs;
  public $display;
  public $paias;

  public function __construct($f3)
  {
    parent::__construct($f3);

    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $this->cases       = array();
    $this->collections = array();
    $this->hierarchy   = array("childs" => array(), "parents" => array());
    $this->ilngroups   = array();
    $this->items       = array();
    $this->bms         = array();
    $this->bmsid       = 1;
    $this->bmscon      = (object) array();
    $this->levels      = array();
    $this->links       = array();
    $this->locations   = array();
    $this->logs        = array();
    $this->params      = array();
    $this->results     = array();
    $this->search      = array();
    $this->works       = array();

    // To delete
    $this->combs       = array();
    $this->display     = array();
    $this->paias       = array();

    // Read Config
    $this->getConfig();

    // Get Levels
    $this->getLevels();

    // Read Cases
    $this->getCases();

    // Read CasesMedia
    $this->getCasesMedia();

    // Read Texts
    // $this->getTexts();

    // Read BMS
    $this->getBMS();

    // Read Links
    $this->getLinks();

    // Read Collections
    $this->getCollections();

    // Read locations
    $this->getLocations();

    // Process BMS
    $this->bmsid = $this->checkPostedStr("bms");
    if ($this->bmsid == "") {
      $this->bmsid = $this->checkDecryptedPostedStr("bms");
      if ($this->bmsid == "") {
        $this->bmsid = 1;
      }
    }
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************
  private function checkUser()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Process display
    $Display = array();
    $Display["user"]     = (isset($this->posted["display"]["user"])  && is_bool($this->posted["display"]["user"])  && $this->posted["display"]["user"])  ? true : false;
    $Display["items"]    = (isset($this->posted["display"]["items"]) && is_bool($this->posted["display"]["items"]) && $this->posted["display"]["items"]) ? true : false;
    $Display["fees"]     = (isset($this->posted["display"]["fees"])  && is_bool($this->posted["display"]["fees"])  && $this->posted["display"]["fees"])  ? true : false;
    $this->display       = $Display;

    // Attach BMS
    if (!$this->setBMS()) return array();

    $User = isset($this->posted["login"]) ? $this->posted["login"] : "";
    $Pass = isset($this->posted["pw"])    ? $this->posted["pw"]    : "";

    // Check User
    $this->results = $this->bmscon->checkUser($User, $Pass);

    if (isset($this->results["stat"]) && $this->results["stat"] == "error") return $this->results;
    // $this->debug("ALX", $this->results);
    // Check password change & cancel reservation
    if (isset($this->results["user"]["name"])) {
      $this->results["user"]["changepw"]  = (isset($this->configs["bms"]["changepassword"])    && $this->configs["bms"]["changepassword"]    == "y") ? true : false;
      $this->results["user"]["cancelres"] = (isset($this->configs["bms"]["cancelreservation"]) && $this->configs["bms"]["cancelreservation"] == "y") ? true : false;
    }

    // Termine groups to be displayed in correct order
    $DisplayGroups  = array();
    $PAIAGroups     = array();
    foreach (array("usercollectables", "userorders", "userrentals", "userreservations") as $AccGrp) {
      if (
        isset($this->configs["account"][$AccGrp . ".show"])    && $this->configs["account"][$AccGrp . ".show"]    == "y"
        && isset($this->configs["account"][$AccGrp . ".pos"])     && $this->configs["account"][$AccGrp . ".pos"]     != ""
        && isset($this->configs["account"][$AccGrp . ".paiaids"]) && $this->configs["account"][$AccGrp . ".paiaids"] != ""
      ) {
        $DisplayGroups[$this->configs["account"][$AccGrp . ".pos"]] = $AccGrp;
        $Ps = explode(",", $this->configs["account"][$AccGrp . ".paiaids"]);
        foreach ($Ps as $P) {
          $PAIAGroups[$P] = $AccGrp;
        }
      }
    }

    ksort($DisplayGroups);
    $DisplayGroups = array_values($DisplayGroups);
    $FindGroups     = array_flip($DisplayGroups);

    // $this->debug("ALX23", $PAIAGroups);
    // $this->debug("ALX23", $DisplayGroups);
    // $this->debug("ALX23", $FindGroups);

    $Items          = array();
    foreach ($DisplayGroups as $Key => $Val) {
      $Items["group_" . $Key] = array("label" => $this->texts["account." . $Val], "code" => $Val, "items" => array());
    }

    // Format items
    if (isset($this->results["items"])  && count($this->results["items"])) {
      $PItems = $this->results["items"];
      // $this->debug("ALX23", $PItems);

      // Ensure endtime always available & sort by endtime desc
      foreach ($PItems as $Ind => $Itm) {
        if (!isset($Itm["endtime"]))  $PItems[$Ind]["endtime"] = "";
      }
      array_multisort(array_column($PItems, 'endtime'), SORT_DESC, $PItems);

      foreach ($PItems as $I => $Item) {
        if (
          !isset($Item["status"])
          || !isset($PAIAGroups[$Item["status"]])
          || !isset($FindGroups[$PAIAGroups[$Item["status"]]])
        )  continue;

        $Group = $FindGroups[$PAIAGroups[$Item["status"]]];
        $SID   = substr($Item["storageid"], strpos($Item["storageid"], "@") + 1);
        $Label = isset($Item["label"]) ?  $Item["label"] : "";
        if (strpos($Label, "::") !== false)  $Label = substr($Label, strpos($Label, "::") + 1);
        if (strpos($Label, ":")  !== false)  $Label = substr($Label, strpos($Label, ":") + 1);

        $Tmp = array(
          "id"      => isset($Item["item"])  ? $Item["item"]  : "",
          "title"   => isset($Item["about"]) ? $Item["about"] : "",
          "label"   => $Label,
          "status"  => array(
            "start"       => isset($Item["starttime"]) && trim($Item["starttime"]) ? $this->format2German($Item["starttime"]) : "",
            "end"         => isset($Item["endtime"])   && trim($Item["endtime"])   ? $this->format2German($Item["endtime"])   : "",
            "queue"       => isset($Item["queue"])    ? $Item["queue"]    : "-",
            "renewals"    => isset($Item["renewals"]) ? $Item["renewals"] : "0",
            "maxrenewals" => intval($this->configs["bms"]["maxrenewals"]),
            "reminder"    => isset($Item["reminder"]) ? $Item["reminder"] : "0",
            "state"       => $Item["status"]
          ),
          "storage" => array(
            "name"        => isset($Item["storage"])  ? $Item["storage"]  : "",
            "id"          => $SID,
            "label"       => isset($this->texts["DESK" . substr($SID, 8)]) ? $this->texts["DESK" . substr($SID, 8)] : ""
          ),
          "actions"  => array(
            "cancel"     => isset($Item["cancancel"]) ? $Item["cancancel"] : false,
            "renew"      => isset($Item["canrenew"]) ? $Item["canrenew"] : false
          )
        );
        if (isset($Item["canrenew"]) && $Item["canrenew"] && isset($Item["renewals"]) && $Item["renewals"] <= intval($this->configs["bms"]["maxrenewals"])) {
          $Tmp["actions"]["renew"]  = array(
            "type"    => "renew",
            "id"      => isset($Item["item"])  ? $Item["item"]  : "",
            "login"   => "<login>"
          );
        }
        if (isset($Item["cancancel"]) && $Item["cancancel"]) {
          $Tmp["actions"]["cancel"] = array(
            "type"    => "cancel",
            "id"      => isset($Item["item"])  ? $Item["item"]  : "",
            "login"   => "<login>"
          );
        }
        $Items["group_" . $Group]["items"][] = $Tmp;
      }
      // $this->debug("AlexPaia", $Items);
      // Add user mails
    }
    $this->results["items"] = $Items;
    // Format fees
    if (isset($this->results["fees"]["items"]) && is_array($this->results["fees"]["items"]) && count($this->results["fees"]["items"])) {
      foreach ($this->results["fees"]["items"] as $I => $Item) {
        $this->results["fees"]["items"][$I]["date"] = $this->format2German($Item["date"]);
      }
    }

    // Add others order (mail) to user account
    if (
      isset($this->configs["account"]["userotherorders.show"])
      && $this->configs["account"]["userotherorders.show"] == "y"
    ) {
      $Mails = $this->getMailDB($this->results["login"]);
      $this->results["otherorders"] = array(
        "label" => $this->texts["account.userotherorders"],
        "code"  => "userotherorders",
        "items" => $Mails
      );
    }
    return $this->results;
  }

  // $DialogMail: eigenes Ziel des Dialogs, sonst leer - die clienteigene
  // action.php entscheidet, ob sie es nutzt oder auf ihre Konfiguration
  // zurueckfaellt (2026-08-11).
  private function doActionLocal($Type, $ID, $Data, $DialogMail = "")
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $File                    = dirname(__FILE__) . DIRECTORY_SEPARATOR . "clients"
      . DIRECTORY_SEPARATOR . $this->client
      . DIRECTORY_SEPARATOR . "action.php";
    $Status = "";
    $Code   = "";
    $More   = "";
    if (file_exists($File)) {
      // Library rules
      include $File;
    }

    // Standardverarbeitung: Hat der Client keine eigene action.php - oder hat
    // sie diesen Typ nicht angefasst ($Status bleibt "") -, wird der Dialog
    // hier einheitlich verschickt. Vorher gab es das nur clienteigen
    // (HannoverGWLB, BerlinWiko); die uebrigen rund 30 Clients liefen im
    // Altmodul ueber "mail2library" mit je eigener Maske. Eine gemeinsame
    // Verarbeitung spart genau diese Vervielfachung (2026-08-11).
    if ($Status === "") {
      return $this->sendDialogMail($Type, $ID, $Data, $DialogMail);
    }

    return array("status" => $Status, "code" => $Code, "more" => $More);
  }

  // Einheitlicher Versand eines Dialogs: Empfaenger ist die im Dialog
  // hinterlegte Adresse, sonst - wie bisher - "mail.<mode>.<typ>" aus der
  // Client-Konfiguration. Inhalt sind die ausgefuellten Felder plus die
  // Kurzangaben zum Titel; der Aufbau folgt dem, was mail2library() erzeugt.
  private function sendDialogMail($Type, $ID, $Data, $DialogMail = "")
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Mode = (MODE == "production") ? "prod" : "test";
    $Tos  = array();
    if (trim((string) $DialogMail)) {
      $Tos = explode(",", trim($DialogMail));
    } elseif (isset($this->configs["mail." . $Mode][$Type]) && trim($this->configs["mail." . $Mode][$Type])) {
      $Tos = explode(",", trim($this->configs["mail." . $Mode][$Type]));
    }

    if (!count($Tos)) {
      $this->sendErrorMail(array(
        "subject" => "Config-Mail-Missing (" . $this->client . ")",
        "message" => "Dialog \"" . $Type . "\": weder eine Adresse in dialogs.mail" . $Mode
                   . " noch mail." . $Mode . "." . $Type . " in der Konfiguration."
      ));
      return array("status" => -1, "code" => "mailerror", "more" => "no target");
    }

    $User    = isset($Data["user"]) ? $Data["user"] : array();
    $Fields  = $Data;
    unset($Fields["user"]);

    $Subject = $this->getText("mail.subject." . $Type);
    if (!$Subject || $Subject == "mail.subject." . $Type)  $Subject = $this->getText("bibline.subject");
    if (!$Subject)  $Subject = "Anfrage";

    $Msg  = "<table border='1' cellpadding='4' cellspacing='0'>";
    // Titel und ID nur, wenn es einen Datensatz gibt. Der Dialog nach einer
    // erfolglosen Suche hat keinen - dort traegt der Absender den Titel selbst
    // in die Felder ein, und eine leere Zeile "Titel:" waere irrefuehrend
    // (09.09.2026).
    if ($ID !== "") {
      $Msg .= "<tr><td>Titel</td><td>" . $this->getMarcText($ID, "bib_01") . "</td></tr>";
      $Msg .= "<tr><td>ID</td><td>" . $ID . "</td></tr>";
    }
    foreach (array("username", "name", "email") as $Key) {
      if (isset($User[$Key]) && trim((string) $User[$Key])) {
        $Msg .= "<tr><td>" . $this->getText("user." . $Key) . "</td><td>" . $User[$Key] . "</td></tr>";
      }
    }
    foreach ($Fields as $Key => $Val) {
      if (is_array($Val))  $Val = implode(", ", $Val);
      if (!trim((string) $Val))  continue;
      $Label = $this->getText($Key);
      $Msg  .= "<tr><td>" . ($Label ? $Label : $Key) . "</td><td>" . htmlspecialchars((string) $Val) . "</td></tr>";
    }
    $Msg .= "</table>";

    $Names = array();
    foreach ($Tos as $To)  $Names[] = ucfirst(explode("@", $To)[0]);

    $Result = $this->DoSendMail($Names, $Tos, $Subject, $Msg, $Type);
    if (!isset($Result["status"]) || $Result["status"] < 0) {
      return array("status" => -1, "code" => "mailerror", "more" => "");
    }

    $this->updStatDB("Action", "Dialog|" . $Type);
    return array("status" => 0, "code" => "mailsuccessful", "more" => "");
  }

  private function formatSolrItemColl($Item)
  {
    // return str_replace("Ö", "%C3%96", $Item );
    $Item = urlencode($Item);
    return $Item;
  }

  private function formatSolrItem($Item)
  {
    return str_replace(
      array("%",  ":",    " ",  "-",  "/"),
      array("%25", "\:", "\%20", "\-", "\/"),
      $Item
    );
  }

  private function getCases()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Item configs
    $File = $this->prepareCachePath(array("settings"), "cases");
    if (file_exists($File)) {
      if (MODE != "production") $this->updStatDB("Cache", "Cases.yes");
      $this->cases = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      if (MODE != "production") $this->updStatDB("Cache", "Cases.no");
      $this->cases  = $this->getAdminCasesDB();
      file_put_contents($File, json_encode($this->cases, true));
      // file_put_contents($File, print_r($this->cases, true));
    }
  }

  private function getCasesMedia()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Item configs
    $File = $this->prepareCachePath(array("settings"), "casesmedia");
    if (file_exists($File)) {
      if (MODE != "production") $this->updStatDB("Cache", "Casesmedia.yes");
      $this->casesmedia = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      if (MODE != "production") $this->updStatDB("Cache", "casesmedia.no");
      $this->casesmedia  = $this->getAdminCasesMediaDB();
      file_put_contents($File, json_encode($this->casesmedia, true));
      // file_put_contents($File, print_r($this->casesmedia, true));
    }
  }

  private function getCollections()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Item configs
    $File = $this->prepareCachePath(array("settings"), "collections");
    if (file_exists($File)) {
      if (MODE != "production") $this->updStatDB("Cache", "Collections.yes");
      $this->collections = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      if (MODE != "production") $this->updStatDB("Cache", "Collections.no");
      $this->collections  = $this->getCollectionsDB();
      file_put_contents($File, json_encode($this->collections, true));
      // file_put_contents($File, print_r($this->collections, true));
    }
  }

  private function getLocations()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Mode = strtolower(substr(MODE, 0, 4)) == "prod" ? "prod" : "test";
    $Tmp = isset($this->configs["import." . $Mode]["loc.data"])
      && trim($this->configs["import." . $Mode]["loc.data"])
      ? $this->configs["import." . $Mode]["loc.data"] : "";

    $this->locations = ($Tmp) ? json_decode($Tmp, true) : [];
    /*
    $File                    = dirname(__FILE__) . DIRECTORY_SEPARATOR . "clients"
                               . DIRECTORY_SEPARATOR . $this->client
                               . DIRECTORY_SEPARATOR . "locations.inc.php";
    if ( file_exists($File) ) $this->locations = (array) json_decode(file_get_contents($File), true);
    */
  }

  private function getConfig()
  {
    // Item configs
    $this->configs["mycolls"] = (isset($this->configs["other"]["mycollections"])) ? (array) json_decode($this->configs["other"]["mycollections"], true) : array();

    // "Ähnliche Titel" (kisuggest) ist ein reines Projekt (useraccess=none) und
    // steht darum nicht in der Client-Config. Die Oberfläche soll den Bereich
    // aber nur bei aktivem Projekt überhaupt zeigen und sonst gar keine Daten
    // abrufen - deshalb spiegeln wir den reinen Ja/Nein-Schalter gezielt in
    // clientconfigs (19.08.2026). Nur "y" wird gespiegelt; fehlt der Schlüssel,
    // bleibt der Bereich beim Client unsichtbar.
    if ( isset($this->configs["project"]["kisuggest"]) && $this->configs["project"]["kisuggest"] == "y" )
    {
      if ( !isset($this->clientconfigs["project"]) || !is_array($this->clientconfigs["project"]) ) $this->clientconfigs["project"] = array();
      $this->clientconfigs["project"]["kisuggest"] = "y";
    }

    // FLAT Combined ILN_MAN or only ILN
    $this->configs["ilnmans"] = (isset($this->configs["item"]["ilnlist"])  && trim($this->configs["item"]["ilnlist"]))
      ? explode(",", $this->configs["item"]["ilnlist"])
      : array();
    // ISILs
    $this->configs["isils"]   = (isset($this->configs["item"]["isillist"]) && trim($this->configs["item"]["isillist"]))
      ? explode(",", $this->configs["item"]["isillist"])
      : array();

    // Only ILNs
    $this->configs["ilns"]     = array();
    foreach ($this->configs["ilnmans"] as $ILNMAN) {
      if (($Pos = stripos($ILNMAN, "_")) !== false) {
        $this->configs["ilns"][] = substr($ILNMAN, 0, $Pos);
      } else {
        $this->configs["ilns"][] = $ILNMAN;
      }
    }

    // ARRAY Combined ILN -> MAN
    $this->configs["ilnandman"] = array();
    foreach ($this->configs["ilnmans"] as $ILNMAN) {
      if (($Pos = stripos($ILNMAN, "_")) !== false) {
        $this->configs["ilnandman"][substr($ILNMAN, 0, $Pos)][] = substr($ILNMAN, $Pos + 1);
      } else {
        $this->configs["ilnandman"][$ILNMAN] = array();
      }
    }

    // $this->debug("ALX1", $this->configs["ilns"]);
    // $this->debug("ALX2", $this->configs["ilnmans"]);
    // $this->debug("ALX2", $this->configs["ilnandman"]);
    // Ensure language (if not posted)
    if (!$this->lang) {
      if (
        isset($this->configs["init"]["language"]) && trim($this->configs["init"]["language"])
        && in_array(trim($this->configs["init"]["language"]), array("e", "g"))
      ) {
        $this->lang = trim($this->configs["init"]["language"]);
      } else {
        $this->lang = "g";
      }
    }
    // $this->debug("AOCX383", $this->config);
  }

  /**
   * Clientname zweisprachig (ALI 03.09.2026)
   *
   * Im Setting client/clientname steht zuerst der deutsche und danach, durch
   * ein Komma getrennt, optional der englische Name ("Leibniz-Institut ...
   * (IAMO),IAMO Library"). Bei englischer Sprache und vorhandenem zweiten
   * Element kommt dieses zur Anzeige, sonst immer das erste (und evtl.
   * einzige) Element.
   */
  public function getClientName()
  {
    $Name  = isset($this->configs["client"]["clientname"]) ? (string) $this->configs["client"]["clientname"] : "";
    $Teile = array_map("trim", explode(",", $Name, 2));
    if ($this->lang == "e" && isset($Teile[1]) && $Teile[1] !== "") return $Teile[1];
    return $Teile[0];
  }

  private function getLevels()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Item configs
    $File = $this->prepareCachePath(array("settings"), "levels");
    if (file_exists($File)) {
      if (MODE != "production") $this->updStatDB("Cache", "Levels.yes");
      $this->levels = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      if (MODE != "production") $this->updStatDB("Cache", "Levels.no");
      $this->levels  = $this->getAdminLevelsDB();
      file_put_contents($File, json_encode($this->levels, true));
      // file_put_contents($File, print_r($this->levels, true));
    }
  }

  private function getBMS()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Item configs
    $File = $this->prepareCachePath(array("settings"), "bms");
    if (file_exists($File)) {
      if (MODE != "production") $this->updStatDB("Cache", "BMS.yes");
      $this->bms = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      if (MODE != "production") $this->updStatDB("Cache", "BMS.no");
      $this->bms  = $this->getAdminBMSDB();
      file_put_contents($File, json_encode($this->bms, true));
    }

    // Auswahlliste der Bibliotheken fuer den Client: Kennung plus FERTIG
    // aufgeloeste Beschriftung in der Sprache des Payloads. Der Client soll
    // dafuer keine eigenen Texte mehr vorhalten - bisher kam die Beschriftung
    // aus VITE_BMSOPTIONS in der .env, die zudem Zugangsdaten im Klartext
    // enthielt (2026-08-08). Reihenfolge: nach Beschriftung sortiert.
    $BMSList = array();
    foreach ($this->bms as $bmsid => $l) {
      $Label = isset($l["namecode"]) && $l["namecode"] ? $this->getText($l["namecode"]) : "";
      if (!$Label)  $Label = isset($l["namecode"]) ? $l["namecode"] : (string) $bmsid;
      $BMSList[] = array("bmsid" => (string) $bmsid, "label" => $Label);
    }
    // Reihenfolge bewusst NICHT umsortiert - sie folgt der Datenbank. Eine
    // alphabetische Sortierung war hier zwischenzeitlich drin, der Wunsch ist
    // aber eine eigene Reihenfolge über eine "sort"-Spalte; die gibt es in
    // lukida_user.adminbms derzeit nicht (2026-08-08, siehe CHANGELOG).
    $this->configs["bmslist"] = $BMSList;
    // Die config-Aktion liefert $this->clientconfigs (eigene, zwischen-
    // gespeicherte Eigenschaft in system.php) - nur dort landet die Liste
    // auch beim Client.
    $this->clientconfigs["bmslist"] = $BMSList;

    // Dialogdefinitionen fuer die gemeinsame Dialogkomponente in User2026.
    // Wie bei bmslist muss es in BEIDE Eigenschaften - die config-Aktion
    // liefert clientconfigs (2026-08-11).
    $Dialogs = $this->getDialogsDB();
    $this->configs["dialogs"]       = $Dialogs;
    $this->clientconfigs["dialogs"] = $Dialogs;

    // Nach ILN UND bmsid verschachtelt (21.08.2026, aus API 4 nachgezogen).
    //
    // Vorher stand hier ein FLACHES iln2bms[iln] samt "!isset(...)": je ILN
    // wurde nur das ERSTE BMS eingetragen, jedes weitere verworfen. WeimarBU
    // nutzt testweise EINE ILN fuer paia (bms 1) UND folio (bms 2) - folio
    // fiel damit unter den Tisch, und die Exemplare kamen trotz Folio-Anmeldung
    // von paia. setBMS() weiter unten liest die verschachtelte Form bereits.
    foreach ($this->bms as $bmsid => $l) {
      if (isset($l["iln"])) {
        $this->configs["iln2bms"][$l["iln"]][$bmsid] = array(
          "bmsid"    => $bmsid,
          "db"       => isset($l["options"]["db"]) ? $l["options"]["db"] : "",
          "isil"     => isset($l["isil"])          ? $l["isil"]          : "",
          "namecode" => isset($l["namecode"])      ? $l["namecode"]      : ""
        );
      }
    }
    // $this->debug("ALXBMS", $this->configs["iln2bms"]);
  }

  // Aktive Wartungsfenster dieses Clients (04.09.2026).
  //
  // Gepflegt wird je BMS unter "options.maintenance" (Maske "BMS -
  // Verfuegbarkeit" in Admin2026):
  //
  //   {"state":"window","from":"2026-09-10 20:00","to":"2026-09-11 06:00",
  //    "text_g":"...","text_e":"..."}
  //
  // "state" != "window" heisst verfuegbar. Zurueck kommt NUR, was gerade
  // laeuft - die Oberflaechen muessen also nichts rechnen, sondern zeigen den
  // Hinweis, sobald die Liste nicht leer ist. Beide Sprachen gehen mit, damit
  // ein Sprachwechsel ohne neuen Aufruf greift.
  public function getMaintenance()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Jetzt = time();
    $Out   = array();

    foreach ($this->getMaintenanceDB() as $BmsID => $W) {
      if (!isset($W["state"]) || $W["state"] != "window") continue;

      $Von = isset($W["from"]) ? strtotime((string) $W["from"]) : false;
      $Bis = isset($W["to"])   ? strtotime((string) $W["to"])   : false;
      if (!$Von || !$Bis || $Jetzt < $Von || $Jetzt > $Bis) continue;

      $Out[] = array(
        "bmsid"  => (string) $BmsID,
        "from"   => (string) $W["from"],
        "to"     => (string) $W["to"],
        "text_g" => isset($W["text_g"]) ? (string) $W["text_g"] : "",
        "text_e" => isset($W["text_e"]) ? (string) $W["text_e"] : "",
      );
    }
    return $Out;
  }

  // Allgemeine Meldungen fuer die Startseite (04.09.2026).
  //
  // Anders als das Wartungsfenster bewirken sie NICHTS ausser ihrer Anzeige:
  // keine gesperrten Schaltflaechen, kein Eingriff in die Ablaeufe. Gepflegt
  // werden sie in Admin2026 unter "Meldungen"; nur die neue Oberflaeche zeigt
  // sie (API 4 liefert sie deshalb gar nicht erst).
  public function getMessages()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    return $this->getMessagesDB();
  }

  private function getLinks()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Item configs
    $File = $this->prepareCachePath(array("settings"), "links");
    if (file_exists($File)) {
      if (MODE != "production") $this->updStatDB("Cache", "Links.yes");
      $this->links = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      if (MODE != "production") $this->updStatDB("Cache", "Links.no");
      $this->links  = $this->getAdminLinksDB();
      file_put_contents($File, json_encode($this->links, true));
      // file_put_contents($File, print_r($this->links, true));
    }
  }

  private function getUser()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Login = isset($this->posted["login"])          ? $this->posted["login"]          : "";
    $Token = isset($this->posted["tokens"]["user"]) ? $this->posted["tokens"]["user"] : "";

    // Process display
    $Display = array();
    $Display["user"]     = true;
    $this->display       = $Display;

    // Attach bms
    if (!$this->setBMS()) return array();

    // Read user
    $this->results = $this->bmscon->ReadUser($Login, $Token);
    if (count($this->results) == 0) return array();

    return $this->results;
  }

  // Lieferdienst: darf diese Benutzergruppe das? (09.09.2026)
  //
  // Bis zum Umbau auf den generischen Dialog stand diese Pruefung im CLIENT
  // (Altmodul ItemDlgDelivery.vue): die API legte dem Exemplar die erlaubten
  // Gruppen als "users" bei, der Client verglich sie mit der Gruppe des
  // angemeldeten Kontos und lehnte mit "delivery.notauthorized" ab. Die neue
  // Oberflaeche kennt diese Liste bewusst nicht mehr - sie soll keine
  // Berechtigungen entscheiden -, und damit lief die Beschraenkung ins Leere:
  // der Lieferdienst stand jedem angemeldeten Konto offen.
  //
  // Jetzt entscheidet der Server, aus derselben Quelle wie zuvor die
  // Knopf-Ausgabe (admin.configs, Kategorie "item"):
  //
  //   delivery.usertypescheck   Schalter (y/n) - ohne "y" wird nicht geprueft
  //   delivery.usertypes        erlaubte Gruppen, z.B. "1,2,3,8"
  //
  // Die Gruppe des Kontos kommt je nach Bibliothekssystem verschieden an:
  // PAIA als "de-wim2:user-type:3", FOLIO als Klartext der Ausleihgruppe.
  // Verglichen wird - wie im Altmodul - der Teil NACH dem letzten
  // Doppelpunkt.
  //
  // Fehlt der Typ, fehlt die Liste oder steht der Schalter nicht auf "y",
  // wird NICHT abgelehnt. Eine Sperre, die aus fehlenden Angaben entsteht,
  // waere schlimmer als die Luecke, die sie schliessen soll - genau so haelt
  // es auch das Altmodul.
  private function checkDeliveryUserType($Dialog, $User)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!in_array($Dialog, array("delivery", "delivery2"))) return true;

    $Switch = isset($this->configs["item"]["delivery.usertypescheck"])
              ? strtolower(trim($this->configs["item"]["delivery.usertypescheck"]))
              : "";
    if ($Switch != "y") return true;

    $Allowed = isset($this->configs["item"]["delivery.usertypes"])
               ? array_values(array_filter(array_map("trim", explode(",", $this->configs["item"]["delivery.usertypes"])), "strlen"))
               : array();
    if (!count($Allowed)) return true;

    $Type = isset($User["type"]) ? $User["type"] : "";
    if (is_array($Type)) $Type = isset($Type[0]) ? $Type[0] : "";
    $Type = trim((string) $Type);
    if ($Type === "") return true;

    $Parts = explode(":", $Type);
    $Group = trim((string) end($Parts));

    if (!in_array($Group, $Allowed, true)) {
      $this->updStatDB("Action", "Delivery|notauthorized");
      if (APPDEBUG["LOG"]) $this->debug("LOG", "Delivery denied for user type '" . $Group . "'");
      return false;
    }
    return true;
  }

  private function requireAreaUser()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Validate the posted login + BMS session token against the ILS (same
    // pattern as getUser()/DoCancel/DoChangeMail) and trust the returned
    // username, rather than the posted login, for hashing/filtering.
    $User = $this->getUser();
    if (!isset($User["username"]) || !trim($User["username"])) {
      $this->finalize(array("status" => -1, "code" => "loginerror"));
    }
    $this->user = $User["username"];
  }

  private function readConvertFindex($Add = false, $Prefix = "M")
  {
    // $this->debug("ALEX35", $this->search["search"]["query"]);

    // Important notes on vars
    // - works    contains working data
    // - results  will be returned to client
    // All drivers may fill both vars

    // Solr driver
    // Reades from search
    // Writes to   works[solr]
    //             results[counts]
    $Solr = new solr($this);
    $Solr->getSearch($Add);

    // Marc driver
    // Reades from works[solr]
    // Writes to   works[marc]
    //             results[data]
    //             results[marc]
    //             hierarchy
    $Marc = new marc($this);
    $Marc->getConverts($Add, $Prefix);
    // Cache is written inside MARC driver
  }

  private function searchControl()
  {
    // ********************************************************
    // ** STEP 1:  Attach Solr & search (if not cached)      **
    // ** Results: $this->results                            **
    // ********************************************************
    $SType  = $this->checkPostedStr("search", "type") == "lukida" ? "lukida" : "findex";
    $SQuery = $this->checkPostedStr("search", "query");
    $EPN    = $this->checkPostedStr("epn");

    // Use Cache for ids from already stored in cache
    if ($SType == "lukida") {
      // Get ID Part of query, keep remaining query
      // Important for Regex detection
      $SQuery .= " ";
      $BracketsRegex = "/([A-Za-z0-9]+)\(([^))]+)\)\s/";
      preg_match_all($BracketsRegex, $SQuery, $IntComs);
      // $this->debug("ALX", $IntComs);
      // $this->debug("AÖLAFT", $SQuery);
      // $this->debug("AÖLAFT", $IntComs);
      if (isset($IntComs[1]) && is_array($IntComs[1]) && count($IntComs[1])) {
        foreach ($IntComs[1] as $Idx => $IC) {
          if ($IC == "id") {
            $IDs    = isset($IntComs[2][$Idx]) ? explode(",", $IntComs[2][$Idx]) : array();
            $IDs    = array_filter(array_unique($IDs));
            $NewIDs = array();
            foreach ($IDs as &$ID) {
              $ID = trim($ID);
              if (!$this->readCache2Marc($ID)) {
                if (MODE != "production") $this->updStatDB("Cache", "Marc.no");
                $NewIDs[] = $ID;
              }
            }
            // $this->debug("AÖLIDs", $IDs);
            // $this->debug("AÖLNEWIDs", $NewIDs);
            $SQuery = trim(str_replace(
              $IntComs[0][$Idx],
              count($NewIDs) ? "id(" . implode(",", $NewIDs) . ")" : "",
              $SQuery
            ));
          }
        }
        // $this->debug("AÖLMARC", $SQuery);
        // $this->debug("AÖLMARC", array_keys($this->works["marc"]));
        $this->search["search"]["query"] = trim($SQuery);
      }
    }

    // ********************************************************
    // ** STEP 2:  Process MARC Conversion                   **
    // ** Results: $this->results                            **
    // ********************************************************
    if (trim($this->search["search"]["query"])) $this->readConvertFindex();


    // ********************************************************
    // ** STEP 3:  Read parents from childs and MARC convert **
    // ** Results: $this->results                            **
    // ********************************************************

    // Read parents from childs
    if (isset($this->hierarchy["childs"]) && is_array($this->hierarchy["childs"]) && count($this->hierarchy["childs"])) {
      $Parents = array();

      // Read from cache or solr
      foreach ($this->hierarchy["childs"] as $PID) {
        if (!$this->readCache2Marc($PID, true)) {
          $Parents[] = $PID;
        }
      }
      // $this->debug("ALEXHHP", $Parents);

      $Parents = array_filter(array_unique(array_values($Parents)));

      if (count($Parents)) {
        $this->search = array(
          "search"   => array(
            "query" => "id(" . implode(", ", $Parents) . ")",
            "type"     => "lukida"
          ),
          "start"    => 0,
          "limit"    => count($Parents),
          "sort"     => "",
          "spell"    => false,
          "phonetic" => false,
          "facets"   => array(),
          "url"      => $this->buildBasicURL("all"),
          "counts"   => array()
        );


        // Process MARC
        $this->readConvertFindex(true, "PM");
      }
    }

    // ********************************************************
    // ** STEP 4:  Process bms                               **
    // ** Results: $this->results                            **
    // ********************************************************

    // $Step = true falls 2 stufiger Bandlisten - Mechanismus
    $Step = (isset($this->config["items"]["journalssteppeditems"]) && trim($this->config["items"]["journalssteppeditems"]) == "y") ? true : false;
    // $this->debug("DEBUG_ITEMS", ["disp" => $this->display["items"] ?? "NOT SET", "marc" => count($this->works["marc"] ?? [])]);

    if (isset($this->display["items"]) && $this->display["items"]) {
      if (isset($this->works["marc"]) && is_array($this->works["marc"]) && count($this->works["marc"])) {
        if (!$EPN) {
          // PPN Mode

          $APS  = (isset($this->configs["item"]["articleparentshift"])   && trim($this->configs["item"]["articleparentshift"])   == "y") ? true : false;
          // Werden fuer Artikel gar keine Eltern gelesen, darf auch nicht auf
          // sie geschoben werden (17.08.2026). item.php verweigert bei
          // articleswithoutparents=y die Elterndaten; ohne diese Bedingung
          // haette user.php trotzdem geschoben, und die Exemplaranzeige waere
          // von der Shift-Entscheidung abgewichen. Live tritt die Kombination
          // bei keinem Client auf - KielUB und SchwerinLaKD haben awop=y, aber
          // keinen Shift -, richtig war es trotzdem nicht.
          $AWOP = (isset($this->configs["item"]["articleswithoutparents"]) && trim($this->configs["item"]["articleswithoutparents"]) == "y") ? true : false;
          $JWI  = (isset($this->configs["item"]["journalswithoutitems"]) && trim($this->configs["item"]["journalswithoutitems"]) == "y") ? true : false;
          $PPNs = array();
          foreach ($this->works["marc"] as $ID => $One) {
            $this->params["shift"] = false;

            if (
              // Kriterien fuer den articleparentshift (festgelegt 11.08.2026):
              //   1. Eltern vorhanden UND geladen
              //   2. nicht online (Solr-Feld remote_bool)
              //   3. keine eigenen Exemplare aus M980 - "ohne Fleisch" meint
              //      ausschliesslich M980, M981 und M856 bleiben aussen vor
              //   4. formatcode == "article"
              //   5. eigener Bestand (getOwnILNsOrCols: Datensatz gehoert zu
              //      einer eigenen ILN bzw. Collection)
              // Dieselben fuenf Kriterien prueft item.php::isHapticArticle() -
              // beide Stellen muessen dasselbe verstehen, sonst weicht die
              // Exemplarausgabe von der Shift-Entscheidung ab.
              //
              // Die spaetere allgemeine Pruefung auf "owner" weiter unten in
              // dieser Schleife ist eine andere und bleibt davon unberuehrt.
              $APS && !$AWOP && isset($this->hierarchy["childs"][$ID])
              && isset($this->works["add"][$this->hierarchy["childs"][$ID]])
              && isset($One["owner"])                 && $One["owner"]
              && isset($One["online"])                && !$One["online"]
              && isset($One["itemcount"]["physical"]) && !$One["itemcount"]["physical"]
              && isset($One["formatcode"])             && $One["formatcode"] == "article"
            ) {
              // Shift to parent
              $this->params["shift"]    = true;
              $this->params["parentid"] = $this->hierarchy["childs"][$ID];
              $this->params["childid"]  = $ID;
              // $this->debug("SKIP 333", $this->works["add"][$this->hierarchy["childs"][$ID]]);
              $One = $this->works["add"][$this->hierarchy["childs"][$ID]];
              // $this->debug("Alex", $One);
            }

            // Format and id are required, not onlines, only KXP, not Journals & Articles
            if (
              !isset($One["format"])                || !$One["format"]
              || !isset($One["online"])                ||  $One["online"]
              || !isset($One["itemcount"]["physical"]) || !$One["itemcount"]["physical"]
              || !isset($One["owner"])                 || !$One["owner"]
              || ($One["format"] == "Journal" && $JWI)
              // || $this->getCollectionFromPPN($ID) != "KXP" // OLC Articles with KXP Parent are possible
              || !isset($One["ilngroups"])             || !is_array($One["ilngroups"])
              || !count($One["ilngroups"])
            ) {
              $this->debug("SKIP 333", $One);
              continue;
            }

            if (!$Step || !in_array($One["format"], array("Journal"))) {
              if (!isset($this->paias[$ID]))            $this->paias[$ID]           = array();
              if (!isset($this->results["paia"][$ID]))  $this->results["paia"][$ID] = array();

              // $this->debug("ALX334PPN", $One["ilngroups"]);

              foreach ($One["ilngroups"] as $ILN => $EPNs) {
                // Skip paia on not configured ilns
                // Auf die ILN pruefen, nicht auf ein flaches ["bmsid"] - das
                // gibt es in der verschachtelten Form nicht, die Schleife
                // uebersprang deshalb JEDE ILN (21.08.2026).
                // $this->debug("ILN_CHECK", ["ILN" => $ILN, "iln2bms_keys" => array_keys($this->configs["iln2bms"] ?? [])]);
                if (!isset($this->configs["iln2bms"][$ILN]))   continue;

                $BMSID = $this->setBMS($ILN);
                if (!$BMSID)  $this->finalize(array("status" => -1, "code" => "missingbms"));

                // $this->debug("EPNs", $EPNs);

                $Host = (MODE == "production")                  ? $this->bms[$BMSID]["apiprod"]    : $this->bms[$BMSID]["apitest"];
                $ISIL = isset($this->bms[$BMSID]["isil"]) ? $this->bms[$BMSID]["isil"] . "/" : "";

                if ($this->display["paia"]) {
                  if ($this->params["shift"]) $this->results["paia"][$ID][] = array("Parentshift: " . $this->params["childid"] . " -> " . $this->params["parentid"]);
                  $this->results["paia"][$ID][] = array("ILN: " . $ILN, "Host: " . $Host . "/" . $ISIL, "EPN(s): " . implode(", ", $EPNs));
                }

                $Tmp = $this->bmscon->getPPN($this->params["shift"] ? $this->params["parentid"] : $ID, $One["format"]);
                // $this->debug("ALX733", $Tmp);
                foreach ($Tmp as $I => $T) {
                  if (isset($T["epn"]) && in_array($T["epn"], $EPNs))   $this->paias[$ID][$I] = $T;
                }
              }
            }
          }
        } else {
          // EPN Mode

          // Reduce MARC items from cache to EPN
          if (isset($this->works["marc"][$ID]["items"][$EPN])) {
            // $this->debug("ALX334EPN", $this->works["marc"][$ID]["items"]);
            // $this->debug("ALX334EPN", $EPN);
            $this->setBMS();
            $this->works["marc"][$ID]["items"] = array($EPN => $this->works["marc"][$ID]["items"][$EPN]);
          }

          // Add paia infos
          $Tmp = $this->bmscon->getEPN($EPN, $this->works["marc"][$ID]["format"]);
          foreach ($Tmp as $I => $T) {
            $this->paias[$ID][$I] = $T;
          }
          // $this->debug("ALX34", $this->paias[$ID]);
        }
      }

      // $this->debug("ALX333", $this->works["marc"][$ID]);

      // ********************************************************
      // ** STEP 5:  Process items                             **
      // ** Results: $this->results                            **
      // ********************************************************
      if (isset($this->works["marc"]) && is_array($this->works["marc"]) && count($this->works["marc"])) {
        // $this->debug("ALEXUSER",$this->works["marc"]);
        $Item   = new item($this);
        foreach ($this->works["marc"] as $ID => $One) {
          $Key = array_search($ID, array_column($this->results["data"], "id")); // $key = 2;
          $this->results["data"][$Key]["items"] = $Item->getItems($ID, $EPN);
        }
      }
    }
  }

  private function ReadArea($Area)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    /*
      $this->results contains data to return to client.
      All attached libraries may write to it.
      Data which is only required to transfer from one library to the other should not be placed there
      Each library has therefore its own "container" accessible to all other libraries: solr, marc, paia, item
    */

    // Initialize
    $this->results = array();

    switch ($Area) {
      case "findex": {
          $this->searchControl();

          // Cleanup mess ( -> should be moved to params to avoid cleaning like this )
          if (isset($this->results["data"]) && is_array($this->results["data"]) && count($this->results["data"])) {
            foreach ($this->results["data"] as $ID => &$One) {
              if (isset($One["artcode"]))            unset($One["artcode"]);
              if (isset($One["collection_details"])) unset($One["collection_details"]);
              if (isset($One["collections"]))        unset($One["collections"]);
              if (isset($One["hapticarticlelinks"])) unset($One["hapticarticlelinks"]);
              if (isset($One["ilngroups"]))          unset($One["ilngroups"]);
              if (isset($One["itemcount"]))          unset($One["itemcount"]);
              if (isset($One["links"]))              unset($One["links"]);
              if (isset($One["marc"]))               unset($One["marc"]);
              if (isset($One["onlinearticlelinks"])) unset($One["onlinearticlelinks"]);
              if (isset($One["owncollection"]))      unset($One["owncollection"]);
              if (isset($One["owner"]))              unset($One["owner"]);
              if (isset($One["ownorg"]))             unset($One["ownorg"]);
              if (isset($One["searches"]))           unset($One["searches"]);
              if (isset($One["series"]))             unset($One["series"]);

              if (!$this->display["items"] && isset($One["items"]))          unset($One["items"]);
              if (!$this->display["bib"]   && isset($One["bibliographic"]))  unset($One["bibliographic"]);
            }
            // Get rid of id keys otherwise the client will sort it by id
            $this->results["data"] = array_values($this->results["data"]);
          }
          break;
        }
      case "facets": {
          $this->results["data"] = array(
            "formats"     => $this->formats,
            "types"       => array("both", "online", "physical"),
            "yearmin"     => 785,
            "yearmax"     => intval(date("Y") + 1),
            "levels"      => array_keys($this->getAdminLevelsDB()),
            "sorts"       => array("ranking", "yeardesc", "yearasc"),
            "languages"   => "ISO 639-2 Codes as Array",
            "topicfacets" => "Keywords as Array",
            "classlocals" => "Local class codes as Array",
            "locations"   => "Locations codes as Array",
            "targets"     => array("meta", "fulltext")
          );

          $this->results["hitspack"] = count($this->results["data"]);
          break;
        }
      case "isils": {
          // Process search
          $Search["search"]    = (isset($this->posted["search"]) && is_array($this->posted["search"]))                              ? $this->posted["search"]        : array();
          $Search["start"]     = (isset($this->posted["start"])  && is_numeric($this->posted["start"]))                             ? intval($this->posted["start"]) : 0;
          $Search["limit"]     = (isset($this->posted["limit"])  && is_numeric($this->posted["start"]) && $this->posted["limit"] <= 1000) ? intval($this->posted["limit"]) : 25;

          $this->results["data"]     = $this->getIsilsDB($Search);
          $this->results["hitspack"] = count($this->results["data"]);
          break;
        }
      // LIGHT: case "classis" (Klassifikationssysteme, esx-107) entfernt
      case "collections": {
          // Process search
          $Search["search"]    = (isset($this->posted["search"]) && is_array($this->posted["search"]))                              ? $this->posted["search"]        : array();
          $Search["start"]     = (isset($this->posted["start"])  && is_numeric($this->posted["start"]))                             ? intval($this->posted["start"]) : 0;
          $Search["limit"]     = (isset($this->posted["limit"])  && is_numeric($this->posted["start"]) && $this->posted["limit"] <= 1000) ? intval($this->posted["limit"]) : 25;

          $this->results["data"]     = $this->findCollectionsDB($Search);
          $this->results["hitspack"] = count($this->results["data"]);
          break;
        }
        /*
      case "norms":
      {
        // Process search
        $Search["search"]    = (isset($this->posted["search"]) && is_array($this->posted["search"]))                              ? $this->posted["search"]        : array();
        $Search["start"]     = (isset($this->posted["start"])  && is_numeric($this->posted["start"]))                             ? intval($this->posted["start"]) : 0;
        $Search["limit"]     = (isset($this->posted["limit"])  && is_numeric($this->posted["start"]) && $this->posted["limit"] <= 1000) ? intval($this->posted["limit"]) : 25;

        $this->results["data"]     = $this->getNormsDB($Search);
        $this->results["hitspack"] = count($this->results["data"]);
        break;
      }
      */
    }
  }


  // Antwort einer BMS-Aktion vereinheitlichen (21.08.2026).
  //
  // Die BMS-Anbindungen antworten in ZWEI Formaten: Request(), Renew() und
  // checkUser() liefern {stat:"success"|"error", mess, more}, waehrend
  // Cancel(), ChangeMail(), ChangePW() und ResetPW() die Antwort der
  // Gegenstelle mit {status, code} durchreichen. Das gilt fuer paia wie folio
  // und in beiden APIs gleichermassen.
  //
  // Der Client prueft "status >= 0". Bei den erstgenannten fehlte das Feld,
  // "undefined >= 0" ist falsch - eine erfolgreiche Verlaengerung wurde
  // deshalb als "Nicht erfolgreich" gemeldet, obwohl die Gegenstelle
  // ausdruecklich Erfolg zurueckgab (gemeldet fuer GoettingenSUB). Dasselbe
  // betraf das Bestellen.
  //
  // "status" wird ERGAENZT, nicht ersetzt: das alte User-Modul wertet
  // weiterhin "stat" aus und darf davon nichts merken.
  private function normalizeBMSResult($SuccessCode)
  {
    if ( !is_array($this->results) )  return;

    if ( !isset($this->results["status"]) && isset($this->results["stat"]) )
    {
      $this->results["status"] = ( $this->results["stat"] === "success" ) ? 0 : -1;
    }
    if ( isset($this->results["status"]) && $this->results["status"] >= 0 && !isset($this->results["code"]) )
    {
      $this->results["code"] = $SuccessCode;
    }
  }

  private function DoRequest()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params
    $Token = (isset($this->posted["tokens"]["user"]) && trim($this->posted["tokens"]["user"])) ? trim($this->posted["tokens"]["user"]) : "";
    $ID    = (isset($this->posted["id"])             && trim($this->posted["id"]))             ? trim($this->posted["id"])             : "";
    $Login = (isset($this->posted["login"])          && trim($this->posted["login"]))          ? trim($this->posted["login"])          : "";
    $Desk  = (isset($this->posted["desk"])           && trim($this->posted["desk"]))           ? trim($this->posted["desk"])           : "";
    $Type  = (isset($this->posted["requesttype"])    && trim($this->posted["requesttype"]))    ? trim($this->posted["requesttype"])    : "";
    if (!$this->bmsid || !$Token || !$ID || !$Login) return array("status" => -1, "code" => "paramsmissing");

    // Attach bms
    if (!$this->setBMS()) return array();

    // bms request
    $Cond = array();
    if ($Desk && isset($this->bms[$this->bmsid]["isil"])) {
      $Cond["desk"] = "http://uri.gbv.de/organization/isil/" . $this->bms[$this->bmsid]["isil"] . "@counter-" . $Desk;
    }
    if (in_array($this->client, array("FlensburgZHB"))) {
      $Cond["feeusertypes"] = true;
    }
    $this->results = $this->bmscon->Request($Login, $ID, $Token, $Cond, $Type);

    $this->normalizeBMSResult("requestsuccessful");
    return $this->results;
  }

  private function DoRenew()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params
    $Token = (isset($this->posted["tokens"]["user"]) && trim($this->posted["tokens"]["user"])) ? trim($this->posted["tokens"]["user"]) : "";
    $ID    = (isset($this->posted["id"])             && trim($this->posted["id"]))             ? trim($this->posted["id"])             : "";
    $Login = (isset($this->posted["login"])          && trim($this->posted["login"]))          ? trim($this->posted["login"])          : "";
    if (!$this->bmsid || !$Token || !$ID || !$Login) return array("status" => -1, "code" => "paramsmissing");

    // Attach bms
    if (!$this->setBMS()) return array();

    // bms renew
    $this->results = $this->bmscon->Renew($Login, $ID, $Token);
    $this->normalizeBMSResult("renewsuccessful");
    return $this->results;
  }

  private function DoCancel()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params
    $Token = (isset($this->posted["tokens"]["user"]) && trim($this->posted["tokens"]["user"])) ? trim($this->posted["tokens"]["user"]) : "";
    $ID    = (isset($this->posted["id"])             && trim($this->posted["id"]))             ? trim($this->posted["id"])             : "";
    $Login = (isset($this->posted["login"])          && trim($this->posted["login"]))          ? trim($this->posted["login"])          : "";
    if (!$this->bmsid || !$Token || !$Login) return array("status" => -1, "code" => "paramsmissing");

    // Attach bms
    if (!$this->setBMS()) return array();

    // bms cancel
    $this->results = $this->bmscon->Cancel($Login, $ID, $Token);
    $this->normalizeBMSResult("cancelsuccessful");
    return $this->results;
  }

  private function DoDeleteMails()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params (login + BMS session token, verified via BMS ReadUser)
    $User = $this->getUser();
    if (!isset($User["username"]) || !trim($User["username"])) return array("status" => -1, "code" => "paramsmissing");

    $this->removeMailsDB($User["username"]);
    return array("status" => 1, "code" => "deletemailssuccessful");
  }

  private function DoChangeMail()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params
    $Token = (isset($this->posted["tokens"]["user"]) && trim($this->posted["tokens"]["user"])) ? trim($this->posted["tokens"]["user"]) : "";
    $New   = (isset($this->posted["newmail"])        && trim($this->posted["newmail"]))        ? trim($this->posted["newmail"])        : "";
    $Login = (isset($this->posted["login"])          && trim($this->posted["login"]))          ? trim($this->posted["login"])          : "";
    if (!$this->bmsid || !$Token || !$New || !$Login) return array("status" => -1, "code" => "paramsmissing");

    // Attach bms
    if (!$this->setBMS()) return array();

    // bms changemail
    $this->results = $this->bmscon->ChangeMail($Login, $Token, $New);
    if (isset($this->results["status"]) && $this->results["status"] >= 0 && !isset($this->results["code"])) {
      $this->results["code"] = "changemailsuccessful";
    }
    return $this->results;
  }

  private function DoChangePW()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params
    $Token = (isset($this->posted["tokens"]["user"]) && trim($this->posted["tokens"]["user"])) ? trim($this->posted["tokens"]["user"]) : "";
    $Old   = (isset($this->posted["oldpw"])          && trim($this->posted["oldpw"]))          ? trim($this->posted["oldpw"])          : "";
    $New   = (isset($this->posted["newpw"])          && trim($this->posted["newpw"]))          ? trim($this->posted["newpw"])          : "";
    $Login = (isset($this->posted["login"])          && trim($this->posted["login"]))          ? trim($this->posted["login"])          : "";
    if (!$this->bmsid || !$Token || !$Old || !$New || !$Login) return array("status" => -1, "code" => "paramsmissing");

    // Attach bms
    if (!$this->setBMS()) return array();

    // Process display
    $Display = array();
    $Display["user"]  = true;
    $Display["items"] = true;
    $Display["fees"]  = true;
    $this->display = $Display;

    // bms changepw
    $this->results = $this->bmscon->ChangePW($Login, $Token, $Old, $New);
    if (isset($this->results["status"]) && $this->results["status"] >= 0 && !isset($this->results["code"])) {
      $this->results["code"] = "changepwsuccessful";
    }

    if (isset($this->results["stat"]) && $this->results["stat"] == "error") return $this->results;
    // $this->debug("ALX", $this->results);
    // Check password change & cancel reservation
    if (isset($this->results["user"]["name"])) {
      $this->results["user"]["changepw"]  = (isset($this->configs["bms"]["changepassword"])    && $this->configs["bms"]["changepassword"]    == "y") ? true : false;
      $this->results["user"]["cancelres"] = (isset($this->configs["bms"]["cancelreservation"]) && $this->configs["bms"]["cancelreservation"] == "y") ? true : false;
    }

    // Termine groups to be displayed in correct order
    $DisplayGroups  = array();
    $PAIAGroups     = array();
    foreach (array("usercollectables", "userorders", "userrentals", "userreservations") as $AccGrp) {
      if (
        isset($this->configs["account"][$AccGrp . ".show"])    && $this->configs["account"][$AccGrp . ".show"]    == "y"
        && isset($this->configs["account"][$AccGrp . ".pos"])     && $this->configs["account"][$AccGrp . ".pos"]     != ""
        && isset($this->configs["account"][$AccGrp . ".paiaids"]) && $this->configs["account"][$AccGrp . ".paiaids"] != ""
      ) {
        $DisplayGroups[$this->configs["account"][$AccGrp . ".pos"]] = $AccGrp;
        $Ps = explode(",", $this->configs["account"][$AccGrp . ".paiaids"]);
        foreach ($Ps as $P) {
          $PAIAGroups[$P] = $AccGrp;
        }
      }
    }

    ksort($DisplayGroups);
    $DisplayGroups = array_values($DisplayGroups);
    $FindGroups     = array_flip($DisplayGroups);

    // $this->debug("ALX23", $PAIAGroups);
    // $this->debug("ALX23", $DisplayGroups);
    // $this->debug("ALX23", $FindGroups);

    $Items          = array();
    foreach ($DisplayGroups as $Key => $Val) {
      $Items["group_" . $Key] = array("label" => $this->texts["account." . $Val], "code" => $Val, "items" => array());
    }

    // Format items
    if (isset($this->results["items"])  && count($this->results["items"])) {
      $PItems = $this->results["items"];
      // $this->debug("ALX23", $PItems);

      // Ensure endtime always available & sort by endtime desc
      foreach ($PItems as $Ind => $Itm) {
        if (!isset($Itm["endtime"]))  $PItems[$Ind]["endtime"] = "";
      }
      array_multisort(array_column($PItems, 'endtime'), SORT_DESC, $PItems);

      foreach ($PItems as $I => $Item) {
        if (
          !isset($Item["status"])
          || !isset($PAIAGroups[$Item["status"]])
          || !isset($FindGroups[$PAIAGroups[$Item["status"]]])
        )  continue;

        $Group = $FindGroups[$PAIAGroups[$Item["status"]]];
        $SID   = substr($Item["storageid"], strpos($Item["storageid"], "@") + 1);
        $Label = isset($Item["label"]) ?  $Item["label"] : "";
        if (strpos($Label, "::") !== false)  $Label = substr($Label, strpos($Label, "::") + 1);
        if (strpos($Label, ":")  !== false)  $Label = substr($Label, strpos($Label, ":") + 1);

        $Tmp = array(
          "id"      => isset($Item["item"])  ? $Item["item"]  : "",
          "title"   => isset($Item["about"]) ? $Item["about"] : "",
          "label"   => $Label,
          "status"  => array(
            "start"       => isset($Item["starttime"]) && trim($Item["starttime"]) ? $this->format2German($Item["starttime"]) : "",
            "end"         => isset($Item["endtime"])   && trim($Item["endtime"])   ? $this->format2German($Item["endtime"])   : "",
            "queue"       => isset($Item["queue"])    ? $Item["queue"]    : "-",
            "renewals"    => isset($Item["renewals"]) ? $Item["renewals"] : "0",
            "maxrenewals" => intval($this->configs["bms"]["maxrenewals"]),
            "reminder"    => isset($Item["reminder"]) ? $Item["reminder"] : "0",
            "state"       => $Item["status"]
          ),
          "storage" => array(
            "name"        => isset($Item["storage"])  ? $Item["storage"]  : "",
            "id"          => $SID,
            "label"       => isset($this->texts["DESK" . substr($SID, 8)]) ? $this->texts["DESK" . substr($SID, 8)] : ""
          ),
          "actions"  => array(
            "cancel"     => isset($Item["cancancel"]) ? $Item["cancancel"] : false,
            "renew"      => isset($Item["canrenew"]) ? $Item["canrenew"] : false
          )
        );
        if (isset($Item["canrenew"]) && $Item["canrenew"] && isset($Item["renewals"]) && $Item["renewals"] <= intval($this->configs["bms"]["maxrenewals"])) {
          $Tmp["actions"]["renew"]  = array(
            "type"    => "renew",
            "id"      => isset($Item["item"])  ? $Item["item"]  : "",
            "login"   => "<login>"
          );
        }
        if (isset($Item["cancancel"]) && $Item["cancancel"]) {
          $Tmp["actions"]["cancel"] = array(
            "type"    => "cancel",
            "id"      => isset($Item["item"])  ? $Item["item"]  : "",
            "login"   => "<login>"
          );
        }
        $Items["group_" . $Group]["items"][] = $Tmp;
      }
      // $this->debug("AlexPaia", $Items);
      // Add user mails
    }
    $this->results["items"] = $Items;
    // Format fees
    if (isset($this->results["fees"]["items"]) && is_array($this->results["fees"]["items"]) && count($this->results["fees"]["items"])) {
      foreach ($this->results["fees"]["items"] as $I => $Item) {
        $this->results["fees"]["items"][$I]["date"] = $this->format2German($Item["date"]);
      }
    }

    // Add others order (mail) to user account
    if (
      isset($this->configs["account"]["userotherorders.show"])
      && $this->configs["account"]["userotherorders.show"] == "y"
    ) {
      $this->results["otherorders"] = array(
        "label" => $this->texts["account.userotherorders"],
        "code"  => "userotherorders",
        "items" => $this->getMailDB($Login)
      );
    }
    return $this->results;
  }

  private function DoResetPW()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Check params
    // $Token = ( isset($this->posted["tokens"]["user"]) && trim($this->posted["tokens"]["user"]) ) ? trim($this->posted["tokens"]["user"]) : "";
    $Login = (isset($this->posted["login"])          && trim($this->posted["login"]))          ? trim($this->posted["login"])          : "";
    // if ( !$this->bmsid || !$Token || !$Login ) return array("status" => -1, "code" => "paramsmissing");

    // Attach bms
    if (!$this->setBMS()) return array();

    // bms changepw
    $this->results = $this->bmscon->ResetPW($Login);
    if (isset($this->results["status"]) && $this->results["status"] >= 0 && !isset($this->results["code"])) {
      $this->results["code"] = "changepwsuccessful";
    }
    return $this->results;
  }

  private function DoSendMail($Name, $Mail, $Subject, $Message, $Type, $Other = array())
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Mode = ucfirst(substr(MODE, 0, 4));
    $Data = array(
      "toname"   => $Name,
      "to"       => $Mail,
      "subject"  => $Mode . ": " . $Subject  . " (" . $this->configs["client"]["appname"] . ")",
      "message"  => $Message
    );

    if (isset($this->configs["hosting"]["mailfrom"]) && trim($this->configs["hosting"]["mailfrom"]))  $Data["from"]     = trim($this->configs["hosting"]["mailfrom"]);
    if (isset($this->configs["client"]["appname"])   && trim($this->configs["client"]["appname"]))    $Data["fromname"] = trim($this->configs["client"]["appname"]);

    if ($this->client != "WeimarBU" || ($this->client == "WeimarBU" && !in_array($Type, ["feedback"]))) {
      $this->storeMailDB($this->getUser(), $Data, $Type, $Other);
    }

    return $this->sendMail($Data);
  }

  private function setBMS($ILN = "")
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // $Logik
    // Es wird die übergebene $this->bmsid verwendet. Es es jedoch einen $EPN Bezug geben, so wird das
    // BMS über $this->iln2bms bestimmt.

    // $this->debug("ALXN33ju", $ILN);
    // $this->debug("ALXN33ju", $this->bms);

    // iln2bms ist nach ILN UND bmsid verschachtelt (eine ILN kann mehrere BMS
    // haben - WeimarBU nutzt testweise EINE ILN fuer paia UND folio). Ist die
    // gepostete bmsid fuer diese ILN gueltig, wird sie genommen (Folio-Login ->
    // Folio-Status); sonst die erste BMS dieser ILN. Frueher las die Zeile ein
    // flaches iln2bms[$ILN]["bmsid"], das es nie gab -> immer $this->bmsid=1
    // (paia). (20.08.2026)
    $BMSID = $this->bmsid;
    // WICHTIG: Nur eingreifen, wenn die ILN TATSAECHLICH mehrere BMS hat
    // (WeimarBU: eine ILN fuer paia UND folio). Bei genau einer BMS bleibt es
    // bei $this->bmsid - sonst aendert sich die BMS-Wahl fuer alle anderen
    // Clients (ErfurtUB fiel dadurch auf 900.Ende zurueck, 20.08.2026).
    // Schalter item.bmsbyiln (03.09.2026): hat ein Client MEHRERE BMS an
    // VERSCHIEDENEN ILNs, muss die Verfuegbarkeit je Exemplar bei DEM BMS
    // erfragt werden, zu dessen ILN das Exemplar gehoert (M980 $2).
    // MagdeburgStendalHS hat 213 (Magdeburg, DE-551) und 230 (Stendal,
    // DE-552): unangemeldet wird bms=1 gepostet, und ein Stendaler Exemplar
    // wurde damit in Magdeburg abgefragt - PAIA lieferte nichts, uebrig blieb
    // ein reiner MARC-Fall (id 588864137, 540.MARC Haptisch - Ausleihbar).
    // Mit dem Schalter zaehlt bei ILN-Bezug die ILN, nicht die geposteten
    // bms-Nummer. Ohne ihn bleibt alles wie bisher - HalleLIT will das
    // ausdruecklich nicht.
    $ProILN = isset($this->configs["item"]["bmsbyiln"]) && $this->configs["item"]["bmsbyiln"] == "y";

    if ($ILN && isset($this->configs["iln2bms"][$ILN]) && is_array($this->configs["iln2bms"][$ILN])
      && (count($this->configs["iln2bms"][$ILN]) > 1 || $ProILN)) {
      $Map = $this->configs["iln2bms"][$ILN];
      if (isset($Map[$this->bmsid]["bmsid"])) {
        $BMSID = $Map[$this->bmsid]["bmsid"];
      } else {
        $First = reset($Map);
        if (isset($First["bmsid"])) $BMSID = $First["bmsid"];
      }
    }

    if (isset($this->bms[$BMSID])) {
      // Jeder Treiber erfuellt den Vertrag in bms.php - user.php kennt nur
      // dessen Methoden. Ein unbekannter Typ fuehrte vorher erst beim ersten
      // Aufruf von $this->bmscon zum Abbruch (14.09.2026).
      $Type =  (isset($this->bms[$BMSID]["type"])) ? $this->bms[$BMSID]["type"] : "";
      if (!in_array($Type, array("paia", "folio"), true)) return false;

      $this->bmscon = new $Type($BMSID, $this);

      // $this->debug("ALX", $Type, true);
      return $BMSID;
    }
    return "";
  }


  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  // Auswahllisten fuer die Oberflaeche - Wert UND fertige Beschriftung
  // (20.08.2026). Bis dahin kannte der Client nur die nackten Werte und baute
  // sich die Textcodes selbst zusammen ("area." + Wert, "sort." + Wert,
  // "format." + kleingeschrieben ohne Leerzeichen, "facet." + Facettenkennung).
  // Damit lagen Textcodes im Client, und die Listen steckten fest in der .env.
  // Beides faellt hier weg: die API liefert {value,label} und die
  // Facetten-Ueberschriften gleich mit. Aufgerufen von system.php::config().
  public function getClientOptions()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT", __METHOD__);

    // Wert -> {value,label}; der Textschluessel entsteht NUR noch hier.
    $Build = function($Values, $Prefix, $Normalize = false)
    {
      $Out = array();
      foreach ( $Values as $V )
      {
        $Code  = $Prefix . ( $Normalize ? strtolower(str_replace(" ", "", $V)) : strtolower($V) );
        $Label = $this->getText($Code);
        $Out[] = array("value" => $V, "label" => ($Label !== "" ? $Label : $V));
      }
      return $Out;
    };

    // Suchbereiche: dieselbe Quelle, aus der auch der Batch die .env speist
    // ($searchOptions in appl.php, siehe applhostingdb.php::getSpecialConfigsDB) -
    // damit sind Werte UND Beschriftungen identisch zu bisher, kommen aber
    // dynamisch statt aus VITE_OPTIONAREAS.
    $Areas  = is_array($this->searchOptions) ? $this->searchOptions : array();
    $Levels = array_keys($this->getAdminLevelsDB());

    $Options = array(
      "areas"   => $Build($Areas,  "area."),
      "levels"  => $Build($Levels, "level."),
      "types"   => $Build(array("both", "online", "physical"), "type."),
      "targets" => $Build(array("meta", "fulltext"),           "target."),
      "sorts"   => $Build(array("ranking", "yeardesc", "yearasc"), "sort."),
      // Formatnamen sind sprechend ("Serial Volume"); die Textschluessel dazu
      // sind kleingeschrieben und ohne Leerzeichen ("format.serialvolume").
      "formats" => $Build(is_array($this->formats) ? $this->formats : array(), "format.", true),
    );

    // Ueberschriften der Facettenbereiche (facet.<id>), passend zu den
    // Kennungen in composables/useFacetOrder.js.
    $FacetIDs = array("sort", "level", "range", "type", "formats", "maps", "formatsmain",
                      "phonetic", "languages", "topicfacets", "classlocals", "locations",
                      "genres", "target", "topicbkls", "topicrvks", "links", "missing");
    $Facets = array();
    foreach ( $FacetIDs as $ID )
    {
      $Label = $this->getText("facet." . $ID);
      if ( $Label !== "" )  $Facets[$ID] = $Label;
    }
    $Options["facets"] = $Facets;

    // Beschriftungen der Verweise (link.<shortcut>) - genutzt u.a. im
    // Normdaten-Dialog fuer die weiterfuehrenden Quellen. Bisher baute der
    // Client daraus selbst "link."+Schluessel. (20.08.2026)
    $Links = array();
    foreach ( (is_array($this->texts) ? $this->texts : array()) as $K => $V )
    {
      if ( strpos($K, "link.") !== 0 )  continue;
      $Lbl = $this->getText($K);
      if ( $Lbl !== "" )  $Links[substr($K, 5)] = $Lbl;
    }
    $Options["links"] = $Links;

    return $Options;
  }

  public function read()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);
    $Area = $this->checkPostedStr("area");
    // "settings" fehlte hier: der Bereich kam mit dem serverseitigen Schalter
    // dazu (11.08.2026), die Liste wurde aber nicht mitgezogen. Ohne den Aufruf
    // bleibt $this->user leer - Bibliotheksbenutzer weisen sich NICHT ueber
    // tokens.user aus (das ist der Sitzungsschluessel des Bibliothekssystems),
    // sondern ueber login + Sitzungsschluessel gegen das ILS; genau das leistet
    // requireAreaUser(). Folge war "no_user_identity" bei jedem Zugriff
    // (14.08.2026).
    // LIGHT: favors/historys/profiles/settings werden nicht serverseitig gespeichert.
    return parent::read();
  }

  public function store()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);
    $Area = $this->checkPostedStr("area");
    // "settings" fehlte hier: der Bereich kam mit dem serverseitigen Schalter
    // dazu (11.08.2026), die Liste wurde aber nicht mitgezogen. Ohne den Aufruf
    // bleibt $this->user leer - Bibliotheksbenutzer weisen sich NICHT ueber
    // tokens.user aus (das ist der Sitzungsschluessel des Bibliothekssystems),
    // sondern ueber login + Sitzungsschluessel gegen das ILS; genau das leistet
    // requireAreaUser(). Folge war "no_user_identity" bei jedem Zugriff
    // (14.08.2026).
    // LIGHT: favors/historys/profiles/settings werden nicht serverseitig gespeichert.
    return parent::store();
  }

  public function getCDBClass($Shortcut, $Code)
  {
    if (APPDEBUG["FKT"]) $this->CS->debug("FKT", __METHOD__);

    if ($Shortcut == "" || $Code == "") return array();

    $Search = array(
      "search" => array(
        "type"   => "code",
        "system" => $Shortcut,
        "code"   => $Code
      ),
      "start"  => 0,
      "limit"  => 1
    );
    // Zwischenspeicher fuer die Auskunft der ZENTRALEN Datenbank (03.09.2026).
    //
    // Gemessen am 03.09.2026: 129 solcher Abfragen fuer 69 Saetze, im Schnitt
    // 44 ms, im Einzelfall bis 531 ms - zusammen 5,7 der rund 6 Sekunden, die
    // der Aufbau einer Trefferseite ohne Cache brauchte. Die Datenbank steht
    // auf einem anderen Rechner (esx-107), daher die Laufzeit.
    //
    // Die Auskunft haengt WEDER am Client NOCH am Satz: "BKL 35.00" bedeutet
    // ueberall dasselbe. Deshalb ein gemeinsamer, dauerhafter Bestand - auch
    // die Fehlanzeige wird gemerkt, sonst kostet gerade sie jedes Mal aufs Neue.
    // Sprachabhaengig ist die Auskunft aber schon (description/parents je
    // Sprache), deshalb steckt die Sprache im Namen.
    $Datei = $this->prepareLookupCachePath($Shortcut . "_" . $this->lang, $Code);
    if (file_exists($Datei) && time() - (int) @filemtime($Datei) < self::LOOKUP_CACHE_MAXALTER) {
      if (MODE != "production") $this->updStatDB("Cache", "Classis.yes");
      $Gemerkt = json_decode(file_get_contents($Datei), true);
      if (is_array($Gemerkt)) return $Gemerkt;
    }
    if (MODE != "production") $this->updStatDB("Cache", "Classis.no");

    $Pack = $this->getClassisDB($Search);
    $CRec = (isset($Pack["0"])) ? $Pack["0"] : array();

    $Cont = array_intersect_key($CRec, array_flip(array("description", "name", "link", "parents")));
    $Parents = (isset($Cont["parents"])) ? $Cont["parents"] : array();
    if (count($Parents)) {
      $Cont["parents"] = array();
      foreach ($Parents as $Search => $Text) {
        $Cont["parents"][] = array(
          "text"   => trim($Text),
          "code"   => $Shortcut . " " . $Search,
          "action" => array(
            "type"   => "search",
            "search" => array(
              "query" => "topic(" . $Shortcut . " " . $Search . ")",
              "type"  => "lukida"
            )
          ),
          "more"   => array()
        );
      }
    }
    // Auskunft merken - auch eine leere, s.o.
    @file_put_contents($Datei, json_encode($Cont, true));
    @chmod($Datei, 0666);

    return $Cont;
  }

  public function getUniqueMultArray($MultArray)
  {
    return array_map("unserialize", array_unique(array_map("serialize", array_values($MultArray))));
  }

  // Hoechstalter fuer Eintraege, deren Aktualitaet NICHT ueber "up_date"
  // geprueft werden kann (Aufrufer ohne Solr-Treffer: Mail, Dialoge,
  // Elternsaetze): ein Tag - so lange, wie frueher das Tagesverzeichnis hielt.
  const MARC_CACHE_MAXALTER = 86400;

  // Gemeinsamer Bestand des ZERLEGTEN MARC-Satzes (03.09.2026, Stufe 2).
  //
  // Anders als der Satz-Cache haengt er weder am Client noch an der Sprache:
  // das Zerlegen des MARC-XML ist fuer alle Bibliotheken dasselbe, und es ist
  // der teure Teil (gemessen 0,56 ms je Satz gegen 0,06 ms fuer alles, was
  // danach daraus gebaut wird). Eine Datei je Satz, alle Clients lesen sie.
  //
  //     caches/ids/<Id in Vierergruppen>/marcxml.inc
  //
  // Gueltig, solange "up_date" aus dem Index derselbe ist - dieselbe Regel wie
  // beim Satz-Cache. Ohne up_date entscheidet das Alter (ein Tag).
  public function readMarcNeutral($ID, $UpDate = "")
  {
    $File = $this->prepareIdCachePath($ID, "marcxml");
    if (!file_exists($File)) return null;

    $Roh = json_decode(file_get_contents($File), true);
    if (!is_array($Roh) || !isset($Roh["marc"])) return null;

    $Alt = isset($Roh["up_date"]) ? (string) $Roh["up_date"] : "";
    if ($UpDate !== "" && $UpDate !== null) {
      if ($Alt === "" || $Alt !== (string) $UpDate) return null;
    } elseif (time() - (int) @filemtime($File) > self::MARC_CACHE_MAXALTER) {
      return null;
    }

    if (MODE != "production") $this->updStatDB("Cache", "Marcxml.yes");
    return array("marc"     => $Roh["marc"],
                 "erstesub" => isset($Roh["erstesub"]) ? $Roh["erstesub"] : array(),
                 "solr"     => isset($Roh["solr"])     ? $Roh["solr"]     : array());
  }

  public function writeMarcNeutral($ID, $Daten, $UpDate = "")
  {
    if (!is_array($Daten) || !isset($Daten["marc"])) return false;
    if ($UpDate === "" && isset($this->works["updates"][$ID])) $UpDate = $this->works["updates"][$ID];

    if (MODE != "production") $this->updStatDB("Cache", "Marcxml.no");
    $File = $this->prepareIdCachePath($ID, "marcxml");
    file_put_contents($File, json_encode(array(
      "up_date"  => (string) $UpDate,
      "marc"     => $Daten["marc"],
      "erstesub" => isset($Daten["erstesub"]) ? $Daten["erstesub"] : array(),
      "solr"     => isset($Daten["solr"])     ? $Daten["solr"]     : array()
    ), true));
    @chmod($File, 0666);
    return true;
  }

  // Einen Satz OHNE Solr-Abfrage bereitstellen (03.09.2026).
  //
  // Der frueher hier liegende clientspezifische Satz-Cache
  // (caches/ids/<Id>/marc_<client>_<sprache>.inc) ist entfallen: seit das
  // Zerlegen des MARC-Satzes und die Auskuenfte der zentralen Datenbank in
  // gemeinsamen Bestaenden liegen, brachte er nichts mehr (gemessen 1609 ms
  // ohne ihn gegen 1302 ms mit ihm - Rauschen), kostete aber je Client eine
  // eigene Datei.
  //
  // Der Name bleibt, weil neun Aufrufer ihn kennen. Die Bedeutung ist jetzt:
  // "baue den Satz aus dem gemeinsamen Bestand, wenn er dort liegt". Damit
  // bleibt der Zweck aller Aufrufstellen erhalten - einen Satz zur Hand haben,
  // ohne den Index zu fragen -, und das Ergebnis gilt fuer jeden Client, auch
  // wenn ihn eine andere Bibliothek geholt hat.
  //
  // ACHTUNG: nicht aus marc.php::getConverts() heraus aufrufen - diese Funktion
  // ruft den MARC-Treiber selbst und wuerde sich sonst im Kreis drehen.
  public function readCache2Marc($ID, $Add = false, $UpDate = null)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Prefix  = $Add ? "PM" : "M";
    $Neutral = $this->readMarcNeutral($ID, $UpDate === null ? "" : (string) $UpDate);
    if (!is_array($Neutral) || !isset($Neutral["solr"]) || !count($Neutral["solr"])) {
      if (MODE != "production") $this->updStatDB("Cache", "Marc.no");
      return false;
    }

    // works["solr"] gehoert dem Aufrufer - kurz ausleihen und zurueckgeben.
    $Merk = isset($this->works["solr"]) ? $this->works["solr"] : null;
    $this->works["solr"] = array(array_merge($Neutral["solr"], array(
      "id"                 => $ID,
      "fullrecord_marcxml" => ""   // der zerlegte Satz liegt im Bestand
    )));

    $Marc = new marc($this);
    $Marc->getConverts($Add, $Prefix);

    if ($Merk === null) unset($this->works["solr"]);
    else                $this->works["solr"] = $Merk;

    $Da = $Add ? isset($this->works["add"][$ID]) : isset($this->works["marc"][$ID]);
    if (MODE != "production") $this->updStatDB("Cache", $Da ? "Marc.yes" : "Marc.no");
    return $Da;
  }

  public function getMarcFieldFirst($ID, $Field, $Subfield)
  {
    if (isset($this->works["marc"][$ID]["marc"][$Field]) && is_array($this->works["marc"][$ID]["marc"][$Field])) {
      foreach ($this->works["marc"][$ID]["marc"][$Field] as $Rec) {
        if (isset($Rec[$Subfield][0]) && isset($Rec[$Subfield][0]))  return $Rec[$Subfield][0];
      }
    }
    return "";
  }

  public function getMarcText($ID, $BibLineID, $ItemNr = "")
  {
    $Return = array();
    if (isset($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"]) && is_array($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"])) {
      if (!$ItemNr) {
        foreach ($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"] as $Item) {
          if (isset($Item["text"])) $Return[] = $Item["text"];
        }
      } else {
        if (isset($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"]["item_" . $ItemNr]["text"])) {
          $Return[] = $this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"]["item_" . $ItemNr]["text"];
        }
      }
    }
    return implode(", ", $Return);
  }

  public function getMarcArray($ID, $BibLineID, $ItemNr = "")
  {
    $Return = array();
    if (isset($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"]) && is_array($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"])) {
      if (!$ItemNr) {
        foreach ($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"] as $Item) {
          if (isset($Item["text"])) $Return[] = $Item["text"];
        }
      } else {
        if (isset($this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"]["item_" . $ItemNr]["text"])) {
          $Return[] = $this->works["marc"][$ID]["bibliographic"]["groupitems"][$BibLineID]["items"]["item_" . $ItemNr]["text"];
        }
      }
    }
    return $Return;
  }

  public function getMarcIntern($ID, $IntID, $Key)
  {
    if (!$ID || !$IntID || !$Key) return "";

    return isset($this->works["marc"][$ID]["bibliographic"]["groupitems"][$IntID]["items"][$Key])
      ? $this->works["marc"][$ID]["bibliographic"]["groupitems"][$IntID]["items"][$Key]
      : "";
  }

  public function action()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Checks
    $Type   = (isset($this->posted["type"])   && $this->posted["type"]   != "") ? strtolower($this->posted["type"])   : "";
    if (!$Type) $this->finalize(array("status" => -1, "code" => "missingvalue"));


    switch ($Type) {
      case "cancel": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoCancel();
          break;
        }
      case "changemail": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoChangeMail();
          break;
        }
      case "deletemails": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoDeleteMails();
          break;
        }
      case "changepw": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoChangePW();
          break;
        }
      case "delivery": {
          $DeliType       = (isset($this->configs["other"]["delivery.type"]) && $this->configs["other"]["delivery.type"] != "")
            ? $this->configs["other"]["delivery.type"]
            : "";

          switch ($DeliType) {
            case "mybibedoc": {
                $User     = $this->getUser();
                if (
                  isset($this->configs["init"]["captcha"]) && $this->configs["init"]["captcha"] == "n" &&
                  (!isset($User["username"]) || !trim($User["username"]))
                ) {
                  $this->results = array("status" => -1, "code" => "mailerror", "more" => "no user");
                  break;
                }

                $this->updStatDB("Action", "Delivery|myBibeDoc");
                $ID   = $this->checkPostedStr("id");
                $Data = array(
                  "author"     => $this->checkDecryptedPostedStr("author"),
                  "formatcode" => $this->checkDecryptedPostedStr("formatcode"),
                  "lang"       => $this->lang,
                  "online"     => $this->checkPostedStr("online"),
                  "pages"      => $this->checkDecryptedPostedStr("pages"),
                  "remark"     => $this->checkDecryptedPostedStr("remark"),
                  "sig"        => $this->checkDecryptedPostedStr("signature"),
                  "stock"      => $this->checkDecryptedPostedStr("stock"),
                  "title"      => $this->checkDecryptedPostedStr("title"),
                  "user"       => $this->checkDecryptedPostedArr("user"),
                  "volume"     => $this->checkDecryptedPostedStr("volume")
                );

                // Check / Set cache
                if (!$this->readCache2Marc($ID)) {
                  // if ( MODE != "production" ) $this->updStatDB("Cache", "Marc.no");
                  // $this->works["marc"][$ID]  = $Marc->getConvert($One);
                  // file_put_contents($File, json_encode($this->works["marc"][$ID], true));
                }

                // $this->debug("ALX393", $this->works["marc"][$ID]);
                // $this->debug("ALX836",  $this->works["marc"][$ID]);

                $Add           = new addons($this, $ID);
                $this->results = $Add->pushMyBibEDoc($Data);

                if (isset($this->results["status"]) && $this->results["status"] == 0 && isset($this->results["more"])) {
                  $More  = $this->results["more"];
                  $CLDID = trim(explode(":", $More)[1]);
                  $Mess  = "<head><style></style></head><body><table>";
                  $Mess .= "<tr><td>Auftragsnummer" . "</td><td>" . $CLDID                            . "</td></tr>";
                  $Mess .= "<tr><td>ID"             . "</td><td>" . $ID                               . "</td></tr>";
                  $Mess .= "<tr><td>Signatur"       . "</td><td>" . $Data["sig"]                      . "</td></tr>";
                  $Mess .= "<tr><td>Seiten"         . "</td><td>" . $Data["pages"]                    . "</td></tr>";
                  $Mess .= "<tr><td>Bemerkung"      . "</td><td>" . $Data["remark"]                   . "</td></tr>";
                  $Mess .= "<tr><td>Titel"          . "</td><td>" . $this->getMarcText($ID, "bib_01") . "</td></tr>";
                  $Mess .= "</table></body>";
                  $SData["title"] = $this->getMarcText($ID, "bib_01");
                  $Tmp = explode(":", $More);
                  $CLDID = isset($Tmp[1]) ? trim($Tmp[1]) : "";
                  $this->storeMailDB(
                    $User,
                    array(
                      "subject" => $CLDID ? "Auftragsnummer: " . $CLDID : "CLD-Auftrag",
                      "message" => $Mess
                    ),
                    $Type,
                    $SData
                  );
                }
                break;
              }
            case "mail": {
                $User     = $this->getUser();
                if (
                  isset($this->configs["init"]["captcha"]) && $this->configs["init"]["captcha"] == "n" &&
                  (!isset($User["username"]) || !trim($User["username"]))
                ) {
                  $this->results = array("status" => -1, "code" => "mailerror", "more" => "no user");
                  break;
                }

                $this->updStatDB("Action", "Delivery|mail");
                $ID   = $this->checkPostedStr("id");
                $Subject  = (isset($this->configs["other"]["delivery.mailsubject"]) && $this->configs["other"]["delivery.mailsubject"] != "")
                  ? $this->configs["other"]["delivery.mailsubject"]
                  : "Scan-Dienst Bestellung";
                $To       = $this->checkDecryptedPostedStr("to");
                $To       = (in_array(MODE, array("local", "development"))) ? "karim@gbv.de" : $To;
                $Name     = explode("@", $To)[0];
                $Stock    = $this->checkDecryptedPostedStr("stock");
                $Sig      = $this->checkDecryptedPostedStr("signature");
                $Vol      = $this->checkDecryptedPostedStr("volume");
                $Remark   = $this->checkDecryptedPostedStr("remark");
                $Pages    = $this->checkDecryptedPostedStr("pages");

                // Check / Set cache
                if (!$this->readCache2Marc($ID)) {
                  // if ( MODE != "production" ) $this->updStatDB("Cache", "Marc.no");
                  // $this->works["marc"][$ID]  = $Marc->getConvert($One);
                  // file_put_contents($File, json_encode($this->works["marc"][$ID], true));
                }

                $Message  = "<head><style>";
                $Message .= "table {font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%;}";
                $Message .= "table td, #customers th {border: 1px solid #ddd; padding: 8px;}";
                $Message .= "table th { padding: 12px 6px 12px 6px; text-align: left; background-color: lightblue; color: white;}";
                $Message .= "</style></head><body>";

                // Part Input
                $Message .= "<h3>Angaben vom Benutzer</h3><table>";
                $Message .= "<tr><td>Jahrgang / Band</td><td>" . $Vol    . "</td></tr>";
                $Message .= "<tr><td>Seiten</td><td>"          . $Pages  . "</td></tr>";
                $Message .= "<tr><td>Anmerkung</td><td>"       . $Remark . "</td></tr>";
                $Message .= "</table>";


                // Part Signatur und Bestand
                $Message .= "<h3>Signatur und Bestand</h3><table>";
                $Message .= "<tr><td>Signatur</td><td>"        . $Sig    . "</td></tr>";
                $Message .= "<tr><td>Bestand</td><td>"         . $Stock  . "</td></tr>";
                $Message .= "</table>";

                // Part User
                if (is_array($User)) {
                  $Message .= "<h3>Benutzer</h3><table>";
                  foreach ($User as $Key => $Val) {
                    $Message .= "<tr><td>" . $this->getText("user." . $Key) . "</td><td>" . $Val . "</td></tr>";
                  }
                  $Message .= "</table>";
                }


                // $this->debug("ALXMAIL", $this->works["marc"][$ID]);

                // Part Medium
                $Message .= "<h3>Medium</h3><table>";
                $Message .= "<tr><td>" . "Titel"      . "</td><td>" . $this->getMarcText($ID, "bib_01") . "</td></tr>";
                $Message .= "<tr><td>" . "Autoren"    . "</td><td>" . $this->getMarcText($ID, "bib_04") . "</td></tr>";
                $Message .= "<tr><td>" . "Beteiligte" . "</td><td>" . $this->getMarcText($ID, "bib_05") . "</td></tr>";
                $Message .= "<tr><td>" . "ID"         . "</td><td>" . $ID                          . "</td></tr>";
                $Message .= "</table>";

                $Message .= "</body>";

                $SData["title"] = $this->getMarcText($ID, "bib_01");
                $this->results = $this->DoSendMail($Name, $To, $Subject, $Message, "Delivery|mail", $SData);
                break;
              }
            default: {
                $this->results = array("status" => -1, "code" => "unknowntype");
                break;
              }
          }
          // Return
          $this->finalize($this->results);
        }
      case "dialog": {
          $this->updStatDB("Action", $Type);
          $ID   = $this->checkPostedStr("id");
          $Type = $this->checkPostedStr("action", "type");
          $Data = $this->checkDecryptedPostedArr("data");
          $Data["user"] = $User = $this->getUser();

          $this->debug("DialogClient", $this->client);
          $this->debug("DialogClient", $this->posted);

          // Lieferdienst: Benutzergruppe pruefen, BEVOR etwas geschieht
          // (09.09.2026). Siehe checkDeliveryUserType() - die Pruefung sitzt
          // jetzt hier auf dem Server und nicht mehr im Client.
          if (!$this->checkDeliveryUserType($Type, $User)) {
            $this->results = array(
              "status" => -1,
              "stat"   => "error",
              "code"   => "delivery.notauthorized",
              "mess"   => array($this->getText("delivery.notauthorized"))
            );
            break;
          }

          // Ohne Datensatz gibt es nichts nachzuladen (09.09.2026).
          //
          // Seit der Dialog auch nach einer erfolglosen Suche angeboten wird
          // (results.nohitsdialog), kommt er ohne "id" an - es gibt ja keinen
          // Treffer. Ohne diese Bedingung liefe readCache2Marc("") ins Leere
          // und danach eine Solr-Suche auf "id:" - eine sinnlose Abfrage,
          // deren Ergebnis niemand benutzt.
          if ($ID !== "" && !$this->readCache2Marc($ID)) {
            // Process search
            $Search  = array();
            $Search["search"]    = array("type" => "lukida", "query" => "id:" . $ID);
            $Search["start"]     = (isset($this->posted["start"])    && is_numeric($this->posted["start"]))                             ? intval($this->posted["start"]) : 0;
            $Search["limit"]     = (isset($this->posted["limit"])    && is_numeric($this->posted["start"]) && $this->posted["limit"] <= 1000) ? intval($this->posted["limit"]) : 25;
            $Level               = (isset($this->posted["level"])    && trim($this->posted["level"]))                                    ? trim($this->posted["level"])   : "";
            $Search["url"]       = $this->buildBasicURL($Level);
            $Search["sort"]      = (isset($this->posted["sort"])     && in_array($this->posted["sort"], array("yeardesc", "yearasc", "acqdesc")))    ? $this->posted["sort"]          : "";
            $Search["spell"]     = false;
            $Search["phonetic"]  = false;
            $Search["facets"]    = array();
            $Search["counts"]    = array();
            $this->search = $Search;

            // Process display
            $Display = array();
            $Display["paia"]     = false;
            $Display["marc"]     = false;
            $Display["comb"]     = false;
            $Display["logs"]     = false;
            $Display["bib"]      = false;
            $Display["items"]    = false;
            $this->display = $Display;

            // STEP 1 - Attach Solr & search
            $this->readConvertFindex();
          }
          $this->results = $this->doActionLocal($Type, $ID, $Data, $this->getDialogMailDB($Type));
          break;
        }
      case "mail2library": {
          // define("REALMAIL", true);
          $ID   = $this->checkPostedStr("id");
          $User = $this->getUser();
          if (
            isset($this->configs["init"]["captcha"]) && $this->configs["init"]["captcha"] == "n" &&
            (!isset($User["username"]) || !trim($User["username"]))
          ) {
            $this->results = array("status" => -1, "code" => "mailerror", "more" => "no user");
            break;
          }
          $Subject  = $this->checkDecryptedPostedStr("subject");
          if ($Subject) {
            $Tmp     = (isset($this->texts[$Subject])) ? $this->texts[$Subject] : array();
            $Subject = (isset($Tmp["german"]))         ? $Tmp["german"]         : "Betreff";
          }
          $UserInpt = $this->checkDecryptedPostedArr("userinput");
          $MailMeta = $this->checkDecryptedPostedArr("meta");
          $MailItem = $this->checkDecryptedPostedArr("item");
          // $BibLines = $this->posted["biblines"];
          $MailType = isset($MailMeta["type"]) && trim($MailMeta["type"]) ? (string) strtolower(trim($MailMeta["type"])) : "";
          if ($MailType == "") {
            $this->results = array("status" => -1, "code" => "mailerror", "more" => "wrong type");
            break;
          }

          // Check / Set cache
          if (!$this->readCache2Marc($ID)) {
            // if ( MODE != "production" ) $this->updStatDB("Cache", "Marc.no");
            // $this->works["marc"][$ID]  = $Marc->getConvert($One);
            // file_put_contents($File, json_encode($this->works["marc"][$ID], true));
          }
          // $this->debug("ALX393", $this->works["marc"][$ID]);

          $this->updStatDB("Action", "Mail|" . $MailType);

          $Tos   = array();
          $Names = array();
          $Mode  = (MODE == "production") ? "prod" : "test";
          if (isset($this->configs["mail." . $Mode][$MailType]) && trim($this->configs["mail." . $Mode][$MailType])) {
            $Tos   = explode(",", trim($this->configs["mail." . $Mode][$MailType]));
            foreach ($Tos as $To) {
              $Names[] = ucfirst(explode("@", $To)[0]);
            }
          } else {
            $MailData = array(
              "Client"   => $this->client,
              "MailType" => $MailType,
              "Error"    => "Mailtype nicht gefunden"
            );

            $this->sendErrorMail("Config-Mail-Missing", $MailData);
          }

          // Mail header
          $Head = "";
          if (isset($this->configs["mail"]["style2library"]) && $this->configs["mail"]["style2library"] == "table") {
            $Head    = "<head><style>";
            $Head   .= "table {font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%;}";
            $Head   .= "table td, #customers th {border: 1px solid #ddd; padding: 8px;}";
            $Head   .= "table th { padding: 12px 6px 12px 6px; text-align: left; background-color: lightblue; color: white;}";
            $Head   .= "</style></head>";
          }

          // Start mail body
          $Message  = "";

          // Part Development
          if (in_array(MODE, array("local", "development"))) {
            $Message .= "<h3>Angaben fürs Development</h3><table>";
            $Message .= "<tr><td>Umgebung</td><td>" . MODE . "</td></tr>";
            $Message .= "<tr><td>Methode</td><td>mail2library</td></tr>";
            $Message .= "<tr><td>MailType</td><td>" . $MailType . "</td></tr>";
            $Message .= "</table>";
          }

          // Part Input
          if (isset($UserInpt) && is_array($UserInpt)) {
            $Message .= "<h3>Angaben Benutzer*in</h3><table>";
            foreach ($UserInpt as $Key => $One) {
              $Tmp  = (isset($this->texts["user." . $Key])) ? $this->texts["user." . $Key] : array();
              $Feld = (isset($Tmp["german"]))     ? $Tmp["german"]     : $Key;
              $Message .= "<tr><td>" . $Feld . "</td><td>" . $One . "</td></tr>";
            }
            $Message .= "</table>";
          }

          // Part User
          $MailFields = (isset($this->configs["mail"]["userfields"]) && trim($this->configs["mail"]["userfields"])) ? explode(",", $this->configs["mail"]["userfields"]) : [];
          if (is_array($User)) {
            $Message .= "<h3>Benutzer*in</h3><table>";
            foreach ($User as $Key => $Val) {
              if (in_array($Key, $MailFields)) $Message .= "<tr><td>" . $this->getText("user." . $Key) . "</td><td>" . $Val . "</td></tr>";
            }
            $Message .= "</table>";
          }

          // Part Medium
          $Message .= "<h3>Medium</h3><table>";
          $Message .= "<tr><td>" . "Titel"      . "</td><td>" . $this->getMarcText($ID, "bib_01") . "</td></tr>";
          $Message .= "<tr><td>" . "Autoren"    . "</td><td>" . $this->getMarcText($ID, "bib_04") . "</td></tr>";
          $Message .= "<tr><td>" . "Beteiligte" . "</td><td>" . $this->getMarcText($ID, "bib_05") . "</td></tr>";
          $Message .= "<tr><td>" . "Publiziert" . "</td><td>" . $this->getMarcText($ID, "bib_12") . "</td></tr>";
          $Message .= "<tr><td>" . "Siehe auch" . "</td><td>" . $this->getMarcText($ID, "bib_22") . "</td></tr>";
          $Message .= "<tr><td>" . "Gattung"    . "</td><td>" . $this->getMarcText($ID, "bib_35") . "</td></tr>";
          $Message .= "<tr><td>" . "ID"         . "</td><td>" . $ID                               . "</td></tr>";
          $Message .= "</table>";

          // Part Item
          if (isset($MailItem) && is_array($MailItem) && count($MailItem)) {
            $Message .= "<h3>Exemplar</h3><table>";
            foreach ($MailItem as $Key => $One) {
              $Tmp  = (isset($this->texts["user." . $Key])) ? $this->texts["user." . $Key] : array();
              $Feld = (isset($Tmp["german"]))     ? $Tmp["german"]     : $Key;
              $Message .= "<tr><td>" . $Feld . "</td><td>" . $One . "</td></tr>";
            }
            $Message .= "</table>";
          }

          // Part Additional
          if (isset($MailMeta) && is_array($MailMeta) && count($MailMeta)) {
            $Message .= "<h3>Weitere Angaben</h3><table>";
            if (isset($MailMeta["useragent"]) && $MailMeta["useragent"] != "") {
              $Message .= "<tr><td>UserAgent</td><td>" . $MailMeta["useragent"] . "</td></tr>";
            }
            if ($MailType == "feedback") {
              $Message .= "<tr><td>IP</td><td>" . $this->clientip . "</td></tr>";
            }
            if (isset($MailMeta["medialink"]) && $MailMeta["medialink"] != "") {
              $Message .= "<tr><td>Link zum Medium</td><td>" . $MailMeta["medialink"] . "</td></tr>";
            }
            $Message .= "</table>";
          }
          $SData["title"] = $this->getMarcText($ID, "bib_01");

          // Part 1 Mail to library
          $this->results  = $this->DoSendMail(
            $Names,
            $Tos,
            $Subject,
            $Head .
              "<body>" . $Message . "</body>",
            $MailType,
            $SData
          );

          // Part 2 Mail Copy an den user selber
          if (
            isset($this->configs["mail"]["copy2user"]) && $this->configs["mail"]["copy2user"] == "y"
            && isset($User["email"]) && trim($User["email"]) && isset($User["name"]) && trim($User["name"])
          ) {
            // Set own user
            $Tos   = array($User["email"]);
            $Names = array($User["name"]);

            // Prepend intro message
            $Intro   = $this->getText('mailcopylibrary');
            $Message = "<h4><b><i>" . $Intro . "</i></b></h4><br><br>" . $Message;
            $this->results = $this->DoSendMail(
              $Names,
              $Tos,
              $Subject,
              $Head .
                "<body>" . $Message . "</body>",
              $MailType,
              $SData
            );
          }
          break;
        }
      case "mail2user": {
          $User = $this->getUser();
          $IDs  = $this->checkPostedArr("ids");
          if (
            isset($this->configs["init"]["captcha"]) && $this->configs["init"]["captcha"] == "n" &&
            (!isset($User["username"]) || !trim($User["username"]))
          ) {
            $this->results = array("status" => -1, "code" => "mailerror", "more" => "no user");
            break;
          }
          $Subject  = $this->checkDecryptedPostedStr("subject");
          $Name     = $this->checkDecryptedPostedStr("mailname");
          $Msg      = $this->checkDecryptedPostedStr("message");
          $FromName = $this->checkDecryptedPostedStr("fromname");
          $MediaLink = $this->checkDecryptedPostedStr("medialink");
          $To       = $this->checkPostedStr("mailto");
          $MailMeta = $this->checkDecryptedPostedArr("meta");

          // $this->debug("ALX", $MailMeta["medialink"]);

          // Check / Set cache
          foreach ($IDs as $ID) {
            if (!$this->readCache2Marc($ID)) {
              // if ( MODE != "production" ) $this->updStatDB("Cache", "Marc.no");
              // $this->works["marc"][$ID]  = $Marc->getConvert($One);
              // file_put_contents($File, json_encode($this->works["marc"][$ID], true));
            }
            $this->updStatDB("Action", "Mail|User");
          }

          $Head = "";
          if (isset($this->configs["mail"]["style2user"]) && $this->configs["mail"]["style2user"] == "table") {
            $Head    = "<head><style>";
            $Head   .= "table {font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%;}";
            $Head   .= "table td, #customers th {border: 1px solid #ddd; padding: 8px;}";
            $Head   .= "table th { padding: 12px 6px 12px 6px; text-align: left; background-color: lightblue; color: white;}";
            $Head   .= "</style></head>";
          }

          $Message = "";

          // Part Development
          if (in_array(MODE, array("local", "development"))) {
            $Message .= "<h3>Angaben fürs Development</h3><table>";
            $Message .= "<tr><td>Umgebung</td><td>" . MODE . "</td></tr>";
            $Message .= "<tr><td>Methode</td><td>mail2user</td></tr>";
            $Message .= "</table>";
          }
          $PreMess = $this->getText('mail.initialtext');
          if (isset($PreMess) && trim($PreMess)) {
            $Message .= "<i>" . $PreMess . "</i><br><br>";
          }

          // Part User
          $Message .= "<h3>Von Benutzer*in " . $FromName . "</h3><table>";
          $Message .= "<tr><td>" . $Msg . "</td></tr>";
          $Message .= "</table>";

          foreach ($IDs as $Idx => $ID) {
            // Part Medium
            $Message .= "<h3>Medium Nr. " . ($Idx + 1) . "</h3><table>";
            $Message .= "<tr><td>" . "Titel"           . "</td><td>" . $this->getMarcText($ID, "bib_01") . "</td></tr>";
            $Message .= "<tr><td>" . "Autoren"         . "</td><td>" . $this->getMarcText($ID, "bib_04") . "</td></tr>";
            $Message .= "<tr><td>" . "Beteiligte"      . "</td><td>" . $this->getMarcText($ID, "bib_05") . "</td></tr>";
            $Message .= "<tr><td>" . "Publiziert"      . "</td><td>" . $this->getMarcText($ID, "bib_12") . "</td></tr>";
            $Message .= "<tr><td>" . "ID"              . "</td><td>" . $ID                               . "</td></tr>";
            $Message .= "<tr><td>" . "Link zum Medium"  . "</td><td><a href='" . str_replace("\"", "", $MailMeta["medialink"]) . "/search?q=id:" . $ID . "'>hier</a></td></tr>";
            $Message .= "</table>";
          }

          // Part 1 Mail to library
          $SData["title"] = $this->getMarcText($ID, "bib_01");
          $this->results = $this->DoSendMail(
            $Name,
            $To,
            $Subject,
            $Head .
              "<body>" . $Message . "</body>",
            "user",
            $SData
          );

          // Part 2 Mail Copy an den user selber
          if (
            isset($this->configs["mail"]["copy2user"]) && $this->configs["mail"]["copy2user"] == "y"
            && isset($User["email"]) && trim($User["email"]) && isset($User["name"]) && trim($User["name"])
          ) {
            // Set own user
            $To   = $User["email"];
            $Name = $User["name"];

            // Prepend intro message
            $Intro   = $this->getText('mailcopyuser');
            $Message = "<h4><b><i>" . $Intro . "</i></b></h4><br><br>" . $Message;

            $this->results = $this->DoSendMail(
              $Name,
              $To,
              $Subject,
              $Head .
                "<body>" . $Message . "</body>",
              "user",
              $SData
            );
          }
          break;
        }
      case "request": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoRequest();
          break;
        }
      case "renew": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoRenew();
          break;
        }
      case "resetpw": {
          $this->updStatDB("Action", ucfirst($Type));
          $this->results = $this->DoResetPW();
          break;
        }

      default: {
          $this->results = array("status" => -1, "code" => "unknowntype");
        }
    }

    // Return
    $this->finalize($this->results);
  }

  // Naechstweiteres Level zu einem gegebenen (17.08.2026).
  //
  // Findet die Suche nichts, soll die Oberflaeche anbieten, eine Stufe weiter
  // zu suchen - nicht gleich "alles". Bisher kannte nur das Altmodul diesen
  // Fall und schlug dort fest "all" vor; Zwischenstufen fielen unter den
  // Tisch. Beispiel FlensburgZHB: "euf" schraenkt auf zwei Kriterien ein
  // (holdingilns + buildings), "start" nur auf eines, "all" auf keines - von
  // "euf" aus ist "start" der richtige naechste Schritt, nicht "all".
  //
  // "Weiter" heisst: WENIGER gefuellte Filterkriterien. Gezaehlt wird ueber die
  // sieben Filterspalten der Tabelle levels; leere Zeichenketten und leere
  // JSON-Listen zaehlen nicht mit. Von den sieben sind heute nur vier je
  // gefuellt (holdingilns, locations, classlocals, buildings), die uebrigen
  // stehen der Vollstaendigkeit halber mit drin.
  //
  // Gewaehlt wird die naechste Stufe, nicht die weiteste: unter allen Leveln
  // mit weniger Kriterien das mit den MEISTEN - bei Gleichstand das mit der
  // kleinsten Sortierung.
  private function countLevelFilters($Level)
  {
    if (!is_array($Level))  return 0;

    // "holdingshaptic" zaehlt mit (09.09.2026): es ist ein Filterkriterium wie
    // die anderen - ohne den Eintrag schluege die Oberflaeche einen Suchraum
    // als "weiter" vor, der in Wahrheit enger ist.
    $Spalten = array("holdingilns", "holdingisils", "holdingshaptic", "locations", "dois", "classlocals", "buildings", "ipranges");
    $Anzahl  = 0;

    foreach ($Spalten as $Spalte) {
      if (!isset($Level[$Spalte]))  continue;
      $Wert = $Level[$Spalte];

      if (is_array($Wert)) {
        if (count($Wert))  $Anzahl++;
        continue;
      }
      $Wert = trim((string) $Wert);
      // Leere JSON-Listen kommen je nach Treiber als "[]" oder "{}" an.
      if ($Wert === "" || $Wert === "[]" || $Wert === "{}" || strtolower($Wert) === "null")  continue;
      $Anzahl++;
    }

    return $Anzahl;
  }

  private function getWiderLevel($Level)
  {
    if (!trim((string) $Level) || !isset($this->levels[$Level]))  return "";

    $Jetzt = $this->countLevelFilters($this->levels[$Level]);
    if ($Jetzt <= 0)  return "";      // "all" o.ae. - breiter geht es nicht

    $Beste  = "";
    $BesteN = -1;
    $BesteS = PHP_INT_MAX;

    foreach ($this->levels as $Name => $Eine) {
      if ($Name === $Level)  continue;

      $N = $this->countLevelFilters($Eine);
      if ($N >= $Jetzt)  continue;    // nicht weiter als das aktuelle

      $S = isset($Eine["sort"]) ? (int) $Eine["sort"] : 999;

      if ($N > $BesteN || ($N === $BesteN && $S < $BesteS)) {
        $Beste  = $Name;
        $BesteN = $N;
        $BesteS = $S;
      }
    }

    return $Beste;
  }

  public function buildBasicURL($Level = "all")
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $LevWHaptic = (isset($this->configs["facet"]["levelswithhaptics"]) && $this->configs["facet"]["levelswithhaptics"] == "y") ? true : false;

    // Basic url
    $URL  = $this->configs["solr"]["prot"] . $this->configs["solr"]["host"];
    $URL .= ($this->configs["solr"]["port"] != "80") ? ":" . $this->configs["solr"]["port"] : "";
    $URL .= "/" . $this->configs["solr"]["path"] . "/select?wt=" . $this->configs["solr"]["wt"] . "&defType=edismax";

    if (!trim($Level) || (!array_key_exists($Level, $this->levels))) $Level = "all";

    // Collections
    $Level = $this->levels[$Level];

    // $Level["collectionsrem"]     = array();
    // $Level["collectionsadd"]     = array();
    // $Level["collectionsaddfull"] = array("MPG E-Books");

    // $this->debug("AlX218", $Level);

    if ((isset($Level["collectionsrem"])     && is_array($Level["collectionsrem"])     && count($Level["collectionsrem"])
        && isset($Level["collectionsadd"])     && is_array($Level["collectionsadd"])     && count($Level["collectionsadd"]))
      || (isset($Level["collectionsaddfull"]) && is_array($Level["collectionsaddfull"]) && count($Level["collectionsaddfull"]))
    ) {
      // Collections will be filtered

      // Main Logic if all three are used
      // ((* AND NOT REM) OR ADD) OR FULL

      // ADD and REM used always together
      // rem and add must be present together > this is the use case ((ALL but not (BAD)) OR GOOD)

      // With full brackets
      // (((* AND NOT (REM)) OR (ADD)) OR (FULL))

      // ADD & REM & FULL                           ADD & REM                       FULL
      // (((* AND NOT (REM)) OR (ADD)) OR (FULL))   ((* AND NOT (REM)) OR (ADD))    (FULL)


      $URL .= "&fq=collection:";

      // Part One: All Three
      if (
        isset($Level["collectionsrem"])         && is_array($Level["collectionsrem"])     && count($Level["collectionsrem"])
        && isset($Level["collectionsadd"])         && is_array($Level["collectionsadd"])     && count($Level["collectionsadd"])
        && isset($Level["collectionsaddfull"])     && is_array($Level["collectionsaddfull"]) && count($Level["collectionsaddfull"])
      ) {
        $URL .= "(((*+AND+NOT+collection:(" . implode("+OR+", array_map(array($this, "formatSolrItemColl"), $Level["collectionsrem"]))     . "))";
        $URL .=          "+OR+collection:(" . implode("+OR+", array_map(array($this, "formatSolrItemColl"), $Level["collectionsadd"]))     . "))";
        $URL .=          "+OR+collection:(" . implode("+OR+", array_map(array($this, "formatSolrItemColl"), $Level["collectionsaddfull"])) . "))";

        if ($this->client == "VechtaUB") $URL .= "+AND+NOT+collection_details:FID-ASIEN";
      }
      // Part Two: ONLY ADD & REM
      elseif (
        isset($Level["collectionsrem"])     && is_array($Level["collectionsrem"])     && count($Level["collectionsrem"])
        && isset($Level["collectionsadd"])     && is_array($Level["collectionsadd"])     && count($Level["collectionsadd"])
      ) {
        $URL .= "((*+AND+NOT+collection:(" . implode("+OR+", array_map(array($this, "formatSolrItemColl"), $Level["collectionsrem"]))      . "))";
        $URL .=         "+OR+collection:(" . implode("+OR+", array_map(array($this, "formatSolrItemColl"), $Level["collectionsadd"]))      . "))";
      }
      // Part Three: ONLY FULL
      elseif (isset($Level["collectionsaddfull"]) && is_array($Level["collectionsaddfull"]) && count($Level["collectionsaddfull"])) {
        $URL .=                         "(" . implode("+OR+", array_map(array($this, "formatSolrItemColl"), $Level["collectionsaddfull"])) . ")";
      }
    }

    /*
    elseif ( isset($Level["collectionsaddfull"]) && is_array($Level["collectionsaddfull"]) && count($Level["collectionsaddfull"]) )
    {
      // if not both present > this is the use case (ONLY GOOD)
      $URL .= "&fq=collection:(("
               . implode(" +OR+ ", array_map(array($this, "formatSolrItem"), $Level["collectionsaddfull"])) . ")";
      if ( isset($Level["dois"]) && is_array($Level["dois"]) && count($Level["dois"]) )
      {
        $URL .= "+OR+doi_str_mv:(" . implode("+OR+",  array_map(array($this, "formatSolrItem"), $Level["dois"])) . ")";
      }
      $URL .= ")";
    }
    */



    // Bestaende des Suchraums (09.09.2026 um "holdingshaptic" erweitert)
    //
    // "holdingilns"/"holdingisils" nehmen den Bestand einer Einrichtung ganz
    // auf. "holdingshaptic" nimmt ihn NUR mit haptischem Titel - gedacht fuer
    // Nachbarbibliotheken, deren gedruckter Bestand zum Suchraum gehoert,
    // deren E-Medien aber nicht (MagdeburgMZB "start": eigene ILN 101 ganz,
    // 100/213/400 nur haptisch; 34,2 Mio. Treffer werden so zu 18,2 Mio.).
    //
    // "Haptisch" ist kein neuer Begriff: remote_bool:false ist dasselbe, was
    // die Typ-Facette unter "type.physical" anbietet - bei MagdeburgMZB
    // woertlich "Haptisch". Die Zahl an der Facette und dieser Suchraum
    // koennen deshalb nicht auseinanderlaufen.
    //
    // Die Teile sind ODER-verknuepft und werden geklammert, sobald es mehr
    // als einen gibt: sonst zoege das AND im haptischen Teil den ganzen
    // Ausdruck an sich.
    $Vals = array();
    if (isset($Level["holdingilns"])  && is_array($Level["holdingilns"])  && count($Level["holdingilns"]))  $Vals = array_merge($Vals, $Level["holdingilns"]);
    if (isset($Level["holdingisils"]) && is_array($Level["holdingisils"]) && count($Level["holdingisils"])) $Vals = array_merge($Vals, $Level["holdingisils"]);
    $Hapt = (isset($Level["holdingshaptic"]) && is_array($Level["holdingshaptic"])) ? $Level["holdingshaptic"] : array();

    if (count($Vals) || count($Hapt)) {
      $Teile = array();
      if (isset($Level["collectionsaddfull"]) && is_array($Level["collectionsaddfull"]) && count($Level["collectionsaddfull"])) {
        $Teile[] = "collection:(" .
          implode("+OR+", array_map(array($this, "formatSolrItem"), $Level["collectionsaddfull"])) . ")";
      }
      if (count($Vals)) {
        $Teile[] = "collection_details:(" .
          implode("+OR+", array_map(array($this, "formatSolrItem"), $Vals)) . ")";
      }
      if (count($Hapt)) {
        $Teile[] = "(collection_details:(" .
          implode("+OR+", array_map(array($this, "formatSolrItem"), $Hapt)) . ")+AND+remote_bool:false)";
      }
      $URL .= "&fq=" . (count($Teile) > 1 ? "(" . implode("+OR+", $Teile) . ")" : $Teile[0]);
    }

    if (isset($Level["classlocals"]) && is_array($Level["classlocals"]) && count($Level["classlocals"])) {
      $URL .= "&fq=class_local_iln:(" . implode("+OR+", array_map(array($this, "formatSolrItem"), $Level["classlocals"])) . ")";
    }
    if (isset($Level["locations"]) && is_array($Level["locations"]) && count($Level["locations"])) {
      $Ls = array();  // Locs
      $Bs = array();  // Bibs
      foreach ($Level["locations"] as $X) {
        if (strpos($X, "@") === false) {
          $Ls[] = $this->formatSolrItem($X);
        } else {
          $Bs[] = $this->formatSolrItem($X);
        }
      }
      if (count($Ls)) {
        // Doppelte Klammer wichtig zur Unterscheidung von Facette location
        $URL .= "&fq=(standort_iln_str_mv:(" . implode("+OR+", $Ls) . "))";
      }
      if (count($Bs)) {
        $URL .= "&fq=(selectbib_iln_str_mv:(" . implode("+OR+", $Bs) . "))";
      }
    }
    if (isset($Level["buildings"]) && is_array($Level["buildings"]) && count($Level["buildings"])) {
      $URL .= "&fq=(building:(" . implode("+OR+", array_map(array($this, "formatSolrItem"), $Level["buildings"])) . ")";
      if ($LevWHaptic) {
        $URL .= "+OR+remote_bool:false";
      }
      $URL .= ")";
    }

    // $this->debug("AlX219", $URL);
    return $URL;
  }

  public function export()
  {
    // Detemine appl & area
    $Appl   = (isset($this->posted["appl"])   && trim($this->posted["appl"]))   ? trim(strtolower($this->posted["appl"]))   : "";
    $Area   = (isset($this->posted["area"])   && trim($this->posted["area"]))   ? trim(strtolower($this->posted["area"]))   : "";
    $Format = (isset($this->posted["format"]) && trim($this->posted["format"])) ? trim(strtolower($this->posted["format"])) : "";
    $IDs    = $this->checkPostedArr("ids");

    // Checks
    if (($Appl && $Appl != $this->appl)              || !$Appl) $this->finalize(array("status" => -1, "code" => "wrongappl"));
    if (($Area && !in_array($Area, $this->getAreas)) || !$Area) $this->finalize(array("status" => -1, "code" => "wrongarea"));
    if (!$Format)                                               $this->finalize(array("status" => -1, "code" => "missingformat"));
    if (empty($IDs))                                            $this->finalize(array("status" => -1, "code" => "missingids"));

    if (MODE == "development")     $Mode = "dev";
    elseif (MODE == "production")  $Mode = "prod";
    else                             $Mode = MODE;

    $Config = array(
      'base_url'       => $this->configs["domain"][$Mode],
      'title'          => $this->configs["meta"]["description"],
      'ezbbibid'       => $this->configs["resolver"]["ezbbibid"],
      'openurl'        => $this->configs["resolver"]["openurl"]
    );

    $exp = new export($this, $Config);

    // Format auf einen Namen bringen, den exportfile() kennt - und Unbekanntes
    // abweisen (27.08.2026). Vorher ging JEDE Zeichenkette durch: die Aktion
    // lieferte dann eine Datei ohne Feldkennungen und das Protokoll fuellte
    // sich mit "Undefined array key". "ris"/"zotero" werden zu "citavi",
    // "csv" zu "excel"; "refworks" ist kein Dateiformat und gehoert zu
    // "resolve", nicht hierher.
    $Format = $exp->formatname($Format);
    if (!$Format)                                               $this->finalize(array("status" => -1, "code" => "wrongformat"));

    //Alle Eintr?ge sammeln
    $exportString = "";

    foreach ($IDs as $ID) {
      if (!$this->readCache2Marc($ID)) {
        // Process search
        $Search  = array();
        $Search["search"]    = array("type" => "lukida", "query" => "id:" . $ID);
        $Search["start"]     = (isset($this->posted["start"])    && is_numeric($this->posted["start"]))                             ? intval($this->posted["start"]) : 0;
        $Search["limit"]     = (isset($this->posted["limit"])    && is_numeric($this->posted["start"]) && $this->posted["limit"] <= 1000) ? intval($this->posted["limit"]) : 25;
        $Level               = (isset($this->posted["level"])    && trim($this->posted["level"]))                                    ? trim($this->posted["level"])   : "";
        $Search["url"]       = $this->buildBasicURL($Level);
        $Search["sort"]      = (isset($this->posted["sort"])     && in_array($this->posted["sort"], array("yeardesc", "yearasc", "acqdesc")))    ? $this->posted["sort"]          : "";
        $Search["spell"]     = false;
        $Search["phonetic"]  = false;
        $Search["facets"]    = array();
        $Search["counts"]    = array();
        $this->search = $Search;

        // Process display
        $Display = array();
        $Display["paia"]     = false;
        $Display["marc"]     = false;
        $Display["comb"]     = false;
        $Display["logs"]     = false;
        $Display["bib"]      = false;
        $Display["items"]    = false;
        $this->display = $Display;

        // STEP 1 - Attach Solr & search
        $this->readConvertFindex();
      }

      // $this->debug("ALXEXP", $this->works);
      // $this->debug("ALXEXP", $Config);

      $noteText = $this->getMarcText($ID, "bib_17");
      $Marc245 = $this->works["marc"][$ID]["marc"]["M245"][0] ?? [];

      $titleMain = isset($Marc245["a"][0])
        ? rtrim(trim($Marc245["a"][0]), " /:;")
        : "";

      $titlePart = isset($Marc245["b"][0])
        ? rtrim(trim($Marc245["b"][0]), " /:;")
        : "";

      $titleFull = $titleMain;

      if ($titlePart !== "") {
        $titleFull = $titleMain . ": " . $titlePart;
      }
      $date100 = $this->getMarcIntern($ID, "int_100", "year");
      $date101 = $this->getMarcIntern($ID, "int_101", "year");

      //$this->debug("EXP_INT101_PLACE", $this->getMarcIntern($ID, "int_101", "place"));
      //$this->debug("EXP_INT101_PUBLISHER", $this->getMarcIntern($ID, "int_101", "publisher"));
      //$this->debug("EXP_INT101_YEAR", $this->getMarcIntern($ID, "int_101", "year"));
      //$this->debug("EXP_INT100_YEAR", $this->getMarcIntern($ID, "int_100", "year"));

      $author = array_merge(
        $this->getMarcArray($ID, "bib_04", "1"),
        $this->getMarcArray($ID, "bib_05", "1")
      );

      $volume = $this->getMarcIntern($ID, "int_100", "volume");
      if (!$volume) {
        $volume = $this->getMarcIntern($ID, "int_100", "year");
      }

      $pages = $this->getMarcIntern($ID, "int_100", "pages");
      if (!$pages) {
        $pages = $this->getMarcIntern($ID, "int_100", "extent");
      }

      $callnumber = "";

      if (isset($this->works["marc"][$ID]["items"]) && is_array($this->works["marc"][$ID]["items"])) {
        foreach ($this->works["marc"][$ID]["items"] as $item) {
          if (isset($item["d"][0]) && trim($item["d"][0]) !== "") {
            $callnumber = trim($item["d"][0]);
            break;
          }
        }
      }

      $place = $this->getMarcIntern($ID, "int_101", "place");
      $publisher = $this->getMarcIntern($ID, "int_101", "publisher");
      // Fallback: Bei Artikeln aus M773$d parsen
      if (empty($place) || empty($publisher)) {
        $m773d = $this->works["marc"][$ID]["marc"]["M773"][0]["d"][0] ?? "";
        if ($m773d !== "") {
          // Format: "Ort : Verlag, Jahr"
          if (preg_match('/^([^:]+)\s*:\s*([^,]+)/', $m773d, $matches)) {
            if (empty($place)) {
              $place = trim($matches[1]);
            }
            if (empty($publisher)) {
              $publisher = trim($matches[2]);
            }
          }
        }
      }
      //$this->debug("FULL_MARC", $this->works["marc"][$ID]["marc"]);

      //Titel der ?bergeordneten Werks bei book section
      $parentTitle = $this->works["marc"][$ID]["marc"]["M773"][0]["t"][0] ?? "";


      $code = $this->works["marc"][$ID]["formatcode"] ?? "";

      if ($code === "journal") {
        $format = $this->getText("format." . $code);
      } else {
        $format = $code;
      }

      $rootId = $this->getMarcIntern($ID, "int_100", "rootid");
      $parentFormat = "";

      if (!empty($rootId) && trim($rootId) !== "") {

        if (!isset($this->works["add"][$rootId]) && !isset($this->works["marc"][$rootId])) {
          $this->search = array(
            "search"   => array(
              "query" => "id(" . $rootId . ")",
              "type"  => "lukida"
            ),
            "start"    => 0,
            "limit"    => 1,
            "sort"     => "",
            "spell"    => false,
            "phonetic" => false,
            "facets"   => array(),
            "url"      => $this->buildBasicURL("all"),
            "counts"   => array()
          );

          $this->readConvertFindex(true, "PM");
        }

        $parentFormat = $this->works["add"][$rootId]["formatcode"]
          ?? $this->works["marc"][$rootId]["formatcode"]
          ?? "";
      }

      $exportFormat = $format;

      if ($code === "article" && $parentFormat === "book") {
        $exportFormat = "inbook";
      }
      //$this->debug("ADD", $this->works["add"][$ID] ?? "EMPTY");
      //$this->debug("carrier",$this->works["add"][$ID]["online"] ?? false);
      // $this->debug("rootId", $rootId);
      // $this->debug("code", $code);
      // $this->debug("format", $format);
      // $this->debug("hasMarcParent", isset($this->works["marc"][$rootId]));
      // $this->debug("hasAddParent", isset($this->works["add"][$rootId]));
      // $this->debug("parentFormat", $parentFormat);
      // $this->debug("exportFormat", $exportFormat);
      $Data = array(
        "id"            => $ID,
        "format"        => $exportFormat,
        //"title"         => $this->getMarcText($ID, "bib_01"),
        "title"         => $titleFull,
        "collTitle"     => $parentTitle,
        "language"      => $this->getMarcText($ID, "bib_14"),
        "isbn"          => $this->getMarcText($ID, "bib_29"),
        "issn"          => $this->getMarcText($ID, "bib_28"),
        //"notes"       => $this->getMarcText($ID, "bib_17"),
        "notes"         => ($noteText !== "" ? array($noteText) : array()),
        "pages"         => $pages,
        "place"         => $place,
        "publisher"     => $publisher,
        "date"          => ($this->works["marc"][$ID]["formatcode"] === "article") ? ($date100 ?: $date101) : ($date101 ?: $date100),
        "edition"       => $this->getMarcText($ID, "bib_10"),
        "author"        => $author,
        "associates"    => $this->getMarcArray($ID, "bib_05", "1"),
        "zdbid"         => $this->getMarcIntern($ID, "int_100", "zdbid"),
        "doi"           => $this->getMarcText($ID, "bib_47"),
        "subtitle"      => $this->getMarcText($ID, "bib_42"),
        "series"        => $this->getMarcText($ID, "bib_08"),
        //"series"        => $this->getMarcIntern($ID, "int_100", "journal"),
        "part"          => $this->getMarcIntern($ID, "int_100", "part"),
        "issue"         => $this->getMarcIntern($ID, "int_100", "number"),
        "volume"        => $volume,
        "volltext"      => $this->getMarcIntern($ID, "int_103", "volltext"),
        "school"        => $this->getMarcIntern($ID, "int_102", "institution"),
        "type"          => $this->getMarcIntern($ID, "int_102", "type"),
        "physdesc"      => $this->getMarcIntern($ID, "int_102", "description"),
        "computerfile"  => $this->getMarcIntern($ID, "int_102", "computerfile"),
        "summary"       => $this->getMarcIntern($ID, "int_102", "summary"),
        "genre"         => $this->getMarcIntern($ID, "int_102", "genre"),
        "subject"       => $this->getMarcText($ID, "bib_33"),
        "callnumber"    => $callnumber,
        "online"        => $this->works["marc"][$ID]["online"] ?? false

      );

      //$this->debug("ALI_data", $Data);

      if (isset($Data["format"]) && $Data["format"] == "article") {
        // Shift titles on Articles
        $Tmp = $this->getMarcIntern($ID, "int_100", "journal");
        if ($Tmp) {
          $Data["articleTitle"] =  $Data["title"];
          $Data["title"]        =  $Tmp;
        }
      }

      // $this->debug("EXP_INT101_PLACE", $this->getMarcIntern($ID, "int_101", "place"));
      // $this->debug("EXP_INT101_PUBLISHER", $this->getMarcIntern($ID, "int_101", "publisher"));
      // $this->debug("EXP_DATA_PLACE", $Data["place"]);
      // $this->debug("EXP_DATA_PUBLISHER", $Data["publisher"]);

      $exportString .= $exp->exportfile($Data, $Format);
    }

    $this->results["export"] = $exportString;

    unset($this->results["data"]);

    // Return
    $this->finalize($this->results, __FUNCTION__);
  }

  public function resolve()
  {
    // Detemine appl & area
    $Appl   = (isset($this->posted["appl"])   && trim($this->posted["appl"]))   ? trim(strtolower($this->posted["appl"]))   : "";
    $Area   = (isset($this->posted["area"])   && trim($this->posted["area"]))   ? trim(strtolower($this->posted["area"]))   : "";
    $ID     = (isset($this->posted["id"])     && trim($this->posted["id"]))     ? trim($this->posted["id"])                 : "";
    $Type   = (isset($this->posted["type"])   && trim($this->posted["type"]))   ? trim($this->posted["type"])               : "";

    // if ( MODE != "production" ) $this->debug("ALI_POSTED", $this->posted);
    // Checks
    if (($Appl && $Appl != $this->appl)              || !$Appl) $this->finalize(array("status" => -1, "code" => "wrongappl"));
    if (($Area && !in_array($Area, $this->getAreas)) || !$Area) $this->finalize(array("status" => -1, "code" => "wrongarea"));
    if (!$ID)                                                   $this->finalize(array("status" => -1, "code" => "missingid"));

    // Use Resolver function to count fullviews
    $this->updStatDB("Media", "Fullview");

    if (in_array($Type, array("suggest"))) {
      $this->updateProjectDB("kisuggest", $ID);
      return $this->finalize();
    }

    $Check = $this->getResolversDB($ID);
    if (is_array($Check) && count($Check)) {
      // $this->debug("ALXREV", $Check);
      if (MODE != "production") $this->updStatDB("Cache", "Resolver.yes");
      // Der zentrale Cache (esx-107) speichert die Dienst-Zeilen sprachneutral
      // als Textcodes, damit er fuer API 4 UND 5 gleich funktioniert. Die
      // Uebersetzung in die angeforderte Sprache passiert erst hier, beim
      // Ausliefern (19.08.2026).
      $Check = $this->resolveServiceTexts($Check);
      $this->finalize($Check);
    }

    // Item configs
    /*
    $File = $this->prepareCachePath(str_split($ID, 4), "resolver", true);
    if ( file_exists($File) )
    {
       if ( MODE != "production" ) $this->updStatDB("Cache", "Resolver.yes");
       $this->results = json_decode(file_get_contents($File),true);
       $this->finalize($this->results);
       return;
    }
    */

    // Initialize
    $this->results = array();
    $this->display["ilns"]   = $this->configs["ilns"];

    $Rs = array();
    if (isset($this->configs["resolver"]["jop"])  && $this->configs["resolver"]["jop"]  == "y") $Rs[] = "jop";
    if (isset($this->configs["resolver"]["sfx"])  && $this->configs["resolver"]["sfx"]  == "y") $Rs[] = "sfx";
    if (isset($this->configs["resolver"]["ovid"]) && $this->configs["resolver"]["ovid"] == "y") $Rs[] = "ovid";
    if (isset($this->configs["resolver"]["redi"]) && $this->configs["resolver"]["redi"] == "y") {
      $Rs[] = "redi";
      // redi2 aufrufen wenn ezbbibid Komma-getrennt (ALI 29.05.2026)
      $ezbids = explode(",", $this->configs["resolver"]["ezbbibid"] ?? "");
      if (count($ezbids) > 1 && !empty(trim($ezbids[1]))) $Rs[] = "redi2";
    }
    if (!$this->readCache2Marc($ID)) {
      // Process search
      $Search  = array();
      $Search["search"]    = array("type" => "lukida", "query" => "id:" . $ID);
      $Search["start"]     = 0;
      $Search["limit"]     = 1;
      $Search["url"]       = $this->buildBasicURL("all");
      $this->search        = $Search;

      // Process display
      $Display = array();
      $Display["paia"]     = false;
      $Display["marc"]     = false;
      $Display["comb"]     = false;
      $Display["logs"]     = false;
      $Display["bib"]      = false;
      $Display["items"]    = false;
      $this->display = $Display;

      // STEP 1 - Attach Solr & search
      $this->readConvertFindex();
    }

    if (MODE == "development")     $Mode = "dev";
    elseif (MODE == "production")  $Mode = "prod";
    else                             $Mode = MODE;

    $Config = array(
      'base_url'       => $this->configs["domain"][$Mode],
      'title'          => $this->getClientName(),
      'ezbbibid'       => $this->configs["resolver"]["ezbbibid"],
      'isil'           => $this->configs["isils"][0],
      'openurl'        => $this->configs["resolver"]["openurl"],
      'jop'            => (isset($this->configs["resolver"]["jop"])  && $this->configs["resolver"]["jop"]  == "y") ? true : false,
      'redi'           => (isset($this->configs["resolver"]["redi"]) && $this->configs["resolver"]["redi"] == "y") ? true : false,
      'redibase'       => $this->configs["resolver"]["redibase"],
      // redi2 via Komma-Trennung in ezbbibid (ALI 29.05.2026)
      // redi2base = gleiche URL wie redibase, {ezbid} wird im Code ersetzt (ALI 02.06.2026)
      'redi2'          => (count(explode(",", $this->configs["resolver"]["ezbbibid"] ?? "")) > 1) ? true : false,
      'redi2base'      => $this->configs["resolver"]["redibase"] ?? "",
      'ezbbibid2'      => trim(explode(",", $this->configs["resolver"]["ezbbibid"] ?? "")[1] ?? ""),
      'sfx'            => (isset($this->configs["resolver"]["sfx"])  && $this->configs["resolver"]["sfx"]  == "y") ? true : false,
      'sfxbase'        => $this->configs["resolver"]["sfxbase"],
      'ovid'           => (isset($this->configs["resolver"]["ovid"])  && $this->configs["resolver"]["ovid"]  == "y") ? true : false,
      'ovidbase'       => $this->configs["resolver"]["ovidbase"],
      'refworks'       => true,
      'zotero'         => true
    );

    // $this->debug("autur", $this->works["marc"]);

    $Data = array(
      "id"            => $ID,
      "format"        => isset($this->works["marc"][$ID]["formatcode"]) ? $this->works["marc"][$ID]["formatcode"] : "",
      "title"         => $this->getMarcText($ID, "bib_01"),
      "language"      => $this->getMarcText($ID, "bib_14"),
      "isbn"          => $this->getMarcText($ID, "bib_29"),
      "issn"          => $this->getMarcText($ID, "bib_28"),
      "notes"         => $this->getMarcText($ID, "bib_17"),
      "pages"         => $this->getMarcIntern($ID, "int_100", "pages"),
      "place"         => $this->getMarcIntern($ID, "int_101", "place"),
      "publisher"     => $this->getMarcIntern($ID, "int_101", "publisher"),
      "date"          => $this->getMarcIntern($ID, "int_101", "year"),
      "edition"       => $this->getMarcText($ID, "bib_10"),
      "author"        => $this->getMarcArray($ID, "bib_04", "1"),
      "associates"    => $this->getMarcArray($ID, "bib_05", "1"),
      "zdbid"         => $this->getMarcIntern($ID, "int_100", "zdbid"),
      "doi"           => $this->getMarcText($ID, "bib_47"),
      "subtitle"      => $this->getMarcText($ID, "bib_42"),
      "series"        => $this->getMarcIntern($ID, "int_100", "journal"),
      "part"          => $this->getMarcIntern($ID, "int_100", "part"),
      "issue"         => $this->getMarcIntern($ID, "int_100", "number"),
      "volume"        => $this->getMarcIntern($ID, "int_100", "volume"),
      "volltext"      => $this->getMarcIntern($ID, "int_103", "volltext"),
      "school"        => $this->getMarcIntern($ID, "int_102", "institution"),
      "type"          => $this->getMarcIntern($ID, "int_102", "type"),
      "physdesc"      => $this->getMarcIntern($ID, "int_102", "description"),
      "computerfile"  => $this->getMarcIntern($ID, "int_102", "computerfile"),
      "summary"       => $this->getMarcIntern($ID, "int_102", "summary"),
      "genre"         => $this->getMarcIntern($ID, "int_102", "genre"),
      "subject"       => $this->getMarcText($ID, "bib_33")
    );
    //$this->debug("ALI_DATA", $Data);

    if (isset($Data["format"]) && $Data["format"] == "article") {
      // Shift titles on Articles
      $Tmp = $this->getMarcIntern($ID, "int_100", "journal");
      if ($Tmp) {
        $Data["articleTitle"] =  $Data["title"];
        $Data["title"]        =  $Tmp;
      }
    }

    // WeimarBU, HalleLIT: Only physical
    if (in_array($this->client, array("HalleLIT", "WeimarBU")) && isset($this->works["marc"][$ID]["online"]) && $this->works["marc"][$ID]["online"]) {
      $Rs = array();
    }
    // KielUB: Only foreign onlines
    elseif ($this->client == "KielUB" && ((isset($this->works["marc"][$ID]["online"]) && !$this->works["marc"][$ID]["online"])
      || (isset($this->works["marc"][$ID]["owner"])  &&  $this->works["marc"][$ID]["owner"]))) {
      $Rs = array();
    }
    // Erfurt: Only physical
    elseif ($this->client == "ErfurtUB" && isset($this->works["marc"][$ID]["online"]) && $this->works["marc"][$ID]["online"]) {
      $Rs = array();
    }

    // $this->debug("ALXConfig", $Config);
    // $this->debug("ALXRs", $Rs);
    // $this->debug("ALXData", $Data);

    $Resolver = array();
    // $this->debug("ALXRSLV", $Data);
    $LR = new resolver($this, $Config);
    if (count($Rs)) {
      // Set statistics
      foreach ($Rs as $R) {
        $this->updStatDB("Resolver", $R);
      }
      $Resolver  += $LR->linkresolver($Data);
      $Color = isset($this->CS->configs["item"]["resolver.color"]) && trim($this->CS->configs["item"]["resolver.color"]) ? $this->CS->configs["item"]["resolver.color"] : "green";
      foreach ($Resolver as $R => $URL) {

        $Resolver[$R] = array(
          "after"  => array(),
          "before" => array(),
          "text"   => array(
            "g" => array(array(
              "type" => "code",
              "code" => "resolver." . $R
            )),
            "e" => array(array(
              "type" => "code",
              "code" => "resolver." . $R
            ))
          ),
          "groupitems" => array(array(
            "short"    => array(
              "code"  => "itembutton.directopen",
              "icon"  => "mdi-link",
              "color" => $Color
            ),
            "comments" => array(
              "g" => array(
                array("type" => "code", "code" => "openinanewtab"),
                array("type" => "code", "code" => "items.fulltext")
              ),
              "e" => array(
                array("type" => "code", "code" => "openinanewtab"),
                array("type" => "code", "code" => "items.fulltext")
              )
            ),
            "light"    => "green",
            "action"   => array(
              "type" => "link",
              "link" => array(
                "url"    => $URL,
                "label"  => $this->getHost(trim($URL)),
                "target" => "_blank"
              )
            ),
            "view"     => array(),
            "users"    => array(),
            "desks"    => array(),
            "meta"     => array(
              "case"   => "LinkResolver " . ucfirst($R),
              "locid"  => "resolver." . $R,
              "window" => false
            )
          )),
          "label" => "resolver." . $R,
          "light" => "green"
        );
      }
    }

    $Resolver += array(
      "refworks" => $LR->exportlink($Data, 'refworks'),
      "zotero"   => $LR->exportlink($Data, 'zotero')
    );

    if ($Type == "media") {
      // Project KI Suggest
      if (isset($this->configs["project"]["kisuggest"])  && $this->configs["project"]["kisuggest"]  == "y") {
        // STEP 1 - Attach Solr & search
        $Solr  = new solr($this);
        //                 URL                                                                                           QUERY PROJECT
        $Solr->getProject("https://findex.gbv.de/semsimlabim/?semsim_model=inomic-iln30-title&semsim_id=" . $ID, "", "kisuggest");

        if (isset($this->works["project"]["kisuggest"]) && is_array($this->works["project"]["kisuggest"]) && count($this->works["project"]["kisuggest"])) {
          $this->results = array();
          $this->works["marc"]   = array();
          $this->paias   = array();
          $this->items   = array();
          $this->logs    = array();

          // $this->debug("ALX33434", $this->works["project"]["kisuggest"]);
          $Final = array();
          $Score = array();
          $Sort  = array();
          foreach ($this->works["project"]["kisuggest"] as $I => $S) {
            if (isset($S["id"]) && $S["id"] == $ID) continue;

            $Final[]         = $S["id"];
            $Sort[$S["id"]]  = $I;
            $Score[$S["id"]] = $S["score"];

            if (count($Final) >= 50) break;
          }
          // $this->debug("ALX33435", $Final);
          // $this->debug("ALX33436", $Sort);
          // $this->debug("ALX33437", $Score);

          $this->storeProjectDB("kisuggest", $ID, $Final, $Score);

          // Process search
          $Search  = array();
          $Search["search"]    = array("type" => "lukida", "query" => "id:(" . implode(" OR ", $Final) . ")");
          $Search["start"]     = 0;
          $Search["limit"]     = 50;
          $Search["url"]       = $this->buildBasicURL("all");
          $this->search        = $Search;

          // $this->debug("ALXData334", $Search["search"]);

          // Process display
          $Display = array();
          $Display["paia"]     = false;
          $Display["marc"]     = false;
          $Display["comb"]     = false;
          $Display["logs"]     = false;
          $Display["bib"]      = true;
          $Display["items"]    = true;
          $this->display = $Display;

          // Perform search
          $this->ReadArea("findex");

          $Sorted = array();

          if (isset($this->results["data"]) && is_array($this->results["data"]) && count($this->results["data"])) {
            foreach ($Final as $PPN_Ranked) {
              foreach ($this->results["data"] as $FullOne) {
                if (isset($FullOne["id"]) && $FullOne["id"] == $PPN_Ranked) $Sorted[] = $FullOne;
              }
              // $this->debug("ALX33434", $PR["id"] . " => " . $Sort[$PR["id"]]);
            }
            // $this->debug("ALX33", $Sorted);
            $this->results["data"] = $Sorted;
            // $this->debug("ALX33437", $this->results["data"]);
          }

          // Set status
          if (isset($this->results["hitspack"]) && $this->results["hitspack"] > 0) {
            $Resolver["kisuggest"] = $this->results;
          }
        }
      }
    }

    // Save cache
    if (MODE != "production") $this->updStatDB("Cache", "Resolver.no");
    // file_put_contents($File, json_encode($Resolver, true));
    // file_put_contents($File, print_r($Resolver, true));
    $this->setResolversDB($ID, $Resolver, array());

    // Erst NACH dem Schreiben in den Cache uebersetzen: der Cache bleibt
    // sprachneutral (Textcodes), die Antwort geht aufgeloest raus (19.08.2026).
    $Resolver = $this->resolveServiceTexts($Resolver);

    // Return
    $this->finalize($Resolver);
  }

  // Dienst-/Resolver-Zeilen tragen im Cache Textcodes (type=code); hier werden
  // sie in die angeforderte Sprache aufgeloest (type=text) und der Knopf bekommt
  // seine Beschriftung. Nur Gruppen mit "groupitems" werden angefasst -
  // Exportlinks (refworks/zotero) und kisuggest bleiben unberuehrt. So bleibt
  // der zentrale Cache auf esx-107 API-4/5-unabhaengig identisch (19.08.2026).
  private function resolveServiceTexts($Resolver)
  {
    if (!is_array($Resolver)) return $Resolver;
    foreach ($Resolver as $Key => $Grp) {
      if (!is_array($Grp) || !isset($Grp["groupitems"]) || !is_array($Grp["groupitems"])) continue;

      // Ueberschrift der Gruppe
      if (isset($Grp["text"]) && is_array($Grp["text"])) {
        foreach (array("g", "e") as $L) {
          if (!isset($Grp["text"][$L]) || !is_array($Grp["text"][$L])) continue;
          foreach ($Grp["text"][$L] as $I => $Row) {
            if (isset($Row["type"], $Row["code"]) && $Row["type"] == "code") {
              $Both = $this->getTextBothLang($Row["code"]);
              $Grp["text"][$L][$I] = array("type" => "text", "text" => ($L == "g" ? $Both["german"] : $Both["english"]));
            }
          }
        }
      }

      // Kommentare und Knopf-Beschriftung je Eintrag
      foreach ($Grp["groupitems"] as $GI => $Item) {
        if (isset($Item["comments"]) && is_array($Item["comments"])) {
          foreach (array("g", "e") as $L) {
            if (!isset($Item["comments"][$L]) || !is_array($Item["comments"][$L])) continue;
            foreach ($Item["comments"][$L] as $I => $C) {
              if (isset($C["type"], $C["code"]) && $C["type"] == "code") {
                $Both = $this->getTextBothLang($C["code"]);
                $Item["comments"][$L][$I] = array("type" => "text", "text" => ($L == "g" ? $Both["german"] : $Both["english"]));
              }
            }
          }
        }
        if (isset($Item["short"]["code"]) && !isset($Item["short"]["label"])) {
          $Lbl = $this->getText($Item["short"]["code"]);
          if ($Lbl !== "")  $Item["short"]["label"] = $Lbl;
        }
        $Grp["groupitems"][$GI] = $Item;
      }

      $Resolver[$Key] = $Grp;
    }
    return $Resolver;
  }

  public function login()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Detemine appl & area
    $Appl  = (isset($this->posted["appl"]) && trim($this->posted["appl"]))
      ? trim(strtolower($this->posted["appl"])) : "";

    // $this->debug("MPID", $this->posted);

    // Checks
    if (($Appl && $Appl != $this->appl) || !$Appl) $this->finalize(array("status" => -1, "code" => "wrongappl"));

    // Perform login
    $this->results = $this->checkUser();

    if (isset($this->results["stat"]) && $this->results["stat"] == "error") {
      // No access
      if (APPDEBUG["LOG"]) $this->debug("LOG", "User Login ERROR");
      if (APPDEBUG["LOG"]) $this->debug("LOG", $this->user);
      // $this->results = array("status" => -1,
      //                        "code"   => "loginfailed");
      $this->finalize($this->results);
    }

    // Access granted
    // $this->userclient = $this->results["client"];
    $this->user       = isset($this->results["user"]) ? $this->results["user"] : array();
    if (APPDEBUG["LOG"]) $this->debug("LOG", "User Login OK");
    if (APPDEBUG["LOG"]) $this->debug("LOG", $this->user);

    $this->results += array(
      "status" => 1,
      "code"   => "logingranted"
    );

    // Set statistics
    $this->updStatDB("Action", "Login");

    // GDPR retention tracking: record/refresh this patron's last login date
    // (client + hash only) - see updUserLoginDB()
    if (isset($this->results["login"]) && trim($this->results["login"])) {
      $this->updUserLoginDB($this->results["login"]);
    }


    // Return
    $this->finalize($this->results);
  }

  public function search()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Detemine appl & area
    $Appl  = (isset($this->posted["appl"])            && trim($this->posted["appl"]))                ? trim(strtolower($this->posted["appl"])) : "";
    $Area  = (isset($this->posted["area"])            && trim($this->posted["area"]))                ? trim(strtolower($this->posted["area"])) : "";

    // Checks
    if (($Appl && $Appl != $this->appl)                 || !$Appl) $this->finalize(array("status" => -1, "code" => "wrongappl"));
    if (($Area && !in_array($Area, $this->searchAreas)) || !$Area) $this->finalize(array("status" => -1, "code" => "wrongarea"));

    // Process search
    $Search  = array();
    $Search["search"]    = (isset($this->posted["search"])   && is_array($this->posted["search"]))                                    ? $this->posted["search"]        : array();
    $Search["start"]     = (isset($this->posted["start"])    && is_numeric($this->posted["start"]) && $this->posted["start"] >=    0) ? intval($this->posted["start"]) : 0;
    $Search["limit"]     = (isset($this->posted["limit"])    && is_numeric($this->posted["limit"]) && $this->posted["limit"] <= 1000) ? intval($this->posted["limit"]) : 25;
    $Level               = (isset($this->posted["level"])    && trim($this->posted["level"]))                                         ? trim($this->posted["level"])   : "";
    $Search["url"]       = $this->buildBasicURL($Level);
    $Search["level"]     = (isset($this->posted["level"])    && trim($this->posted["level"]))         ? trim($this->posted["level"])  : "";
    $Search["sort"]      = (isset($this->posted["sort"])     && in_array($this->posted["sort"], array("yeardesc", "yearasc", "acqdesc")))         ? $this->posted["sort"]          : "";
    $Search["spell"]     = (isset($this->posted["spell"])    && is_bool($this->posted["spell"])    && $this->posted["spell"])         ? true                           : false;
    $Search["phonetic"]  = (isset($this->posted["phonetic"]) && is_bool($this->posted["phonetic"]) && $this->posted["phonetic"])      ? true                           : false;
    $Search["facets"]    = (isset($this->posted["facets"])   && is_array($this->posted["facets"]))                                    ? $this->posted["facets"]        : array();
    $Search["counts"]    = (isset($this->posted["counts"])   && is_array($this->posted["counts"]))                                    ? $this->posted["counts"]        : array();
    $this->search        = $Search;

    // Set statistics
    if (
      isset($Search["search"]["query"])
      && !in_array(substr($Search["search"]["query"], 0, 3), array("id:", "id("))
    ) {
      $this->updStatDB("Level", $Level);
      $SearchMode = (in_array(substr($Search["search"]["query"], 0, 8), array("ppnlink:", "ppnlink("))) ? "Related" : "Search";
      $this->updStatDB($SearchMode, "Query");

      if (isset($Search["facets"]["yearmin"]) || isset($Search["facets"]["yearmax"])) $this->updStatDB($SearchMode, "Range");
      foreach ($Search["facets"] as $Key => $Val) {
        if (in_array($Key, array("yearmin", "yearmax")))  continue;
        $this->updStatDB($SearchMode, ucfirst($Key));
      }
    }

    // Process display
    //
    // "mode" ersetzt die frueheren Einzelschalter display.paia/comb/logs.
    // API 5 wird ausschliesslich von User2026 aufgerufen; Admin2026 und
    // Hosting2026 brauchen diese Bibliothekars-Daten nicht, deshalb gibt es
    // die alten Schluessel im Payload nicht mehr (2026-08-11).
    //
    // Aufbau: display.mode = { "lib": true }. Ein Objekt und kein einzelner
    // Wert, damit weitere Ansichten daneben passen, ohne den Vertrag erneut
    // zu aendern.
    //
    // Innerhalb der API bleiben paia/comb/logs die Schalter, an denen die
    // Verarbeitung haengt - sie werden hier aus dem Modus abgeleitet.
    $Mode = (isset($this->posted["display"]["mode"]) && is_array($this->posted["display"]["mode"]))
              ? $this->posted["display"]["mode"] : array();
    $Lib  = (isset($Mode["lib"]) && $Mode["lib"]) ? true : false;

    $Display = array();
    $Display["paia"]     = $Lib;
    $Display["comb"]     = $Lib;
    $Display["logs"]     = $Lib;
    $Display["mode"]     = $Mode;
    // MARC21 gehoert zur Bibliotheksansicht - der gleichnamige Reiter lebt davon.
    $Display["marc"]     = $Lib || (isset($this->posted["display"]["marc"])  && is_bool($this->posted["display"]["marc"])  && $this->posted["display"]["marc"])  ? true : false;
    $Display["bib"]      = (isset($this->posted["display"]["bib"])   && is_bool($this->posted["display"]["bib"])   && $this->posted["display"]["bib"])   ? true : false;
    $Display["items"]    = (isset($this->posted["display"]["items"]) && is_bool($this->posted["display"]["items"]) && $this->posted["display"]["items"]) ? true : false;
    $this->display = $Display;

    // Perform search
    $this->ReadArea($Area);

    if (isset($Search["counts"]["links"]) && $Search["counts"]["links"]) {
      // $this->debug("ALX29", $this->results);
      $Links = $this->readDB("adminstructures", $this->client, 0, 100, ["type" => "facet1"], ["sort"]);
      // $this->debug("ALEX", $Links);

      foreach ($Links as &$L) {
        $this->results["counts"]["links"][] = [
          "code"   => "norm",
          "label"  => trim($L["shortcutl1"]) ? $this->getText($L["shortcutl1"]) : "",
          "label2" => trim($L["shortcutl2"]) ? $this->getText($L["shortcutl2"]) : "",
          "label3" => trim($L["shortcutl3"]) ? $this->getText($L["shortcutl3"]) : "",
          "value"  => trim($L["action"])     ? $L["action"]                     : ""
        ];
      }
    }

    // Set status
    if (isset($this->results["hitspack"]) && $this->results["hitspack"] > 0) {
      if ($Display["bib"]) {
        $this->updStatDB("Media", "preview", $this->results["hitspack"]);
      }
      $this->results += array(
        "status" => 1,
        "code"   => "dataloaded"
      );
    } else {
      $this->results += array(
        "status" => 0,
        "code"   => "nodata"
      );

      // Kein Treffer: eine Stufe weiter anbieten (17.08.2026).
      //
      // Bisher konnte nur das Altmodul diesen Fall und schlug dort fest "all"
      // vor. Zwischenstufen fielen unter den Tisch - bei FlensburgZHB fuehrt
      // der Weg von "euf" (zwei Filterkriterien) sinnvollerweise ueber "start"
      // (eines) und erst dann nach "all" (keines).
      //
      // Geliefert wird die naechste Stufe samt Textschluessel. Welche Adresse
      // die Oberflaeche daraus baut, entscheidet sie selbst - die REIHENFOLGE
      // der Level muss sie dafuer nicht kennen, und genau darum geht es.
      $Weiter = $this->getWiderLevel($Level);
      if ($Weiter) {
        $this->results["levelup"] = array(
          "level"    => $Weiter,
          // Fertige Beschriftung mitliefern, damit der Client keinen Textcode
          // mehr aufloesen muss. "textcode" bleibt fuer aeltere Clients
          // erhalten. (20.08.2026)
          "label"    => $this->getText("level." . $Weiter),
          "textcode" => "level." . $Weiter,
          "filters"  => $this->countLevelFilters($this->levels[$Weiter]),
          "from"     => array(
            "level"   => $Level,
            "filters" => $this->countLevelFilters($this->levels[$Level]),
          ),
        );
      }

      // Kein Treffer: zusaetzlich einen Dialog anbieten (09.09.2026).
      //
      // Gewuenscht von HannoverGWLB: wer nichts findet, soll gleich einen
      // Anschaffungsvorschlag stellen koennen. Der Dialog dafuer ist derselbe
      // wie ueberall (dialogs/dialogfields) - neu ist nur, WOHER er kommt:
      // nicht aus cases/casesmedia, denn dort haengt jeder Fall an einem
      // Datensatz, und hier gibt es gerade keinen.
      //
      // Der Schalter traegt den DIALOGCODE, nicht ja/nein (wie item.bibtipurl,
      // 03.09.2026): so haengt jeder Client den Dialog an, den er dafuer
      // vorgesehen hat, ohne dass dieser Zweig ihn kennen muss. Leer = aus.
      //
      // Ausgegeben wird nur, was der Client zum Oeffnen braucht - der Code und
      // eine fertige Beschriftung. Die Felder holt er ohnehin schon aus der
      // Konfiguration (configs.dialogs), dieselbe Quelle wie bei den
      // Exemplar-Dialogen. Der Suchraum-Vorschlag daneben bleibt unberuehrt;
      // beide koennen nebeneinander stehen.
      $NoHitsDialog = isset($this->configs["other"]["nohitsdialog"])
                      ? trim($this->configs["other"]["nohitsdialog"]) : "";
      if ($NoHitsDialog !== "" && isset($this->configs["dialogs"][$NoHitsDialog]["fields"])) {
        // Beschriftung des Knopfes: der eigene Textbaustein geht vor, sonst
        // die Ueberschrift des Dialogs selbst (Feld vom Typ "head") - so
        // braucht ein Client keinen zweiten Text zu pflegen, der dasselbe sagt.
        $Label = $this->getText("nohitsdialog." . $NoHitsDialog);
        if ($Label === "") {
          foreach ($this->configs["dialogs"][$NoHitsDialog]["fields"] as $Feld) {
            if (isset($Feld["fieldtype"]) && $Feld["fieldtype"] == "head"
              && isset($Feld["label"]) && trim($Feld["label"]) !== "") {
              $Label = $Feld["label"];
              break;
            }
          }
        }

        $this->results["nohitsdialog"] = array(
          "code"  => $NoHitsDialog,
          "label" => $Label
        );
      }
    }

    // Return
    $this->finalize($this->results);
  }

  public function print()
  {
    $Appl   = (isset($this->posted["appl"])   && trim($this->posted["appl"]))   ? trim(strtolower($this->posted["appl"]))   : "";
    $Area   = (isset($this->posted["area"])   && trim($this->posted["area"]))   ? trim(strtolower($this->posted["area"]))   : "";
    $IDs    = (isset($this->posted["ids"])    && is_array($this->posted["ids"])) ? $this->posted["ids"]                : [];

    // Checks
    if (($Appl && $Appl != $this->appl)              || !$Appl) $this->finalize(array("status" => -1, "code" => "wrongappl"));
    if (($Area && !in_array($Area, $this->getAreas)) || !$Area) $this->finalize(array("status" => -1, "code" => "wrongarea"));
    if (!count($IDs))                                           $this->finalize(array("status" => -1, "code" => "missingids"));

    // Use Resolver function to count fullviews
    $this->updStatDB("Media", "PrintSave");

    // Process search
    $Search  = array();
    $Search["search"]    = array("type" => "lukida", "query" => "id:(" . implode(" OR ", $IDs) . ")");
    $Search["start"]     = 0;
    $Search["limit"]     = 1000;
    $Search["url"]       = $this->buildBasicURL("all");
    $this->search        = $Search;

    // Process display
    $Display = array();
    $Display["paia"]     = false;
    $Display["marc"]     = false;
    $Display["comb"]     = false;
    $Display["logs"]     = false;
    $Display["bib"]      = true;
    $Display["items"]    = true;
    $this->display = $Display;

    // STEP 1 - Attach Solr & search
    $this->ReadArea($Area);

    $Mode          = (isset($this->posted["mode"]) && trim($this->posted["mode"])) ? trim($this->posted["mode"]) : "full";
    $Add           = new addons($this);
    $this->results = $Add->printpdf($IDs, $Mode);
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************

}
