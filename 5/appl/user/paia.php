<?php

// PAIA/DAIA-Treiber. Vertrag und Rueckgabeformate: siehe bms.php.
class paia extends bms
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  public function __construct($BMSID, $CS)
  {
    parent::__construct($BMSID, $CS);

    // PAIA-Endpunkte haengen an der ISIL: <api>/<isil>/daia, .../core, ...
    if ( $this->link )  $this->link .= "/" . ( isset($this->config["isil"]) ? $this->config["isil"] : "" );

    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  // PAIA-Aufruf. Wie bisher: keine oder leere Antwort -> {error: Text},
  // sonst das JSON als Array (kein JSON -> leeres Array).
  private function call($Method, $File, $Data = null, $Token = null)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Headers   = array('Content-type: application/json; charset=utf-8');
    if ( $this->lang == "e" )  $Headers[] = 'Accept-Language: en';
    if ( $Token !== null )     $Headers[] = 'Authorization: Bearer ' . $Token;

    $Response = $this->http($Method, $this->link . $File, $Data === null ? null : json_encode($Data), $Headers);
    if ( !$Response["ok"] || $Response["body"] === "" )
    {
      return array("error" => $this->CS->getText("account.servernotresponding"));
    }
    return $Response["json"] ?? array();
  }

  // Fehlertext hinter dem ersten ":" aus error_description
  private function errorDescription($Response)
  {
    if ( !isset($Response["error_description"]) )  return "";
    $Tmp = explode(":", $Response["error_description"]);
    array_shift($Tmp);
    return implode(":", $Tmp);
  }

  private function GetLimitation($Limitation)
  {
    $Lim = array();
    foreach ( $Limitation as $Limit )
    {
      if ( isset($Limit["id"]) )       $Lim[] = explode("#", $Limit["id"])[1] ?? $Limit["id"];
      if ( isset($Limit["content"]) )  $Lim[] = $Limit["content"];
    }
    return $Lim;
  }

  private function GetBMSAction($URI)
  {
    if ( strpos($URI,"&action=") === false && strpos($URI,"?action=") === false )  return "-";

    parse_str((string) parse_url(trim($URI), PHP_URL_QUERY), $Tmp);
    return $Tmp["action"] ?? "-";
  }

  private function ParseBMSServices($BMSItems, $State)
  {
    $Services = array();
    $Items    = array();
    foreach ( $BMSItems as $One )
    {
      $SName = ( isset($One["service"]) ) ? $One["service"] : "";
      if ( $SName == "" ) continue;
      if ( !isset($Services[$SName]) )
      {
        $Items[$SName]         = $State;
        $Items[$SName."items"] = array();
        $Services[$SName]      = 0;
      }
      else
      {
        $Services[$SName]     += 1;
      }

      $Items[$SName."items"][$Services[$SName]] = array(
        "limitation" => ( isset($One["limitation"]) ) ? $this->GetLimitation($One["limitation"])                     : array(),
        "expected"   => ( isset($One["expected"]) )   ? date("d.m.Y", strtotime(strtolower(trim($One["expected"])))) : "-",
        "queue"      => ( isset($One["queue"]) )      ? trim($One["queue"]) : "-",
        "title"      => ( isset($One["title"]) )      ? trim($One["title"]) : "-",
        "delay"      => ( isset($One["delay"]) )      ? trim($One["delay"]) : "-",
        "href"       => ( isset($One["href"]) )       ? trim($One["href"])  : "-",
        "action"     => ( isset($One["href"]) )       ? $this->GetBMSAction($One["href"]) : "-",
      );
    }
    return $Items;
  }

  // DAIA-Antwort in Exemplare zerlegen - gemeinsam fuer getPPN() und getEPN(),
  // die bis 14.09.2026 je eine vollstaendige Kopie dieses Codes hatten.
  private function readDAIA($Query, $Format, $ShowRaw = false)
  {
    $Contents = $this->call("GET", "/daia?format=json&id=" . $Query);

    // Rohantwort fuer die Anzeige "paia" (nur Titelabfrage, wie bisher)
    if ( $ShowRaw && !empty($this->CS->display["paia"]) )
    {
      $this->CS->results["paia"][$this->CS->params["shift"] ? $this->CS->params["childid"] : substr($Query, 4)][] = $Contents;
    }

    $Items  = array();
    $ICount = array();
    $Count  = 0;
    if ( !isset($Contents["document"]) )  return $Items;

    foreach ( $Contents["document"] as $Dok )
    {
      if ( !isset($Dok["item"]) )  continue;

      foreach ( $Dok["item"] as $Exp )
      {
        // DAIA 1 & 2 - Check services
        $ExpID    = ( isset($Exp["temporary-hack-do-not-use"]) ) ? strtoupper($Exp["temporary-hack-do-not-use"])
                                                                 : ( explode(":", $Exp["id"] ?? "")[3] ?? ($Exp["id"] ?? "") );
        $OrgExpID = $ExpID;
        $ICount["EPN_" . $ExpID] = ( !isset($ICount["EPN_" . $ExpID]) ) ? 1 : $ICount["EPN_" . $ExpID] + 1;
        if ( array_key_exists($ExpID, $Items) )
        {
          // Important: Bandlist
          $Count ++;
          $ExpID .= "_" . $Count;
        }
        else
        {
          $Count = 0;
        }
        $Items[$ExpID]["epn"]   = $OrgExpID;
        $Items[$ExpID]["count"] = $Count;

        // ParseServices
        $Items[$ExpID] += ( isset($Exp["available"]) )   ? $this->ParseBMSServices($Exp["available"],   true )  : array();
        $Items[$ExpID] += ( isset($Exp["unavailable"]) ) ? $this->ParseBMSServices($Exp["unavailable"], false ) : array();

        if ( isset($Exp["id"]) && $Exp["id"] != "" )                                                                   $Items[$ExpID]["id"]           = trim($Exp["id"]);
        if ( isset($Exp["storage"]["content"]) && $Exp["storage"]["content"] != "" )                                   $Items[$ExpID]["storage"]      = trim($Exp["storage"]["content"]);
        if ( isset($Exp["storage"]["id"]) && $Exp["storage"]["id"] != "" && stripos($Exp["storage"]["id"], "@") !== false )  $Items[$ExpID]["storageid"]    = explode("@", $Exp["storage"]["id"])[1];
        if ( isset($Exp["storage"]["href"]) && $Exp["storage"]["href"] != "" )                                         $Items[$ExpID]["storagelink"]  = $Exp["storage"]["href"];
        if ( isset($Exp["chronology"]["about"]) && $Exp["chronology"]["about"] != "" )                                 $Items[$ExpID]["chronology"]   = trim($Exp["chronology"]["about"]);
        if ( isset($Exp["department"]["content"]) && $Exp["department"]["content"] != "" )                             $Items[$ExpID]["department"]   = trim($Exp["department"]["content"]);
        if ( isset($Exp["department"]["id"]) && $Exp["department"]["id"] != "" && stripos($Exp["department"]["id"], "@") !== false )  $Items[$ExpID]["departmentid"] = explode("@", $Exp["department"]["id"])[1];
        if ( isset($Exp["label"]) && $Exp["label"] != "" )                                                             $Items[$ExpID]["label"]        = trim($Exp["label"]);

        // Label About ergänzen (Immer wegen Sortierung)
        $Items[$ExpID]["about"] = ( isset($Exp["about"]) && $Exp["about"] != "" ) ? trim($Exp["about"]) : "";
      }
    }

    // Add bandlist switch
    foreach ( $Items as $ExpID => $One )
    {
      if ( in_array($Format, $this->CS->formatsBandlist) )
      {
        $Items[$ExpID]["bandlist"] = ( $One["about"] != "-" && isset($One["id"]) && stripos($One["id"], ":bar:") !== false );
      }
      else
      {
        $Items[$ExpID]["bandlist"] = ( isset($One["epn"]) && isset($ICount["EPN_" . $One["epn"]]) && $ICount["EPN_" . $One["epn"]] > 1 );
      }
    }

    // Sort records by about (volume...)
    uasort($Items, function ($a, $b) { return $a['about'] <=> $b['about']; });

    return $Items;
  }

  private function CalcUserStatus($Data)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Reminder = false;
    $Collect  = false;
    if ( !isset($Data["user"]["notes"]) ) $Data["user"]["notes"] = array();

    // Check for collectable items
    if ( isset($Data["items"]) )
    {
      foreach ( $Data["items"] as $Item )
      {
        if ( isset($Item["reminder"]) && $Item["reminder"] >= "3" )  $Reminder = true;
        if ( isset($Item["status"])   && $Item["status"]   == "4" )  $Collect  = true;
      }
    }

    // Special libs
    $Limited = ( $this->CS->client == "MagdeburgMZB" );
    $Status  = isset($Data["user"]["status"]) ? (string) $Data["user"]["status"] : null;

    // Status -> Sperrtext (in dieser Reihenfolge; der letzte Treffer gewinnt)
    $Locks = array();
    if ( !$Limited && $Status === "0" && $Reminder )   $Locks[] = "account.lockedreminder";
    if ( $Status === "1" && !$Reminder )               $Locks[] = "account.lockedlocked";
    if ( $Status === "1" && $Reminder )                $Locks[] = "account.lockedreminder";
    if ( $Status === "2" )                             $Locks[] = "account.lockedexpired";
    if ( !$Limited && $Status === "3" && !$Reminder )  $Locks[] = "account.lockedfees";
    if ( !$Limited && $Status === "3" && $Reminder )   $Locks[] = "account.lockedreminderfees";
    if ( !$Limited && $Status === "4" )                $Locks[] = "account.lockedexpiredfees";
    if ( !$Limited && $Status === "PC" )               $Locks[] = "account.lockedpasswordchange";

    $Data["user"]["accountstatus"] = "";
    foreach ( $Locks as $Lock )
    {
      $Data["user"]["accountstatus"] = $this->CS->getText($Lock);
      $Data["user"]["notes"][]       = array("label" => $Data["user"]["accountstatus"]);
    }
    if ( !$Limited && $Status === "0" && $Reminder )  $Data["user"]["status"] = "5";

    $Data["user"]["blocked"] = count($Locks) > 0;
    if ( $Collect )  $Data["user"]["notes"][] = array("label" => $this->CS->getText("account.collectableremark"));

    $Data += array("stat" => "success",
                   "mess" => array($this->CS->getText("loginsuccessful")));

    return $Data;
  }

  private function loginError($More)
  {
    return array("stat" => "error",
                 "mess" => array($this->CS->getText("loginerror")),
                 "more" => array($More));
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function getPPN($PPN, $Format)
  {
    return $this->readDAIA("ppn:" . $PPN, $Format, true);
  }

  public function getEPN($EPN, $Format)
  {
    return $this->readDAIA("epn:" . $EPN, $Format);
  }

  public function checkUser($User, $Pass)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Check user account
    $Response = $this->call("POST", "/auth/login", array("username"   => $User,
                                                         "password"   => $Pass,
                                                         "grant_type" => "password",
                                                         "scope"      => "read_patron read_fees read_items write_items change_password read_availability reset_password update_patron_email"));

    if ( isset($Response["error"]) )  return $this->loginError($Response["error"]);
    if ( isset($Response["code"]) )   return $this->loginError($this->CS->getText($Response["code"]));

    // Process response
    $Token  = ( isset($Response["access_token"]) ) ? $Response["access_token"] : "";
    $Login  = ( isset($Response["patron"]) )       ? $Response["patron"]       : $User;
    $Return = array("user" => array("token" => $Token, "login" => $Login), "login" => $Login);

    if ( isset($Response["scope"]) && $Response["scope"] == "change_password" )
    {
      $Return["user"]["status"] = "PC";
      $Return["items"]          = array();
      $Return["fees"]           = array("amount" => "0.00 EUR");
      return $this->CalcUserStatus($Return);
    }

    if ( isset($this->CS->display["user"]) && $this->CS->display["user"] )
    {
      // Get basic user information
      $Response = $this->call("GET", "/core/" . $Login, null, $Token);
      if ( isset($Response["error"]) )  return $this->loginError($Response["error"]);

      // Convert Note to Array
      if ( isset($Response["note"]) )
      {
        $Response["notes"][] = array("label" => $Response["note"]);
        unset($Response["note"]);
      }
      $Return["user"] += $Response;
    }

    if ( isset($this->CS->display["items"]) && $this->CS->display["items"] )
    {
      // Get items
      $Response = $this->call("GET", "/core/" . $Login . "/items", null, $Token);
      if ( isset($Response["error"]) )  return $this->loginError($Response["error"]);

      if ( isset($Response["doc"]) && is_array($Response["doc"]) )  $Return += array("items" => $Response["doc"]);
    }

    if ( isset($this->CS->display["fees"]) && $this->CS->display["fees"] )
    {
      // Get fees
      $Response = $this->call("GET", "/core/" . $Login . "/fees", null, $Token);
      if ( !isset($Response["amount"]) )  $Response["amount"] = "0.00 EUR";
      $Response["code"] = "account.userfees";
      if ( isset($Response["fee"]) )
      {
        $Response["items"] = $Response["fee"];
        unset($Response["fee"]);
      }
      $Return += array("fees" => $Response);
    }

    // Process & return user status
    return $this->CalcUserStatus($Return);
  }

  public function ReadUser($Login, $Token)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Der PAIA-Server prueft den Token
    $Response = $this->call("GET", "/core/" . $Login, null, $Token);
    if ( isset($Response["error"]) )  return array();

    return array(
      "username" => isset($Response["patron"])  ? $Response["patron"]  : $Login,
      "name"     => isset($Response["name"])    ? $Response["name"]    : "",
      "email"    => isset($Response["email"])   ? $Response["email"]   : "",
      "address"  => isset($Response["address"]) ? $Response["address"] : "",
      "status"   => isset($Response["status"])  ? $Response["status"]  : "",
      "type"     => isset($Response["type"][0]) ? $Response["type"][0] : "",
      "expires"  => isset($Response["expires"]) ? $Response["expires"] : "",
      "note"     => isset($Response["note"])    ? $Response["note"]    : "",
    );
  }

  public function Request($Login, $ID, $Token, $Cond, $Type)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Doc = array("item" => $ID);
    if ( isset($Cond["desk"]) && $Cond["desk"] != "" )
    {
      $Doc["confirm"] = array("http://purl.org/ontology/paia#StorageCondition" => array($Cond["desk"]));
    }
    elseif ( isset($Cond["feeusertypes"]) )
    {
      $Doc["confirm"] = array("http://purl.org/ontology/paia#FeeCondition" => array("http://purl.org/ontology/dso#Reservation"));
    }

    $Response = $this->call("POST", "/core/" . $Login . "/request", array("doc" => array($Doc)), $Token);
    if ( isset($Response["error"]) )          return $this->actionError("action.requesterror", $Response["error"]);
    if ( isset($Response["doc"][0]["error"]) ) return $this->actionError("action.requesterror", $Response["doc"][0]["error"]);

    $Kind = ( $Type == "order" ) ? "" : "reserve";
    return $this->actionSuccess($Type == "order" ? "action.ordersuccessful" : "action.reservesuccessful",
                                $this->answerLines($Kind, $Response["doc"][0]["endtime"] ?? "", $Response["doc"][0]["storage"] ?? ""));
  }

  public function Renew($Login, $ID, $Token)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Response = $this->call("POST", "/core/" . $Login . "/renew", array("doc" => array(array("item" => $ID))), $Token);
    if ( isset($Response["error"]) )          return $this->actionError("action.requesterror", $Response["error"]);
    if ( isset($Response["doc"][0]["error"]) ) return $this->actionError("action.requesterror", $Response["doc"][0]["error"]);

    return $this->actionSuccess("action.renewsuccessful", $this->answerLines("renew", $Response["doc"][0]["endtime"] ?? ""));
  }

  public function Cancel($Login, $ID, $Token)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Response = $this->call("POST", "/core/" . $Login . "/cancel", array("doc" => array(array("item" => $ID))), $Token);
    if ( isset($Response["error"]) )           return array("status" => -2, "error" => $Response["error"]);
    if ( isset($Response["doc"][0]["error"]) ) return array("status" => -2, "more"  => $Response["doc"][0]["error"]);
    if ( !isset($Response["doc"][0]) )         return array("status" => -2, "more"  => "");

    // "status" im PAIA-Dokument ist der Exemplarstatus, nicht das Ergebnis
    return array("status" => 0) + $Response["doc"][0];
  }

  public function ChangeMail($Login, $Token, $New)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Response = $this->call("PATCH", "/core/" . $Login, array("email" => $New), $Token);
    if ( isset($Response["error"]) )  return array("status" => -2, "more" => $this->errorDescription($Response));

    return $Response + array("status" => 0);
  }

  public function ChangePW($Login, $Token, $Old, $New)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Response = $this->call("POST", "/auth/change", array("patron"       => $Login,
                                                          "username"     => $Login,
                                                          "old_password" => $Old,
                                                          "new_password" => $New), $Token);
    if ( isset($Response["error"]) )  return array("status" => -2, "more" => $this->errorDescription($Response));

    $Tmp = $this->checkUser($Login, $New);
    $Tmp["status"] = 1;
    return $Tmp;
  }

  public function ResetPW($Login)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Response = $this->call("POST", "/auth/login", array("username"   => $Login,
                                                         "password"   => "",
                                                         "grant_type" => "password",
                                                         "scope"      => "reset_password"));
    // Vorher array() - der Vertrag verlangt einen Fehlerstatus (14.09.2026)
    if ( isset($Response["error"]) )  return array("status" => -2, "more" => $this->errorDescription($Response));

    $Token = ( isset($Response["access_token"]) ) ? $Response["access_token"] : "";
    if ( isset($Response["scope"]) && $Response["scope"] == "reset_password" )
    {
      $Response = $this->call("POST", "/auth/reset", array("patron"   => $Login,
                                                          "username" => $Login), $Token);
      if ( isset($Response["error"]) )  return array("status" => -2, "more" => $this->errorDescription($Response));
    }
    return $Response + array("status" => 0);
  }
}

?>
