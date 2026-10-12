<?php

class system extends systemdb
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  protected $dbpw;
  public    $batchconfigs;
  public    $batchtextexcludes;
  public    $batchtexts;
  public    $clientconfigs;
  public    $clienttexts;
  public    $configs;
  public    $texts;

  public function __construct($f3)
  {
    parent::__construct($f3);
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    if ( ACTION != "batch" )
    {
      // Update client
      $this->updateClient();
    }
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************
  private function updateClient()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Write Session
    $this->updSessionDB($this->checkPostedStr("tokens", "session"));

    if ( $this->checkPostedStr("tokens", "device") )  $this->updDeviceDB();
    if ( $this->checkPostedArr("platform") )          $this->updPlatformDB();

    if ( !$this->user ) return array();

    // LIGHT: Server-seitige Favoriten/Suchen/Einstellungen deaktiviert.
    // Diese Daten liegen ausschließlich im Browser (localStorage der UI).
    return array();
  }

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************

  protected function getConfigs($ClientConfigs)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);    

    // Part 1 Server
    $File = $this->prepareCachePath(array("settings"), "configs");
    if ( file_exists($File) )
    {
       if ( MODE != "production" ) $this->updStatDB("Cache", "Configs.yes");
       $this->configs = json_decode(file_get_contents($File),true);
    }
    else
    {
      // Read texts
      if ( MODE != "production" ) $this->updStatDB("Cache", "Configs.no");
      $this->configs  = $this->getAdminConfigsDB();
      file_put_contents($File, json_encode($this->configs,true));
      // file_put_contents($File.".txt", print_r($this->configs, true));
    }

    // Part 2 Client
    $File = $this->prepareCachePath(array("settings"), "Clientconfigs");
    if ( file_exists($File) )
    {
       if ( MODE != "production" ) $this->updStatDB("Cache", "ClientConfigs.yes");
       $this->clientconfigs = json_decode(file_get_contents($File),true);
    }
    else
    {
      // Read texts
      if ( MODE != "production" ) $this->updStatDB("Cache", "ClientConfigs.no");
      $this->clientconfigs  = $this->getAdminConfigsDB($ClientConfigs, true);
      $this->clientconfigs += $this->getApplConfigs();
      file_put_contents($File, json_encode($this->clientconfigs,true));
      // file_put_contents($File.".txt", print_r($this->clientconfigs, true));
    }
  }

  // Texte, die der Client als Markdown rendert, brauchen zwei Ersetzungen,
  // die sich in der Textverwaltung nicht direkt eingeben lassen: "§" steht
  // fuer die Ueberschriftsraute "#", "{newline}" fuer einen Zeilenumbruch.
  // Beides wird HIER aufgeloest, damit der Client fertiges Markdown bekommt
  // und die Ersetzung nicht in jedem Modul erneut nachgebaut werden muss
  // (2026-08-10; im alten Modul steckte sie in Info.vue).
  protected function markdownTexts($Prefixes)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    if ( !is_array($this->clienttexts) )  return;

    foreach ( $this->clienttexts as $Key => $Text )
    {
      $Match = false;
      foreach ( $Prefixes as $Prefix )  if ( strpos($Key, $Prefix) === 0 )  $Match = true;
      if ( !$Match || !is_array($Text) )  continue;

      foreach ( array("german", "english") as $Lang )
      {
        if ( !isset($Text[$Lang]) )  continue;
        $this->clienttexts[$Key][$Lang] = str_replace(array("§", "{newline}"),
                                                      array("#", "\n"),
                                                      $Text[$Lang]);
      }
    }
  }

  protected function getTexts($ClientTexts)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);    

    // Part 0: Texte, die die ANWENDUNG beisteuert - einmal fuer alle Clients
    // (03.09.2026, am 12.09.2026 nach appl verlagert)
    //
    // Was das ist, weiss nur die Anwendung: Lukida legt hier seine
    // Bibliothekstexte ab ("lang.*", "bibline.*"), die fuer jede Bibliothek
    // dieselben sind. Base kennt weder die Tabelle noch die Schluessel - es
    // kuemmert sich um Zwischenspeicher und Reihenfolge, nicht um den Inhalt.
    // Gibt es den Haken nicht, faellt der ganze Teil weg.
    //
    // Der Cache liegt bewusst NICHT im Client-Verzeichnis, sondern unter
    // caches/<Tag>/_system/settings/appltexts.inc - eine Datei fuer alle
    // Clients statt einer gleichen Kopie je Client.
    //
    // Gelesen wird VOR den uebrigen Texten, gemischt wird darunter: ein
    // gleichnamiger Text aus "texts" oder "clienttexts" gewinnt also
    // weiterhin. Nach dem Mischen sieht getText() nur noch EINE Sammlung -
    // woher ein Text stammt, spielt von hier an keine Rolle mehr.
    $ApplTexts = array();
    if ( method_exists($this, "getApplTextsDB") )
    {
      $File = $this->prepareSharedCachePath("settings", "appltexts");
      if ( file_exists($File) )
      {
        if ( MODE != "production" ) $this->updStatDB("Cache", "ApplTexts.yes");
        $ApplTexts = (array) json_decode(file_get_contents($File), true);
      }
      else
      {
        if ( MODE != "production" ) $this->updStatDB("Cache", "ApplTexts.no");
        $ApplTexts = (array) $this->getApplTextsDB();
        // Nur schreiben, wenn wirklich etwas da ist: eine leere Datei wuerde
        // den Fehlstand bis Mitternacht festschreiben.
        if ( count($ApplTexts) )  file_put_contents($File, json_encode($ApplTexts, true));
      }
    }

    // Part 1 Server
    $File = $this->prepareCachePath(array("settings"), "texts");
    if ( file_exists($File) )
    {
       if ( MODE != "production" ) $this->updStatDB("Cache", "Texts.yes");
       $this->texts = json_decode(file_get_contents($File), true);
    }
    else
    {
      // Read texts
      if ( MODE != "production" ) $this->updStatDB("Cache", "Texts.no");
      $this->texts  = $this->getAdminTextsDB();
      file_put_contents($File, json_encode($this->texts, true));
      // file_put_contents($File.".txt", print_r($this->texts, true));
    }

    // Anwendungstexte UNTER die Servertexte legen (s. Part 0).
    if ( count($ApplTexts) )
      $this->texts = array_merge($ApplTexts, is_array($this->texts) ? $this->texts : array());

    // Part 2 Client
    $File = $this->prepareCachePath(array("settings"), "Clienttexts");
    if ( file_exists($File) )
    {
       if ( MODE != "production" ) $this->updStatDB("Cache", "ClientTexts.yes");
       $this->clienttexts = json_decode(file_get_contents($File),true);
    }
    else
    {
      // Read texts
      if ( MODE != "production" ) $this->updStatDB("Cache", "ClientTexts.no");
      $this->clienttexts  = $this->getAdminTextsDB($ClientTexts, true);
      file_put_contents($File, json_encode($this->clienttexts,true));
      // file_put_contents($File.".txt", print_r($this->clienttexts, true));
    }
  }

  protected static function prepareSearch($String)
  {
    if ( APPDEBUG["FKT"] ) self::debug("FKT",__METHOD__);

    $String = preg_replace('!\s+!', '*', trim($String));
    $String = $String . "*";
    return $String;
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function action()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks
    $Type   = $this->checkPostedStr("type");
    $Action = $this->checkPostedArr($Type);
    if ( !$Type || !count($Action) ) $this->finalize(array("status" => -1, "code" => "missingvalue"));

    $Type   = strtolower($Type);
    // if ( !in_array($Type,array("dbprocedure","osbatch","system")) && $Action == "" ) $this->finalize(array("status" => -1, "code" => "missingvalue"));
    if ( !in_array($Type,array("mail")) ) $this->finalize(array("status" => -1, "code" => "missingvalue"));

    // Ensure required classes

    // Set statistics
    $this->updStatDB("Action", $Type);

    // Produce results
    switch ($Type)
    {
      case "mail":
      {
        $Mail    = $this->checkPostedArr("mail");
        $Results = $this->sendMail($Mail);
        break;
      }
      default:
      {
        $Results = array();
      }


      /*
      case "dbprocedure":
      {
        $Results = $this->database->PerformDBAction($Type, $Action, $this->filter);
        break;
      }
      case "osbatch":
      {
        $Results = $this->PerformOSAction($Type, $Action, $this->filter);
        break;
      }
      case "system":
      {
        $Results = $this->PerformSystemAction($Type, $Action, $this->filter);
        break;
      }
      default:
      {
        // Attach application
        $Appl = $this->f3->get("application.type");

        // Perform application action
        $Results = $this->appl->DoAction($Type, $this->posted);
      }
      */
    }

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function config()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks are done within com class
    $this->updStatDB("Session", "-");

    // Herkunft zaehlen (2026-08-14). Die Oberflaechen werten den
    // Adressparameter "source" aus (z.B. ...&source=Kiwi), geben ihn EINMAL
    // beim Aufruf von config mit und entfernen ihn danach aus der Adresse.
    // Gespeichert wird ausschliesslich hier, als Zaehlerzeile in "stats" -
    // Kategorie "Source", Wert die Herkunft selbst. Kein Bezug zur Person, die
    // Zeile zaehlt nur, wie oft von dort gekommen wurde.
    //
    // Gefiltert wird streng: die Spalte fasst 50 Zeichen, und der Wert kommt
    // ungeprueft aus der Adresszeile. Erlaubt sind Buchstaben, Ziffern,
    // Leerzeichen sowie - _ . und +.
    $Source = trim($this->checkPostedStr("source"));
    if ( $Source )
    {
      $Source = preg_replace('/[^\p{L}\p{N} ._+-]/u', "", $Source);
      $Source = trim(mb_substr($Source, 0, 50));
      if ( $Source )  $this->updStatDB("Source", $Source);
    }

    // Produce results
    //
    // Texte: bisher wurden NUR die clientspezifischen Texte ausgeliefert (eine
    // Handvoll), alle uebrigen steckten fest gebacken in der .env des Clients
    // (VITE_LANG_G/E, ~1.100 Schluessel, 117 KB). Damit war jede Textaenderung
    // erst nach einem Neu-Build sichtbar. Jetzt liefert der config-Aufruf die
    // vollstaendige Textsammlung mit - dieselbe Struktur {code:{german,english}},
    // die der Client ohnehin verarbeitet. Clienttexte werden zuletzt gemischt,
    // damit sie einen gleichnamigen allgemeinen Text weiterhin ueberschreiben.
    // So wirken Textaenderungen nach dem Loeschen des Caches sofort, und die
    // .env traegt nur noch die wenigen hart verdrahteten Texte. (20.08.2026)
    // Dieselben Ausschluesse wie der Batch beim Bauen der .env
    // (appl.php::batchtextexcludes - Normdatentexte person./place./organisation.
    // u.a.). Ohne sie waeren es ~9.800 Texte / 638 KB statt der rund 1.200, die
    // der Client wirklich braucht; die Normdatentexte loest die API selbst auf.
    $Texts = array();
    // Rueckfall, falls die Liste im config-Kontext nicht gefuellt ist: dieselben
    // Praefixe wie appl.php. Ohne den Rueckfall blieben u.a. lang.* (7.958) und
    // classlocal.* (415) in der Antwort - 638 KB statt rund 90 KB.
    $Excludes = ( is_array($this->batchtextexcludes) && count($this->batchtextexcludes) )
                ? $this->batchtextexcludes
                : array("classlocal.%", "event.%", "family.%", "lang.%", "organisation.%", "person.%", "place.%", "role.%");
    foreach ( (is_array($this->texts) ? $this->texts : array()) as $Key => $Val )
    {
      $Skip = false;
      foreach ( $Excludes as $Ex )
      {
        $Prefix = rtrim($Ex, "%");
        if ( $Prefix !== "" && strpos($Key, $Prefix) === 0 ) { $Skip = true; break; }
      }
      if ( !$Skip )  $Texts[$Key] = $Val;
    }
    if ( is_array($this->clienttexts) )  $Texts = array_merge($Texts, $this->clienttexts);

    $Configs = is_array($this->clientconfigs) ? $this->clientconfigs : array();

    // Auswahllisten MIT fertigen Beschriftungen (20.08.2026). Das Modul liefert
    // sie ueber getClientOptions(); der Client muss dadurch keine Textcodes mehr
    // selbst zusammensetzen ("area."/"sort."/"format." + Wert) und die Listen
    // sind zugleich dynamisch - also ohne Neu-Build aenderbar.
    if ( method_exists($this, "getClientOptions") )
    {
      $Options = $this->getClientOptions();
      if ( is_array($Options) && count($Options) )  $Configs["options"] = $Options;
    }

    // Wartungsfenster der BMS (04.09.2026). BEWUSST hier und nicht in den
    // zwischengespeicherten clientconfigs: die Angabe haengt an der Uhrzeit,
    // und eine Ankuendigung, die erst am naechsten Tag wirkt, waere keine.
    // getMaintenance() liest deshalb frisch aus der Datenbank.
    if ( method_exists($this, "getMaintenance") )
    {
      $Configs["maintenance"] = $this->getMaintenance();
    }

    // Allgemeine Meldungen (04.09.2026) - nur in der neuen Oberflaeche, siehe
    // user.php::getMessages(). Auch sie stehen bewusst nicht im Cache.
    if ( method_exists($this, "getMessages") )
    {
      $Configs["messages"] = $this->getMessages();
    }

    $Results  = array("configs" => $Configs,
                      "texts"   => $Texts);

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function dash()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    $UserClients = $this->checkPostedArr("userclients");

    // Checks are done within com class

    // Produce results
    $Results = $this->getDashboardDB($UserClients);

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function getMultipleTexts($ShortcutArray)
  {
    $Lang = ($this->lang == "g") ? "german" : "english";

    $Texts = array();
    foreach( $ShortcutArray as $Shortcut )
    {
      $Texts[$Shortcut] = ( isset($this->texts[$Shortcut][$Lang]) ) ? $this->texts[$Shortcut][$Lang] : "";
    }
    return $Texts;
  }

  public function getText($Shortcut)
  {
    $Lang = ($this->lang == "g") ? "german" : "english";
    return ( isset($this->texts[$Shortcut][$Lang]) ) ? $this->texts[$Shortcut][$Lang] : "";
  }

  public function getTextBothLang($Shortcut)
  {
    return ( isset($this->texts[$Shortcut]) ) ? $this->texts[$Shortcut] : array("german" => "", "english" => "");
  }

  // ─── Bremse gegen das Durchprobieren von Passwoertern (29.08.2026) ───────
  //
  // WARUM DATEIEN UND KEINE TABELLE. Ein Zaehler in der Datenbank braeuchte ein
  // Schema, das auf jeden Server ausgerollt werden muss - und die Bremse soll
  // gerade dann greifen, wenn jemand OHNE Anmeldung anklopft. Marker-Dateien
  // koennen das ohne jede Vorbedingung; com.php haelt es bei der Mailbremse
  // schon genauso (logs/mailthrottle/).
  //
  // GEZAEHLT WIRD JE BENUTZERNAME, nicht je Adresse: eine Adresse wechselt der
  // Angreifer, den Namen des Ziels nicht. Der Name steht dabei NICHT im
  // Dateinamen - er wird gehasht, damit ein Blick ins Verzeichnis nicht die
  // Kontonamen verraet.
  //
  // WAS SIE NICHT TUT: ein Konto dauerhaft sperren. Das waere ein Weg, jemanden
  // gezielt auszusperren - man muesste nur zehnmal falsch raten. Nach dem
  // Fenster laeuft alles von selbst weiter.
  private function loginBremsePfad($User)
  {
    $Dir = PROJECTDIR . "logs" . DIRECTORY_SEPARATOR . "loginthrottle" . DIRECTORY_SEPARATOR;
    if ( !file_exists($Dir) )  @mkdir($Dir, 0777, true);
    return $Dir . hash("sha256", mb_strtolower(trim($User))) . ".json";
  }

  // Rueckgabe: Sekunden zu warten, 0 fuer freie Fahrt, -1 fuer abweisen.
  //
  // Die Staffelung ist bewusst weich: die ersten vier Fehlversuche kosten
  // nichts - so viel vertippt sich jeder einmal. Danach verdoppelt sich die
  // Wartezeit bis acht Sekunden, ab zehn Versuchen ist im Fenster Schluss.
  // Aus hunderten Versuchen je Minute werden damit weniger als zehn.
  private function loginBremse($User)
  {
    $FENSTER = 900;   // 15 Minuten
    $FREI    = 4;     // so viele Fehlversuche kosten nichts
    $SCHLUSS = 10;

    $Datei = $this->loginBremsePfad($User);
    if ( !is_file($Datei) )  return 0;

    $Inhalt = file_get_contents($Datei);
    $Stand  = ($Inhalt === false) ? null : json_decode((string) $Inhalt, true);
    if ( !is_array($Stand) || !isset($Stand["n"], $Stand["t"]) )  return 0;

    // Fenster abgelaufen: der Zaehler ist wertlos.
    if ( (time() - (int) $Stand["t"]) > $FENSTER ) { unlink($Datei); return 0; }

    $N = (int) $Stand["n"];
    if ( $N >= $SCHLUSS )  return -1;
    if ( $N <= $FREI )     return 0;

    return min(pow(2, $N - $FREI), 8);
  }

  private function loginBremseZaehlen($User)
  {
    $FENSTER = 900;
    $Datei = $this->loginBremsePfad($User);

    $N = 0;
    if ( is_file($Datei) )
    {
      $Inhalt = file_get_contents($Datei);
      $Stand  = ($Inhalt === false) ? null : json_decode((string) $Inhalt, true);
      if ( is_array($Stand) && isset($Stand["n"], $Stand["t"])
        && (time() - (int) $Stand["t"]) <= $FENSTER )  $N = (int) $Stand["n"];
    }

    file_put_contents($Datei, json_encode(array("n" => $N + 1, "t" => time())), LOCK_EX);
    chmod($Datei, 0666);
  }

  // is_file() VOR dem Loeschen, und das @ genuegt nicht.
  //
  // unlink() auf eine nicht vorhandene Datei loest eine Notice aus, und dieses
  // Projekt behandelt Notices als Fehler - die erfolgreiche Anmeldung endete
  // dadurch mit HTTP 500, obwohl Name und Passwort stimmten. Der Fehlerpfad
  // lief sauber; genau die Umkehrung, die man beim Testen leicht uebersieht
  // (29.08.2026).
  private function loginBremseFrei($User)
  {
    $Datei = $this->loginBremsePfad($User);
    if ( is_file($Datei) )  unlink($Datei);
  }

  public function login()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks
    $User = $this->checkPostedStr("userid");
    $Pass = $this->checkPostedStr("userpw");
    $Tokn = $this->checkPostedStr("tokens", "user");
    if ( ( !$User || !$Pass ) && !$Tokn )  $this->finalize(array("status" => -1, "code" => "missingvalue"));

    // BREMSE GEGEN DURCHPROBIEREN (29.08.2026).
    //
    // Bis hierher gab es keine: wer die Adresse kannte, konnte Passwoerter so
    // schnell durchprobieren, wie der Server antwortet - hunderte je Minute.
    // Die Hashes sind zwar bcrypt und damit von sich aus langsam, aber das
    // bremst den ANGREIFER nur so weit wie den Server auch.
    //
    // Nur bei Anmeldung mit NAMEN und PASSWORT. Die Anmeldung ueber ein
    // vorhandenes Token ($Tokn) ist nichts zum Erraten - sie ist signiert.
    if ( $User && $Pass )
    {
      $Wartezeit = $this->loginBremse($User);
      if ( $Wartezeit < 0 )
        $this->finalize(array("status" => -1, "code" => "toomanyattempts"));
      if ( $Wartezeit > 0 )
        sleep($Wartezeit);
    }

    // Produce results
    $Results = array();
    if ( $Tokn )
    {
      $UserToken = $this->decodeToken($Tokn, "user");
      if ( is_array($UserToken) && count($UserToken) )
      {
        if ( isset($UserToken["cl"]) && $UserToken["cl"] == $this->client )
        {
          $Results["account"]  = $this->getUserDB($UserToken["un"]);
        }
      }
    }
    else
    {
      $Tmp = $this->checkGetUserDB($User, $Pass);
      if ( is_array($Tmp) && count($Tmp) )  $Results["account"] = $Tmp;
    }

    if ( isset($Results["account"]["username"]) && $Results["account"]["username"] )
    {
      $this->user        = $Results["account"]["username"];
      $this->avatar      = isset($Results["account"]["avatar"]) ? $Results["account"]["avatar"] : "";
      $Results["texts"]  = $this->getMultipleTexts(array("loginsuccessful", "loginerror", "logoutgranted"));
      if ( APPDEBUG["LOG"] ) $this->debug("LOG", "User Access OK (for user: " . $this->user . ")");
      $this->logDB("User Access OK");
      if ( $User )  $this->loginBremseFrei($User);

      // Set statistics on public functions
      $this->updStatDB("Action", "login");
    }
    else
    {
      if ( APPDEBUG["LOG"] ) $this->debug("LOG", "User Access ERROR (for user: " . $User . ")");
      $this->logDB("User Access ERROR");
      if ( $User && $Pass )  $this->loginBremseZaehlen($User);
    }

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function read()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Ensure required classes

    // Detemine appl & area
    $AdminClient = $this->checkPostedStr("adminclient");
    $Area        = $this->checkPostedStr("area");
    $Client      = $this->checkPostedStr("client");
    $Start       = $this->checkPostedInt("start");
    $Limit       = $this->checkPostedInt("limit");
    $Sorts       = $this->checkPostedArr("sorts");
    $Filters     = $this->checkPostedArr("filters");
    $Texts       = $this->checkPostedArr("texts");
    $Group       = $this->checkPostedStr("group");
    $Env         = $this->checkPostedStr("env");
    $Special     = $this->checkPostedStr("special");
    // Freitextsuche ueber die Pflegemasken (14.09.2026). Ein Client fuehrt
    // mehrere tausend Textbausteine; eine Liste, die bei 500 abschneidet, ist
    // ohne Suche nicht zu gebrauchen. Gesucht wird in der Datenbank, nicht in
    // der schon geladenen Seite - sonst faende man nur, was ohnehin dasteht.
    $Search      = $this->checkPostedStr("search");

    // Combine special with its value (e.g. special:"year" + year:"2026" => "year:2026")
    //
    // "monthhours"/"yearhours" holen ihren Wert aus demselben Feld wie
    // "month"/"year" - sie unterscheiden sich nur in der Form der Auswertung
    // (24 Stundenspalten ueber den Zeitraum statt einer Reihe je Tag, siehe
    // systemdb.php). Ohne diesen Eintrag kaeme "special" ohne Wert an, und die
    // Auswertung fiele stillschweigend auf eine ungefilterte Abfrage zurueck
    // (2026-08-22).
    $SpecialFelder = array("day"        => "day",
                           "month"      => "month",
                           "year"       => "year",
                           "monthhours" => "month",
                           "yearhours"  => "year");
    if ( isset($SpecialFelder[$Special]) )
    {
      $SpecialValue = $this->checkPostedStr($SpecialFelder[$Special]);
      if ( $SpecialValue )  $Special .= ":" . $SpecialValue;
    }

    // Im Benutzermodul steht der Client bereits fest (er kommt aus dem
    // API-Token) - store() leitet ihn deshalb selbst ab. read() verlangte ihn
    // dagegen im Payload, und der Client schickt ihn nicht mit: jedes Lesen
    // von favors/historys/profiles/settings endete in "missingvalue".
    // Jetzt verhalten sich beide gleich (14.08.2026).
    if ( MODULE == "user" && !$Client )  $Client = $this->client;

    // Checks
    if ( !$Area || ( !$Client && MODULE != "hosting" ) )  $this->finalize(array("status" => -1, "code" => "missingvalue"));
    if ( !$AdminClient && MODULE == "admin" )             $this->finalize(array("status" => -1, "code" => "missingvalue"));

    if ( MODULE == "admin" ) $Client = $AdminClient;

    if ( !$Start )  $Start = 0;
    if ( !$Limit )  $Limit = 25;



    // Set statistics
    $this->updStatDB("Read", $Area);

    // Perform search
    $Results = array();
    $Results["data"]  = $this->readDB($Area, $Client, $Start, $Limit, $Filters, $Sorts, $Group, $Env, $Special, $Search);
    if ( is_array($Texts) && count($Texts) ) $Results["texts"] = $this->getAdminTextsDB($Texts, true, $AdminClient);

    // No data
    if ( !count($Results["data"]) ) $this->finalize(array("status" => -1, "code" => "nodata"));

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function readoptions()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Ensure required classes

    // Detemine appl & area
    $Client  = $this->checkPostedStr("client");
    $Area    = $this->checkPostedStr("area");
    $Values  = $this->checkPostedArr("values");
    $Labels  = $this->checkPostedArr("labels");
    $Filters = $this->checkPostedArr("filters");
    $Sorts   = $this->checkPostedArr("sorts");

    // Checks
    if ( !$Area || ( !$Client && MODULE != "hosting" ) )  $this->finalize(array("status" => -1, "code" => "missingvalue"));

    // Set statistics
    $this->updStatDB("ReadOption", $Area);

    // Perform search
    $Rows    = $this->readDB($Area, $Client, 0, 1000, $Filters, $Sorts);

    // No data
    if ( !count($Rows) ) $this->finalize(array("status" => -1, "code" => "nodata"));

    $Results         = array();
    $Results["data"] = array();
    foreach ($Rows as $Row)
    {
      // $this->debug("ALX", $Row);
      // $this->debug("ALX", array_flip($Labels));
      // $this->debug("ALX", array_intersect_key($Row, array_flip($Labels)));
      // $this->debug("ALX", array_flip($Values));
      // $this->debug("ALX", array_intersect_key($Row, array_flip($Values)));

      $Results["data"][] = array(
                                 "value" => implode(", ", array_intersect_key($Row, array_flip($Values))),
                                 "label" => implode(", ", array_intersect_key($Row, array_flip($Labels)))
                                );
    }

    // $this->debug("ALX", $Results);

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function regis()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks
    $Regis = $this->checkPostedStr("regis");
    if ( !$Regis )  $this->finalize(array("status" => -1, "code" => "regiserror"));

    // Produce results
    $Results = $this->getRegisDB($Regis);

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function remove()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks
    $Area = $this->checkPostedStr("area");
    $ID   = $this->checkPostedStr("data", "id");
    if ( !$Area || !$ID )  $this->finalize(array("status" => -1, "code" => "removeerror"));
    if ( MODULE == "hosting" ) $Client = "";
    if ( MODULE == "user" )    $Client = $this->client;
    if ( MODULE == "admin" )
    {
      $Client = $this->checkPostedStr("data", "client");
      if ( !$Client ) $this->finalize(array("status" => -1, "code" => "removeerror"));
    }

    // Set statistics on public functions
    $this->updStatDB("System", "remove");

    // Produce results
    $Results  = $this->removeRowDB($Area, $ID, $Client);

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function store()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks
    $Area = $this->checkPostedStr("area");
    $Data = $this->checkPostedArr("data");
    $Text = $this->checkPostedArr("texts");
    if ( !$Area || ( !count($Data) && !in_array($Area, array("favors", "historys", "profiles", "settings")) ) )  $this->finalize(array("status" => -1, "code" => "storeerror"));
    if ( MODULE == "hosting" ) $Client = "";
    if ( MODULE == "user" )    $Client = $this->client;
    if ( MODULE == "admin" )
    {
      $Client = $this->checkPostedStr("data", "client");
      if ( !$Client ) $this->finalize(array("status" => -1, "code" => "storeerror"));
    }

    // Set statistics on public functions
    $this->updStatDB("System", "store");

    // Produce results
    $Results  = $this->storeRowDB($Area, $Data, $Client);

    if ( is_array($Text) && count($Text) )
    {
      $Results["textids"] = array();
      foreach ( $Text as $T )
      {
        $Tmp = $this->storeRowDB("texts", $T, $Client, true); // Replace
        if ( isset($Tmp["id"]) && isset($T["shortcut"]) )  $Results["textids"][$T["shortcut"]] = $Tmp["id"];
      }
    }

    // ── Abhaengige Datensaetze in DERSELBEN Anfrage (31.08.2026) ───────────
    //
    // Verallgemeinerung des "texts"-Sonderfalls direkt darueber. Der stand
    // fest verdrahtet fuer EINE Tabelle da; gebraucht wird dasselbe aber fuer
    // Dialoge mit ihren Feldern, fuer Levels mit ihren Texten und absehbar
    // fuer weitere. Statt jedes Mal einen neuen Sonderfall zu bauen, nimmt
    // "children" die Tabelle als Angabe entgegen:
    //
    //   "children": [{
    //      "area":        "dialogfields",
    //      "key":         "dialogcode",   Spalte im Kind
    //      "parentvalue": "request",      Wert, der dort einzutragen ist
    //      "rows":        [ {...}, ... ],
    //      "removeids":   [ 12, 15 ]
    //   }]
    //
    // WARUM ERST NACH DEM HAUPTSATZ: die Kinder verweisen auf ihn. Und warum
    // die Loeschungen ZULETZT: schlaegt ein Schreibvorgang fehl, sind die
    // alten Zeilen dann noch da - das ist der harmlosere Ausgang.
    //
    // KEINE Transaktion ueber das Ganze: execDB() faengt Fehler einzeln ab und
    // die Verbindung kennt hier keine Klammer. Deshalb wird jeder Teilschritt
    // gezaehlt und das Ergebnis ehrlich zurueckgemeldet, statt Erfolg zu
    // behaupten. Die Oberflaeche kann dann gezielt nachbessern.
    $Children = $this->checkPostedArr("children");
    if ( is_array($Children) && count($Children) )
    {
      $Results["children"] = array();
      foreach ( $Children as $Kind )
      {
        $KArea = isset($Kind["area"]) ? trim((string) $Kind["area"]) : "";
        $KKey  = isset($Kind["key"])  ? trim((string) $Kind["key"])  : "";
        $KWert = isset($Kind["parentvalue"]) ? (string) $Kind["parentvalue"] : "";
        if ( !$KArea )  continue;

        $Bericht = array("area" => $KArea, "stored" => 0, "removed" => 0, "failed" => 0);

        foreach ( (array) ($Kind["rows"] ?? array()) as $Zeile )
        {
          if ( !is_array($Zeile) )  continue;
          // Die Verknuepfung setzt der Server, nicht die Oberflaeche: sonst
          // koennte eine Kindzeile an einen fremden Hauptsatz gehaengt werden.
          if ( $KKey !== "" )  $Zeile[$KKey] = $KWert;
          $Tmp = $this->storeRowDB($KArea, $Zeile, $Client);
          // ">= 0" ist die Erfolgsregel des Hauses: storeRowDB() meldet 0,
          // removeRowDB() dagegen 1 - beides ist Erfolg, nur -1 ist Fehler.
          // Ein "== 0" haette jedes gelungene Loeschen als Fehler gezaehlt.
          if ( isset($Tmp["status"]) && $Tmp["status"] >= 0 )  $Bericht["stored"]++;
          else                                                $Bericht["failed"]++;
        }

        foreach ( (array) ($Kind["removeids"] ?? array()) as $RID )
        {
          $RID = trim((string) $RID);
          if ( $RID === "" )  continue;
          // removeRowDB() ist kundengebunden: eine fremde Zeile findet es
          // nicht und meldet "nothingtoremove".
          $Tmp = $this->removeRowDB($KArea, $RID, $Client);
          if ( isset($Tmp["status"]) && $Tmp["status"] >= 0 )  $Bericht["removed"]++;
          else                                                $Bericht["failed"]++;
        }

        $Results["children"][] = $Bericht;
        if ( $Bericht["failed"] > 0 )
        {
          $Results["status"] = -1;
          $Results["code"]   = "storechilderror";
        }
      }
    }

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function storeregis()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks
    $Data = $this->checkPostedArr("data");
    if ( !count($Data) )  $this->finalize(array("status" => -1, "code" => "storeeregiserror"));

    // Set statistics on public functions
    $this->updStatDB("System", "storeregis");

    // Produce results
    $Results  = $this->storeRegisDB($Data);

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  public function version()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Checks are done within com class

    // Set statistics on public functions
    $this->updStatDB("System", "version");

    // Produce results
    $Results  = $this->getVersionDB();

    // Return
    $this->finalize($Results, __FUNCTION__);
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}

?>