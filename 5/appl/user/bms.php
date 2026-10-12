<?php

/*
  Gemeinsamer Vertrag aller BMS-Treiber (14.09.2026)
  ===================================================

  user.php spricht ein Bibliotheksmanagementsystem NUR ueber die zehn Methoden
  unten an und weiss nicht, welches System dahintersteht (setBMS() waehlt den
  Treiber nach adminbms.type). Deshalb muss jeder Treiber exakt dieselben
  Formate liefern. Massgeblich ist das, was paia seit jeher liefert - daran
  haengen user.php, item.php und die Oberflaechen.

  Konfiguration
  -------------
  - adminbms (je BMS): type, isil, iln, apitest/apiprod, options (JSON).
    Treiberspezifisches steht in options, z.B. bei folio tenant, systemuser,
    systempass. Hier gilt: $this->option("...").
  - adminconfigs, Kategorie "bms" ($this->CS->configs["bms"]): fachliche
    Schalter fuer alle Treiber (answer*, maxrenewals, changepassword, ...).

  Kennungen
  ---------
  - $Login: was checkUser() als "login" geliefert hat (paia: Patron-ID,
    folio: Nutzer-UUID).
  - $Token: was checkUser() als "user.token" geliefert hat. JEDE Methode mit
    $Token muss ihn gegen das BMS pruefen - user.php prueft nur, ob er da ist.
  - $ID eines Exemplars/Kontoeintrags: undurchsichtig, der Treiber versteht ihn.

  Rueckgabeformate
  ----------------
  checkUser($User, $Pass)
    Erfolg: { stat:"success", mess:[..], login,
              user:{ token, login, name, email, address, expires, status,
                     type, notes:[{label}], blocked:bool, accountstatus },
              items:[ { item, about, label, storage, storageid, starttime,
                        endtime, queue, renewals, reminder, canrenew,
                        cancancel, status:1..5 } ]      (nur mit display.items)
              fees:{ amount:"0.00 EUR", code, items:[{feetype, about,
                     amount, date}] } }                 (nur mit display.fees)
    Fehler: { stat:"error", mess:[..], more:[..] }

  ReadUser($Login, $Token)
    Erfolg: { username, name, email, address, status, type, expires, note }
    Token ungueltig oder Fehler: array()

  getPPN($PPN, $Format) / getEPN($EPN, $Format)
    [ <ExpID> => { epn, count, id, about, bandlist, label?, storage?,
                   storageid?, storagelink?, chronology?, department?,
                   departmentid?,
                   <dienst>:bool, <dienst>items:[{ limitation:[], expected,
                   queue, title, delay, href, action }] } ]
    <dienst> = loan, presentation, interloan, openaccess, remote.
    Fehler: array()

  Request($Login, $ID, $Token, $Cond, $Type)  ($Type: "order" | "reserve")
  Renew($Login, $ID, $Token)
    Erfolg: { status:0, stat:"success", mess:[..], more:[..] }
    Fehler: { status:-1, stat:"error",   mess:[..], more:[..] }

  Cancel($Login, $ID, $Token)
  ChangeMail($Login, $Token, $New)
  ResetPW($Login)
    Erfolg: { status:0, ... }
    Fehler: { status:-2, code?, more }

  ChangePW($Login, $Token, $Old, $New)
    Erfolg: Ergebnis von checkUser() mit dem neuen Passwort, dazu status:1
            (die Oberflaeche uebernimmt daraus den Kontostand)
    Fehler: { status:-2, more }
*/

abstract class bms
{
  protected $CS;
  protected $bmsid;
  protected $config;
  protected $lang;
  protected $link;

  public function __construct($BMSID, $CS)
  {
    $this->bmsid  = $BMSID;
    $this->CS     = $CS;
    $this->lang   = $CS->lang;
    $this->config = ( isset($CS->bms[$BMSID]) && is_array($CS->bms[$BMSID]) ) ? $CS->bms[$BMSID] : array();

    $Api          = ( MODE == "production" ) ? "apiprod" : "apitest";
    $this->link   = ( isset($this->config[$Api]) && trim($this->config[$Api]) ) ? rtrim(trim($this->config[$Api]), "/") : "";
  }

  // *******************************************************************
  // ************* V E R T R A G ***************************************
  // *******************************************************************
  abstract public function checkUser($User, $Pass);
  abstract public function ReadUser($Login, $Token);
  abstract public function getPPN($PPN, $Format);
  abstract public function getEPN($EPN, $Format);
  abstract public function Request($Login, $ID, $Token, $Cond, $Type);
  abstract public function Renew($Login, $ID, $Token);
  abstract public function Cancel($Login, $ID, $Token);
  abstract public function ChangeMail($Login, $Token, $New);
  abstract public function ChangePW($Login, $Token, $Old, $New);
  abstract public function ResetPW($Login);

  // *******************************************************************
  // ************* H T T P *********************************************
  // *******************************************************************

  // Eine HTTP-Schicht fuer alle Treiber. Rueckgabe:
  //   ok      curl hat eine Antwort bekommen (unabhaengig vom HTTP-Status)
  //   http    HTTP-Status
  //   headers Antwort-Kopfzeilen als Text
  //   body    Antwortrumpf
  //   json    Rumpf als Array, falls JSON - sonst null
  protected function http($Method, $Url, $Body = null, $Headers = array())
  {
    static $ch = null;
    if ( $ch === null )  $ch = curl_init();
    curl_reset($ch);

    if ( APPDEBUG["BMI"] ) $this->CS->debug("BMI", array("method"  => $Method,
                                                         "url"     => $Url,
                                                         "headers" => $this->maskHeaders($Headers),
                                                         "body"    => $this->mask(is_string($Body) ? (json_decode($Body, true) ?? $Body) : $Body)));

    $Opts = array(
      CURLOPT_URL            => $Url,
      CURLOPT_CUSTOMREQUEST  => $Method,
      CURLOPT_HTTPHEADER     => $Headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 60,
      CURLOPT_ENCODING       => "",
      // Bisheriges Verhalten beider Treiber: keine Zertifikatspruefung (die
      // Testsysteme haben teils keine gueltigen Zertifikate). Je BMS
      // einschaltbar ueber options.sslverify = "y".
      CURLOPT_SSL_VERIFYPEER => $this->option("sslverify") == "y",
      CURLOPT_SSL_VERIFYHOST => $this->option("sslverify") == "y" ? 2 : 0,
    );
    if ( $Body !== null )  $Opts[CURLOPT_POSTFIELDS] = is_string($Body) ? $Body : json_encode($Body);
    curl_setopt_array($ch, $Opts);

    $Raw = curl_exec($ch);
    if ( $Raw === false )
    {
      $Result = array("ok" => false, "http" => 0, "headers" => "", "body" => "", "json" => null, "error" => curl_error($ch));
      if ( APPDEBUG["BMO"] ) $this->CS->debug("BMO", $Result);
      return $Result;
    }

    $HeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $Body       = substr($Raw, $HeaderSize);
    $Json       = json_decode($Body, true);
    $Result     = array("ok"      => true,
                        "http"    => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                        "headers" => substr($Raw, 0, $HeaderSize),
                        "body"    => $Body,
                        "json"    => is_array($Json) ? $Json : null,
                        "error"   => "");

    if ( APPDEBUG["BMO"] ) $this->CS->debug("BMO", array("http" => $Result["http"], "body" => $this->mask($Result["json"] ?? $Body)));
    return $Result;
  }

  // Zugangsdaten und Tokens NIE ins Debug-Log (14.09.2026). Vorher schrieb
  // BMI bei jeder Anmeldung das Passwort im Klartext mit.
  protected function mask($Data)
  {
    if ( !is_array($Data) )  return $Data;
    foreach ( $Data as $Key => $Val )
    {
      if ( is_string($Key) && preg_match('/pass|token|secret/i', $Key) )  $Data[$Key] = "***";
      elseif ( is_array($Val) )                                           $Data[$Key] = $this->mask($Val);
    }
    return $Data;
  }

  protected function maskHeaders($Headers)
  {
    return array_map(fn($H) => preg_replace('/^(Authorization|Cookie):.*$/i', '$1: ***', $H), $Headers);
  }

  // *******************************************************************
  // ************* H E L F E R *****************************************
  // *******************************************************************
  protected function option($Key, $Default = "")
  {
    return ( isset($this->config["options"][$Key]) && $this->config["options"][$Key] !== "" ) ? $this->config["options"][$Key] : $Default;
  }

  protected function isConfigYes($Setting)
  {
    return isset($this->CS->configs["bms"][$Setting]) && $this->CS->configs["bms"][$Setting] == "y";
  }

  protected function actionError($MessCode, $More = array())
  {
    return array("status" => -1,
                 "stat"   => "error",
                 "mess"   => array($this->CS->getText($MessCode)),
                 "more"   => array_values(array_filter((array) $More, fn($M) => $M !== "" && $M !== null)));
  }

  protected function actionSuccess($MessCode, $More = array())
  {
    return array("status" => 0,
                 "stat"   => "success",
                 "mess"   => array($this->CS->getText($MessCode)),
                 "more"   => (array) $More);
  }

  // Zusatzzeilen einer Erfolgsmeldung nach den Schaltern configs.bms.answer*.
  // $Kind: "" (Bestellung), "reserve" oder "renew".
  protected function answerLines($Kind, $EndTime = "", $Storage = "")
  {
    $Lines = array();
    if ( $EndTime && $this->isConfigYes("answer" . $Kind . "endtime") )
    {
      $Lines[] = $this->CS->getText("action.requestreturn") . ": " . $this->CS->format2German($EndTime);
    }
    if ( $Storage && $Kind != "renew" && $this->isConfigYes("answer" . $Kind . "storage") )
    {
      $Lines[] = $this->CS->getText("action.requestpickup") . ": " . $Storage;
    }
    if ( $EndTime && $this->isConfigYes("answer" . $Kind . "daysremaining") )
    {
      $Lines[] = $this->CS->getText("action.daysremaining") . ": " . $this->CS->daysLeft($EndTime);
    }
    return $Lines;
  }
}

?>
