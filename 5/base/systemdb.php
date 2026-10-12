<?php

class systemdb extends com
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  private   $centraldb;
  private   $envdb;
  private   $localdb;
  private   $pdoOptions;
  private   $pdoOptionsCentral;
  protected $db;
  protected $dbc;
  protected $dbe;
  // TEMP diagnostics (2026-07): last swallowed PDOException message, so a
  // silently-failed write can surface *why* instead of a bare "storeerror".
  protected $lastDBError;

  public function __construct($f3)
  {
    parent::__construct($f3);
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // PDO::ATTR_TIMEOUT not working with mysql on all platsforms, therefore additionally ini_set
    ini_set("default_socket_timeout", 2);

    // Treiber: "mysql" (Vorgabe) oder "pgsql". Kommt aus der jeweiligen
    // Modul-INI, Abschnitt [db], Schluessel "driver" - fehlt er, bleibt es bei
    // MySQL. So laufen beide Staende parallel, waehrend die Umstellung laeuft
    // (2026-08-14).
    $this->driver = $this->normalizeDriver($this->f3->get('db.driver'));

    // Die zentrale Datenbank (esx-107) hat einen EIGENEN Treiber. Sie liegt auf
    // einem anderen Rechner, wird von beiden APIs gelesen und zieht deshalb
    // nicht zwangslaeufig mit um: waehrend der Umstellung darf ein Server
    // lokal schon auf PostgreSQL laufen und zentral weiter auf MySQL - oder
    // umgekehrt. Beides sind getrennte Verbindungen, es gibt keine Abfrage
    // ueber die Grenze hinweg. Fehlt der Schluessel, bleibt es bei MySQL -
    // "db.driver" wirkt hier ausdruecklich NICHT mit (2026-08-14).
    $this->drivercentral = $this->normalizeDriver($this->f3->get('central.db.driver'));

    $this->pdoOptions        = $this->pdoOptionsFor($this->driver);
    $this->pdoOptionsCentral = $this->pdoOptionsFor($this->drivercentral);

    try {
      $this->db      = new DB\SQL(
        $this->buildDSN($this->f3->get('db.host'), $this->f3->get('db.name')),
        $this->f3->get('db.user'),
        $this->f3->get('db.pass'),
        $this->pdoOptions
      );
      $this->localdb = true; // Will only be set when connection was successful (try .. catch)
    } catch (PDOException $e) {
      if (strstr($e, 'SQLSTATE[')) {
        preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/', $e, $matches);
        $Code    = ($matches[1] == 'HT000' ? $matches[2] : $matches[1]);
        $Message = $matches[3];
        $this->debug("PDO", "Local DB not available");
        $this->debug("PDO", $Code . " " . $Message);
        $this->mailDBError("Local DB not available", $Code . " " . $Message);
      } else {
        $this->debug("PDO", $e);
        $this->mailDBError("Local DB not available", $e->getMessage());
      }
      $this->localdb = false;
      $this->finalize(array("status" => 0, "code" => "localdatabaseerror"));
    }

    if (
      $this->f3->get('central.db.host') && $this->f3->get('central.db.name')
      && $this->f3->get('central.db.user') && $this->f3->get('central.db.pass')
    ) {
      try {
        $this->dbc = new DB\SQL(
          $this->buildDSN($this->f3->get('central.db.host'), $this->f3->get('central.db.name'), true),
          $this->f3->get('central.db.user'),
          $this->f3->get('central.db.pass'),
          $this->pdoOptionsCentral
        );
        $this->centraldb = true; // Will only be set when connection was successful (try .. catch)
      } catch (PDOException $e) {
        if (strstr($e, 'SQLSTATE[')) {
          preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/', $e, $matches);
          $Code    = ($matches[1] == 'HT000' ? $matches[2] : $matches[1]);
          $Message = $matches[3];
          $this->debug("PDO", "Central DB not available");
          $this->debug("PDO", $Code . " " . $Message);
          $this->mailDBError("Central DB not available", $Code . " " . $Message);
        } else {
          $this->debug("PDO", $e);
          $this->mailDBError("Central DB not available", $e->getMessage());
        }
        $this->centraldb = false;
      }
    }
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  private function setEnvDB($Env)
  {
    if (
      $this->f3->get('deploydbs.' . $Env) && $this->f3->get('db.name')
      && $this->f3->get('deployuser.user') && $this->f3->get('deployuser.pass')
    ) {
      try {
        // Treiber des ZIELSERVERS, nicht der hiesige (20.08.2026). Vorher
        // stand hier buildDSN()/$this->pdoOptions, beides am lokalen Treiber
        // haengend: seit der Umstellung dieses Rechners auf PostgreSQL ging
        // die Verbindung als "pgsql:host=esx-33...;port=5432" hinaus, obwohl
        // der Testserver MySQL spricht - und Port 5432 ist dort von aussen zu.
        // Der Verbindungsversuch scheiterte, execDB() lieferte [], und die
        // Konfigurationsseite in Admin2026 zeigte alle Felder ohne Werte.
        $Driver    = $this->driverForEnv($Env);
        $this->dbe = new DB\SQL(
          $this->buildDSNFor($Driver, $this->f3->get('deploydbs.' . $Env), $this->f3->get('db.name')),
          $this->f3->get('deployuser.user'),
          $this->f3->get('deployuser.pass'),
          $this->pdoOptionsFor($Driver)
        );
        $this->envdb = true; // Will only be set when connection was successful (try .. catch)
      } catch (PDOException $e) {
        if (strstr($e, 'SQLSTATE[')) {
          preg_match('/SQLSTATE\[(\w+)\] \[(\w+)\] (.*)/', $e, $matches);
          $Code    = ($matches[1] == 'HT000' ? $matches[2] : $matches[1]);
          $Message = $matches[3];
          $this->debug("PDO", $Env . " DB not available");
          $this->debug("PDO", $Code . " " . $Message);
          $this->mailDBError($Env . " DB not available", $Code . " " . $Message);
        } else {
          $this->debug("PDO", $e);
          $this->mailDBError($Env . " DB not available", $e->getMessage());
        }
        $this->envdb = false;
      }
    }
  }

  public function EncryptString($String)
  {
    if (!isset($this->dbpw)) {
      $this->logs->Insert("SessionError|EncryptString|" . $String, "partners");
      return "";
    }
    $key   = substr(sha1($this->dbpw, true), 0, 16);
    $iv    = openssl_random_pseudo_bytes(16);
    $Crypt = $iv . openssl_encrypt($String, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $Crypt;
  }

  private function DecryptString($Crypt)
  {
    if (!isset($this->dbpw)) {
      $this->logs->Insert("SessionError|DecryptString|" . $Crypt, "partners");
      return "";
    }
    $key = substr(sha1($this->dbpw, true), 0, 16);
    $iv  = substr($Crypt, 0, 16);
    $String  = openssl_decrypt(substr($Crypt, 16), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $String;
  }

  public function EncodeString($String)
  {
    if (trim($String) != "") {
      //$String = htmlentities(trim($String), ENT_QUOTES);
      //$String = htmlspecialchars(trim($String), ENT_NOQUOTES, "UTF-8");
      $String = trim($String);
    }
    return $String;
  }

  public function EncodeArray($Array)
  {
    return (serialize($Array));
  }

  private function DecodeString($String)
  {
    if (trim($String) != "") {
      $String = htmlspecialchars_decode(trim($String), ENT_NOQUOTES);
    }
    return $String;
  }

  private function DecodeArray($Array)
  {
    $Array = unserialize($Array);
    return ($Array);
  }

  private function getCountry2IP()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (ACTION == "batch") return "Germany";

    $Country = "";

    // Item configs
    // Tages- und clientunabhaengig (17.08.2026): die Zuordnung Adresse -> Land
    // haengt von beidem nicht ab. Vorher lag sie unter <cache>/<Tag>/<Client>/
    // und wurde deshalb fuer jeden der 33 Clients und an jedem neuen Tag erneut
    // zentral nachgeschlagen. Der Dateiname wird gesaeubert, weil er aus einer
    // Angabe des Aufrufers stammt (X-Real-IP, X-Forwarded-For) und sonst
    // Verzeichnistrenner enthalten koennte.
    $this->createPath(IPCACHEDIR);
    $File = IPCACHEDIR . preg_replace('/[^0-9a-f.:]/i', "_", strtolower((string) $this->clientip)) . ".inc";
    if (file_exists($File)) {
      // if ( MODE != "production" ) $this->updStatDB("Cache", "IP.yes");
      $Country = json_decode(file_get_contents($File), true);
    } else {
      // Read config
      // if ( MODE != "production" ) $this->updStatDB("Cache", "IP.no");

      // MySQL haelt ipfrom/ipto als VARBINARY(16) und vergleicht die von
      // inet_pton() gelieferten Rohbytes. Unter PostgreSQL waeren das bytea -
      // rohe Bytes mit Nullzeichen lassen sich ueber PDO aber nicht zuverlaessig
      // binden. Dort steht deshalb der Typ "inet": verglichen wird die Adresse
      // selbst, gebunden als gewoehnliche Zeichenkette. Die zentrale Tabelle
      // muss unter PostgreSQL entsprechend als inet angelegt werden
      // (2026-08-14).
      // Schema der ZENTRALEN Tabellen, falls eines noetig ist (12.09.2026).
      //
      // Bei Lukida ist die zentrale Datenbank eine eigene Datenbank auf einem
      // eigenen Rechner - die DSN waehlt sie aus, der Tabellenname steht nackt
      // da. Bei Karim ist sie ein SCHEMA derselben Datenbank, und der Suchpfad
      // der zentralen Rolle enthaelt es nicht; ohne Zusatz meldet PostgreSQL
      // dort 'relation "ip2country" does not exist' (08./09.09.2026).
      //
      // Statt zwei Fassungen derselben Datei: der Zusatz steht in der
      // Konfiguration, [central.db] schema. Fehlt er, bleibt der Name nackt.
      $Zentral = trim((string) $this->f3->get("central.db.schema"));
      $Tabelle = ($Zentral !== "" ? $Zentral . "." : "") . "ip2country";

      if ($this->isPG(true)) {
        $Data = array("ip" => $this->clientip);
        $SQL  = "SELECT country FROM " . $Tabelle . " WHERE cast(:ip as inet) >= ipfrom and cast(:ip as inet) < ipto order by ipto limit 1";
      } else {
        $Data = array("ip" => inet_pton($this->clientip));
        $SQL  = "SELECT country FROM " . $Tabelle . " WHERE :ip >= ipfrom and :ip < ipto order by ipto limit 1";
      }
      $Rows = $this->execDB($SQL, $Data, true);
      $Country = isset($Rows[0]["country"]) ? $Rows[0]["country"] : "Unknown";

      // Ein zweiter Versuch, falls das Verzeichnis zwischendurch fehlt
      // (01.09.2026).
      //
      // Bei aelteren Staenden liegt der Zwischenspeicher unter dem TAEGLICH neu
      // entstehenden Baum, auf esx-35 zudem auf einer RAM-Disk. Beim Tageswechsel
      // gibt es ein Fenster, in dem der Pfad noch nicht da ist - gemessen am
      // 01.09.2026 um 04:58:15, in derselben Minute, in der das Tagesverzeichnis
      // angelegt wurde. Die Anfrage scheiterte mit "Failed to open stream: No
      // such file or directory", obwohl an Rechten, Platz und Inodes nichts
      // fehlte.
      //
      // Der Landeseintrag ist reiner Zwischenspeicher: misslingt er auch beim
      // zweiten Mal, wird beim naechsten Aufruf eben wieder nachgeschlagen.
      // Deshalb nur EIN Wiederholungsversuch und kein Fehler nach aussen - aber
      // ein Vermerk im Protokoll, damit es nicht still bleibt.
      if ( @file_put_contents($File, json_encode($Country, true)) === false )
      {
        $this->createPath(dirname($File) . DIRECTORY_SEPARATOR);
        if ( @file_put_contents($File, json_encode($Country, true)) === false )
          $this->debug("LOG", "getCountry2IP: Zwischenspeicher nicht schreibbar: " . $File);
      }
    }

    if (!trim($Country))  $Country = "Unknown";

    return $Country;
  }


  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************
  // ***********************************************************************
  // ***** Treiberabhaengige Bausteine (MySQL / PostgreSQL) *****************
  // ***********************************************************************
  //
  // Die Umstellung auf PostgreSQL beruehrt drei Dinge, die sonst an 31 Stellen
  // einzeln stuenden: Verbindungszeichenfolge, Upsert und Volltextsuche.
  // Sie liegen deshalb hier gebuendelt; die Aufrufer bleiben unveraendert
  // (2026-08-14).

  protected $driver        = "mysql";
  protected $drivercentral = "mysql";

  // Treiber der ZIELUMGEBUNG waehrend eines Fernzugriffs (readDB mit $Env).
  // null = kein Fernzugriff, es gilt der lokale Treiber. Siehe beginEnvDriver()
  // weiter unten (20.08.2026).
  protected $driverenv     = null;

  // $Central waehlt die Verbindung: false = lokale Modul-Datenbank,
  // true = zentrale Datenbank auf esx-107. Beide koennen unterschiedliche
  // Treiber haben, deshalb reicht ein einzelnes isPG() nicht mehr.
  //
  // Dritter Fall seit 20.08.2026: der Fernzugriff auf eine ANDERE Umgebung
  // (Dashboard-Zahlen von test/prod). Auch der kann einen eigenen Treiber
  // haben - dieser Rechner laeuft auf PostgreSQL, der Testserver auf MySQL.
  // Ohne diese Abfrage baute die gesamte SQL-Erzeugung (schemaOf, ident,
  // sqlLimit, sqlDup, sqlDate) weiter PostgreSQL-Syntax und schickte sie an
  // eine MySQL-Datenbank.
  public function isPG($Central = false)
  {
    if ($Central)                      return ($this->drivercentral == "pgsql");
    if ($this->driverenv !== null)     return ($this->driverenv     == "pgsql");
    return ($this->driver == "pgsql");
  }

  // Treiber des Zielservers einer Umgebung. Steht in configs/<Umgebung>/
  // database.ini, Abschnitt [deploydb] - fehlt der Eintrag, gilt MySQL, genau
  // wie bei den Batch-Skripten (batches/phps/_dbconfig.php::LukidaTargetDB()).
  //
  // ACHTUNG, Erreichbarkeit: ein MySQL-Ziel hoert auf dem Netz, ein
  // PostgreSQL-Ziel NUR auf localhost - Port 5432 ist auf esx-32/33/35 von
  // aussen zu. Die Batch-Skripte legen dafuer einen SSH-Tunnel an; die API
  // kann das nicht. Ein ECHTER Fernzugriff auf eine bereits umgestellte
  // Umgebung bleibt deshalb leer (und meldet das ueber mailDBError).
  //
  // Stand 30.08.2026: dev und test sprechen PostgreSQL, nur prod (esx-35)
  // noch MySQL. Von aussen erreichbar ist damit allein prod. Der haeufigste
  // Fall - eine Umgebung fragt ihre EIGENEN Zahlen ab - laeuft seit heute
  // gar nicht mehr ueber diesen Weg, siehe isOwnEnv() weiter unten.

  // Ist die gefragte Umgebung dieser Rechner? (30.08.2026)
  //
  // Die Schluessel der Fernzugriffe heissen dev/test/prod, MODE dagegen
  // development/test/production - nur "test" stimmt woertlich ueberein. Ohne
  // diese Zuordnung wuerde die Pruefung ausgerechnet auf dem Produktionsserver
  // versagen und dort denselben stillen Ausfall erzeugen, sobald auch esx-35
  // auf PostgreSQL umzieht.
  // Ist das ein JSON-Spaltentyp? (31.08.2026)
  //
  // information_schema.columns.data_type meldet unter MySQL "json", unter
  // PostgreSQL dagegen "jsonb" (so sind die Spalten dort angelegt). Der
  // Vergleich stand fest auf "json" - unter PostgreSQL griff die Umwandlung
  // deshalb nie:
  //
  //   Schreiben: die PHP-Liste ging UNCODIERT an PDO. Daraus wurde die
  //              Zeichenkette "Array", und PostgreSQL antwortete mit
  //              "invalid input syntax for type json, Token \"Array\" is
  //              invalid" - genau daran scheiterte das Anlegen eines
  //              Hosting-Benutzers (Spalte "clients").
  //   Lesen:     json_decode() wurde uebersprungen, der Aufrufer bekam die
  //              rohe Zeichenkette statt einer Liste.
  //
  // Beide Namen gelten hier gleich.
  protected function isJsonType($Type)
  {
    return in_array(strtolower(trim((string) $Type)), array("json", "jsonb"), true);
  }

  protected function isOwnEnv($Env)
  {
    $Map = array("dev" => "development", "prod" => "production",
                 "test" => "test", "local" => "local");
    $Key = strtolower(trim((string) $Env));
    return (($Map[$Key] ?? $Key) === MODE);
  }

  protected function driverForEnv($Env)
  {
    return $this->normalizeDriver($this->f3->get('deploydb.' . $Env));
  }

  // Umschalten der SQL-Erzeugung auf die Zielumgebung. Immer paarweise mit
  // endEnvDriver() verwenden (try/finally), sonst bleibt der Schalter stehen
  // und die naechste ORTLICHE Abfrage wuerde im falschen Dialekt gebaut.
  protected function beginEnvDriver($Env)
  {
    // Die eigene Umgebung wird oertlich gelesen (siehe execDB) - dann muss auch
    // die SQL-Erzeugung beim lokalen Treiber bleiben. Stuende hier weiter
    // [deploydb] test = mysql, baute die Anfrage MySQL-Syntax fuer eine
    // PostgreSQL-Verbindung (30.08.2026).
    $this->driverenv = ($Env && !$this->isOwnEnv($Env)) ? $this->driverForEnv($Env) : null;
  }

  protected function endEnvDriver()
  {
    $this->driverenv = null;
  }

  protected function normalizeDriver($Value)
  {
    $Drv = strtolower(trim((string) $Value));
    return ($Drv == "pgsql" || $Drv == "postgres" || $Drv == "postgresql") ? "pgsql" : "mysql";
  }

  // Der Zeichensatzbefehl gilt nur fuer MySQL. Da lokale und zentrale
  // Verbindung verschiedene Treiber haben koennen, braucht jede ihren
  // eigenen Satz Optionen.
  protected function pdoOptionsFor($Driver)
  {
    // PDO::ATTR_TIMEOUT not working with mysql on all platforms, therefore additionally ini_set
    $Opts = array(
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_PERSISTENT         => false,
      PDO::ATTR_TIMEOUT            => 2
    );
    if ($Driver == "mysql") {
      if (substr(PHP_VERSION, 0, 3) >= "8.5") {
        $Opts[PDO\Mysql::ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
      } else {
        // 8.3, 8.4
        $Opts[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
      }
    }
    return $Opts;
  }

  // Verbindungszeichenfolge. Bei PostgreSQL liegen die frueheren drei
  // Datenbanken als drei Schemas in EINER Datenbank ("lukida"); der Name der
  // Modul-Datenbank wird deshalb nicht mehr in die DSN geschrieben, sondern
  // ueber den Suchpfad der Rolle aufgeloest (siehe
  // others/sql/postgres/00_database_roles.sql).
  //
  // Fuer die zentrale Datenbank gilt das NICHT: sie ist eine gewoehnliche
  // einzelne Datenbank ohne Schema-Aufteilung, ihr Name steht wie bisher in
  // "central.db.name" und wandert unveraendert in die DSN.
  protected function buildDSN($Host, $Name, $Central = false)
  {
    return $this->buildDSNFor($this->isPG($Central) ? "pgsql" : "mysql", $Host, $Name, $Central);
  }

  // Wie buildDSN(), aber mit ausdruecklich genanntem Treiber. Gebraucht fuer
  // die Verbindung in eine ANDERE Umgebung: deren Treiber hat mit dem hiesigen
  // nichts zu tun (20.08.2026).
  protected function buildDSNFor($Driver, $Host, $Name, $Central = false)
  {
    if ($Driver == "pgsql") {
      if ($Central)  return 'pgsql:host=' . $Host . ';port=5432;dbname=' . $Name . ';';
      // Rueckfall ohne festen Namen (12.09.2026).
      //
      // Hier stand "lukida" fest verdrahtet - auch in der Karim-Fassung dieser
      // Datei, die damit im Notfall eine fremde Datenbank angesprochen haette.
      // "application.type" nennt die Anwendung ohnehin (lukida, karim); als
      // letztes bleibt der uebergebene Name.
      $DB = trim((string) $this->f3->get('db.database'));
      if (!$DB)  $DB = trim((string) $this->f3->get('application.type'));
      if (!$DB)  $DB = $Name;
      return 'pgsql:host=' . $Host . ';port=5432;dbname=' . $DB . ';';
    }
    return 'mysql:host=' . $Host . ';port=3306;dbname=' . $Name . ';charset=utf8mb4;';
  }

  // Seitenweises Blaettern. MySQL: "limit <ab>,<anzahl>".
  // PostgreSQL kennt nur "limit <anzahl> offset <ab>". Die Namen der beiden
  // gebundenen Werte bleiben in beiden Faellen :start und :limit, die Aufrufer
  // aendern ihr $Data also nicht.
  protected function sqlLimit($Central = false)
  {
    return $this->isPG($Central) ? " limit :limit offset :start" : " limit :start,:limit";
  }

  // "Einfuegen, sonst aktualisieren". MySQL kennt ON DUPLICATE KEY UPDATE ohne
  // Angabe des Schluessels, PostgreSQL verlangt bei ON CONFLICT das Ziel.
  // $Conflict nennt die Spalten des eindeutigen Schluessels; fehlt die Angabe,
  // wird unter PostgreSQL auf "id" zurueckgefallen.
  protected function sqlUpsert($Table, $Fields, $Conflict = array())
  {
    $Quoted = array();
    foreach ($Fields as $F)  $Quoted[] = $this->ident($F);
    $Cols = implode(", ", $Quoted);
    $Vals = ":" . implode(", :", $Fields);

    if ($this->isPG()) {
      if (!count($Conflict))  $Conflict = array("id");
      $Set = array();
      foreach ($Fields as $F) {
        if (in_array($F, $Conflict))  continue;
        $Set[] = $this->ident($F) . " = EXCLUDED." . $this->ident($F);
      }
      $SQL = "insert into " . $Table . " (" . $Cols . ") values (" . $Vals . ")";
      $QConf = array();
      foreach ($Conflict as $C)  $QConf[] = $this->ident($C);
      $SQL .= " on conflict (" . implode(", ", $QConf) . ") do update set " . implode(", ", $Set);
      $SQL .= " returning id";
      return $SQL;
    }

    $Set = array();
    foreach ($Fields as $F)  $Set[] = $F . " = :" . $F;
    // LAST_INSERT_ID(id) sorgt dafuer, dass auch beim Aktualisieren eine
    // brauchbare Kennung zurueckkommt - unter PostgreSQL leistet das RETURNING.
    return "insert into " . $Table . " (" . $Cols . ") values (" . $Vals . ")"
         . " ON DUPLICATE KEY UPDATE " . implode(", ", $Set) . ", id = LAST_INSERT_ID(id)";
  }

  // Volltextsuche. MySQL: MATCH(...) AGAINST(... IN BOOLEAN MODE).
  // PostgreSQL kennt das nicht - dort steht ein Teilstringvergleich ueber die
  // verketteten Spalten, beschleunigt durch die GIN/pg_trgm-Indizes aus
  // others/sql/postgres/03_fulltext.sql. Das trifft anders: Teiltreffer an
  // jeder Stelle statt der +/-/*-Operatoren des Boolean-Modus.
  // "Einfuegen, vorhandene Zeile ersetzen". MySQL: REPLACE INTO - es loescht die
  // vorhandene Zeile und legt eine neue an. PostgreSQL kennt das nicht; dort
  // steht INSERT ... ON CONFLICT DO UPDATE. $Conflict nennt die Spalten des
  // eindeutigen Schluessels; hat die Tabelle keinen (projects), bleibt es beim
  // reinen INSERT - genau das tut REPLACE dort ebenfalls (2026-08-14).
  protected function sqlReplace($Table, $Fields, $Conflict = array())
  {
    $Vals = ":" . implode(", :", $Fields);

    if ( !$this->isPG() )
      return "replace into " . $Table . " (" . implode(", ", $Fields) . ") values (" . $Vals . ")";

    $Quoted = array();
    foreach ( $Fields as $F )  $Quoted[] = $this->ident($F);
    $SQL = "insert into " . $Table . " (" . implode(", ", $Quoted) . ") values (" . $Vals . ")";

    if ( !count($Conflict) )  return $SQL;

    $Set = array();
    foreach ( $Fields as $F )
    {
      if ( in_array($F, $Conflict) )  continue;
      $Set[] = $this->ident($F) . " = EXCLUDED." . $this->ident($F);
    }
    $QC = array();
    foreach ( $Conflict as $C )  $QC[] = $this->ident($C);
    $SQL .= " on conflict (" . implode(", ", $QC) . ")";
    $SQL .= count($Set) ? " do update set " . implode(", ", $Set) : " do nothing";
    return $SQL;
  }

  protected function sqlFulltext($Columns, $Param = ":search")
  {
    if (!is_array($Columns))  $Columns = array($Columns);

    if ($this->isPG()) {
      $Parts = array();
      foreach ($Columns as $C)  $Parts[] = "coalesce(" . $C . "::text,'')";
      // prepareSearch() setzt MySQL-Platzhalter: Leerzeichen und das Ende
      // werden zu "*". Fuer ILIKE ist "*" ein gewoehnliches Zeichen - deshalb
      // hier zu "%" umgesetzt, statt prepareSearch() anzufassen (die Methode
      // ist statisch und kennt den Treiber nicht) (14.08.2026).
      return "(" . implode(" || ' ' || ", $Parts) . ") ILIKE '%' || replace(" . $Param . ", '*', '%') || '%'";
    }
    return "MATCH(" . implode(", ", $Columns) . ") AGAINST (" . $Param . " in boolean mode)";
  }

  // Spalten des eindeutigen Schluessels einer Tabelle - Konfliktziel fuer
  // ON CONFLICT. MySQL braucht das nicht, PostgreSQL schon. Ergebnis wird je
  // Anfrage gemerkt; ohne passenden Schluessel bleibt es bei "id".
  private $uniquecache = array();

  protected function uniqueKeyOf($Table)
  {
    $Table = trim($Table);
    if (isset($this->uniquecache[$Table]))  return $this->uniquecache[$Table];

    $Cols = array();
    if ($this->isPG()) {
      $Sch = "";
      $Tbl = $Table;
      if (strpos($Table, ".") !== false)  list($Sch, $Tbl) = explode(".", $Table, 2);
      $SQL  = "select a.attname from pg_index i";
      $SQL .= " join pg_class c on c.oid = i.indrelid";
      $SQL .= " join pg_namespace n on n.oid = c.relnamespace";
      $SQL .= " join unnest(i.indkey) with ordinality k(att, ord) on true";
      $SQL .= " join pg_attribute a on a.attrelid = c.oid and a.attnum = k.att";
      $SQL .= " where c.relname = :tbl and i.indisunique and not i.indisprimary";
      if ($Sch)  $SQL .= " and n.nspname = :sch";
      $SQL .= " order by k.ord limit 10";
      $Data = array("tbl" => trim($Tbl, '"'));
      if ($Sch)  $Data["sch"] = trim($Sch, '"');
      foreach ($this->execDB($SQL, $Data) as $R)  $Cols[] = $R["attname"];
    }

    return ($this->uniquecache[$Table] = $Cols);
  }

  // Kennung der gerade eingefuegten Zeile.
  //
  // MySQL: lastInsertId() ohne Argument. PostgreSQL: dort braeuchte
  // lastInsertId() den Namen der Sequenz - stattdessen liefern die
  // Insert-Anweisungen "returning id" mit (siehe sqlUpsert), und der Wert
  // steht dann in der ersten Ergebniszeile (2026-08-14).
  protected function newIDFrom($Rows, $Conn)
  {
    if ($this->isPG()) {
      if (is_array($Rows) && isset($Rows[0]) && is_array($Rows[0]) && isset($Rows[0]["id"])) {
        return $Rows[0]["id"];
      }
      return 0;
    }
    return $Conn ? $Conn->lastInsertId() : 0;
  }

  // Voll qualifizierter Name eines Bereichs.
  //
  // MySQL: drei Datenbanken, "lukida_admin.cases".
  // PostgreSQL: drei Schemas in EINER Datenbank, "admin.cases" - und "user"
  // muss in Anfuehrungszeichen, weil es dort ein reserviertes Wort ist.
  // Aufruf: $this->schemaOf("admin") . ".cases" (2026-08-14).
  protected function schemaOf($Module)
  {
    $Module = strtolower(trim($Module));
    if ($this->isPG())  return ($Module == "user") ? '"user"' : $Module;
    return $this->f3->get("application.type") . "_" . $Module;
  }

  // "Einfuegen, sonst aktualisieren" fuer die vorgefertigten Anweisungen mit
  // eigener SET-Liste. $Set steht in BEIDEN Treibern gleich - PostgreSQL
  // versteht unqualifizierte Spalten in DO UPDATE SET als die der Zielzeile,
  // "amount = amount + 1" gilt dort also genauso. Nur das Konfliktziel muss
  // benannt werden (14.08.2026).
  protected function sqlDup($Set, $Conflict)
  {
    if ($this->isPG()) {
      $Q = array();
      foreach ($Conflict as $C)  $Q[] = $this->ident($C);
      return " on conflict (" . implode(", ", $Q) . ") do update set " . $Set;
    }
    return " on duplicate key update " . $Set;
  }

  // Spaltenname als Bezeichner. Unter PostgreSQL werden ALLE Spalten in
  // Anfuehrungszeichen gesetzt - sonst brechen reservierte Namen wie "user"
  // und "end" die Anweisung, und zwar still: execDB() faengt die Ausnahme ab,
  // storeRowDB() meldete daraufhin trotzdem Erfolg (14.08.2026 aufgefallen,
  // settings-Tabelle blieb leer). Eine Liste reservierter Woerter zu pflegen
  // waere fehleranfaellig - pauschal klammern ist verlaesslich.
  protected function ident($Name)
  {
    return $this->isPG() ? '"' . str_replace('"', '', $Name) . '"' : $Name;
  }

  // Wie schemaOf(), aber OHNE Anfuehrungszeichen - fuer Stellen, an denen der
  // Schemaname als WERT gebraucht wird (information_schema.TABLE_SCHEMA).
  protected function schemaName($Module)
  {
    return trim($this->schemaOf($Module), '"');
  }

  // Datum als Text. MySQL: DATE_FORMAT mit %-Platzhaltern, PostgreSQL: to_char
  // mit eigenen Mustern. $Pattern ist das MySQL-Muster, es wird umgesetzt
  // (2026-08-14).
  protected function sqlDate($Column, $Pattern = "%d.%m.%Y")
  {
    if ($this->isPG()) {
      $Map = array("%d" => "DD", "%m" => "MM", "%Y" => "YYYY", "%y" => "YY",
                   "%H" => "HH24", "%i" => "MI", "%s" => "SS");
      return "to_char(" . $this->ident($Column) . ", '" . strtr($Pattern, $Map) . "')";
    }
    return "DATE_FORMAT(" . $Column . ", '" . $Pattern . "')";
  }

  protected function execDB($SQL, $Params = [], $Central = false, $Env = "")
  {
    if (APPDEBUG["SQQ"])                    $this->debug("SQQ", $SQL);
    if (APPDEBUG["SQP"] && !empty($Params)) $this->debug("SQP", $Params);
    $Insert = (strncasecmp(trim($SQL), "insert ", 7) === 0) ? true : false;
    $Stats  = [];
    try {
      if ($Central) {
        $Rows = $this->centraldb ? $this->dbc->exec($SQL, $Params) : [];
        if ($Insert) {
          $Stats["newID"]       = $this->newIDFrom($Rows, $this->dbc);
        }
      // "$Env != MODE": die EIGENE Umgebung ist kein Fernzugriff (30.08.2026).
      //
      // Admin2026 liest seine statslive-Zahlen mit env:"test". Laeuft die API
      // selbst auf dem Testserver, zeigte diese Anfrage bisher ueber das Netz
      // auf esx-33.gbv.de - also auf den Rechner, auf dem sie ohnehin schon
      // laeuft. Unter MySQL fiel das nicht auf: der Dienst hoerte auf dem Netz,
      // und den Zugang "remote" gab es. Nach der Umstellung auf PostgreSQL
      // scheitert derselbe Weg dreifach - Port 5432 ist von aussen zu, die
      // Rolle "remote" gibt es dort nicht, und [deploydb] test sagt weiterhin
      // MySQL, dessen Dienst inzwischen abgeschaltet ist.
      //
      // Folge war der stille Ausfall: setEnvDB() faengt den Fehler, envdb
      // bleibt false, execDB() liefert [] - im Dashboard blieben genau die
      // env:"test"-Kacheln leer (alle statslive-Bereiche), waehrend env:"prod"
      // (esx-35, noch MySQL) und env:"" weiter Zahlen zeigten.
      //
      // Der eigene Rechner wird jetzt ueber die oertliche Verbindung gelesen:
      // richtiger Treiber, richtige Rechte, und ein Netzweg weniger.
      } elseif ($Env && !$this->isOwnEnv($Env)) {
        $this->setEnvDB($Env);
        $Rows = $this->envdb   ? $this->dbe->exec($SQL, $Params)  : [];
        if ($Insert) {
          $Stats["newID"]       = $this->newIDFrom($Rows, $this->dbe);
        }
      } else {
        $Rows = $this->localdb   ? $this->db->exec($SQL, $Params)  : [];
        if ($Insert) {
          $Stats["newID"]       = $this->newIDFrom($Rows, $this->db);
        }
      }
      if (APPDEBUG["SQD"] &&  empty($Rows))   $this->debug("SQD", "No records found!");
      if (APPDEBUG["SQD"] && !empty($Rows))   $this->debug("SQD", $Rows);
      // $this->debug("Type", gettype($Rows));
      // $this->debug("Return", is_array($Rows) ? $Rows : $Stats);
      return $Insert ? $Stats : $Rows;
      // return $Rows;
    } catch (PDOException $e) {
      // Kompakt statt Objektabzug (17.08.2026).
      //
      // Vorher stand hier $this->debug("PDO", $e) - das schreibt das ganze
      // Exception-Objekt ins Protokoll, rund 180 Zeilen je Vorfall, und
      // ausgerechnet die ausloesende Abfrage fehlt darin. Wer nachsehen will,
      // was schiefging, muss sich die Aufrufkette aus dem Abzug klauben.
      //
      // Jetzt eine Kopfzeile mit SQLSTATE, Meldung und Abfrage, dazu die ersten
      // Aufrufer - genau das, was bei den Sperrzeitueberschreitungen vom
      // 16.08.2026 gebraucht wurde (updStatDB aus updPlatformDB).
      $Kette = array();
      foreach (array_slice($e->getTrace(), 0, 6) as $Schritt) {
        $Kette[] = ($Schritt["class"] ?? "") . ($Schritt["type"] ?? " ") . ($Schritt["function"] ?? "?");
      }
      // Kein getCode() davor: die Meldung traegt "SQLSTATE[...]" bereits selbst.
      $this->debug("PDO", $e->getMessage()
                        . " | SQL: " . preg_replace('/\s+/', " ", trim((string) $SQL))
                        . " | via " . implode(" < ", $Kette));

      $this->lastDBError = $e->getMessage();
      $this->mailDBError("execDB query failed", $e->getMessage(), $SQL);
      return [];
    }
  }

  protected function checkGetUserDB($User, $Pass)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      "username" => $User,
      "active"   => "y"
    );
    $SQL  = "select username, clients, pwhash, gender, title, firstname, lastname, mail, start, avatar";
    if (MODULE == "hosting") $SQL .= " from users";
    if (MODULE == "admin")   $SQL .= " from hostingusers";
    if (MODULE == "user")    $SQL .= " from adminusers";
    $SQL .= " where username = :username and active = :active";
    $Rows = $this->execDB($SQL, $Data);

    if (count($Rows) == "1") {
      $Row = $Rows[0];
      if (password_verify($Pass, $Row["pwhash"])) {
        unset($Row["pwhash"]);
        $Row["clients"] = (MODULE != "user") ? json_decode($Row["clients"]) :  array($Row["clients"]);
        $Row["token"]   = $this->encodeToken(array(
          'un' => $Row["username"],
          'cl' => $this->client,
          'av' => $Row["avatar"],
          'dt' => time()
        ));
        $Row["name"]    = $Row["firstname"] . " " . $Row["lastname"];
        $Row["notes"]   = [];
        sort($Row["clients"]);
        return $Row;
      }
    }
    return [];
  }

  protected function getAdminConfigsDB($Selection = [], $Filter = false)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (is_array($Selection) && !count($Selection) && $Filter) return [];

    $Data = array(
      "client" => $this->client,
    );
    $SQL  = "SELECT category, setting, value FROM " . $this->schemaOf(MODULE);
    $SQL .= (MODULE == "admin") ? ".configs" : ".adminconfigs";
    $SQL .= " WHERE client = :client";
    if (is_array($Selection) && $Filter) $SQL .= " and category in ('" . implode("', '", $Selection) . "')";

    $Rows = $this->execDB($SQL, $Data);

    $Confs = [];
    foreach ($Rows as $Row) {
      $Confs[$Row["category"]][$Row["setting"]] = $Row["value"];
    }
    return $Confs;
  }

  protected function getAdminTextsDB($Selection = [], $Filter = false, $Client = "")
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (is_array($Selection) && !count($Selection) && $Filter) return [];

    /* Im Admin Modul wird die ID zur Pflege benötigt, sonst nicht */
    $Data = array(
      "client" => $Client ? $Client : $this->client,
    );
    $SQL  = (MODULE == "admin") ? "SELECT id," : "select";
    $SQL .= " shortcut, german, english FROM " . $this->schemaOf(MODULE);
    $SQL .= (MODULE == "admin") ? ".texts" : ".admintexts";
    $SQL .= " WHERE client = :client";
    if (is_array($Selection) && $Filter) $SQL .= " and (shortcut like '" . implode("' or shortcut like '", $Selection) . "')";
    $Rows = $this->ExecDB($SQL, $Data);

    // Return fetched data
    $Texts = [];
    foreach ($Rows as $Row) {
      $Texts[$Row["shortcut"]] = array(
        "german"  => $Row["german"]  !== null && trim($Row["german"])  !== '' ? trim($Row["german"])  : "",
        "english" => $Row["english"] !== null && trim($Row["english"]) !== '' ? trim($Row["english"]) : ""
      );
      if ($Client)   $Texts[$Row["shortcut"]]["id"] = $Row["id"];
    }

    return $Texts;
  }


  protected function getRegisDB($Regis)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      "regisid" => $Regis,
    );

    // "end" ist unter PostgreSQL ein RESERVIERTES WORT und muss geklammert
    // werden - ident() macht das treiberrichtig. Unkgeklammert ist die
    // Anweisung dort ein harter Syntaxfehler ("syntax error at or near
    // \"end\""), den execDB() verschluckt: es kam eine leere Menge zurueck,
    // und beide Pruefungen unten lasen daraus "Link ungueltig, abgelaufen
    // oder schon benutzt". Damit war JEDER Registrierungslink sofort tot
    // (31.08.2026). Unter MySQL ist "end" nicht reserviert, dort fiel es nie
    // auf.
    $SQL  = "select userid, " . $this->ident("end") . " from registrations where regisid = :regisid";
    $Rows = $this->execDB($SQL, $Data);

    // TEMP diagnostics (2026-07): "debug" pinpoints which check rejected the
    // regisid without leaking any actual row data - remove once bug (b) is confirmed fixed.
    if (count($Rows) != 1) {
      return array("status" => 0, "code" => "regiserror", "debug" => "registrations_row_not_found");
    }

    // Compare as actual dates (not raw strings) - "end" being a moment-of-day
    // DATETIME rather than a plain DATE would otherwise skew a lexical compare.
    if (!isset($Rows[0]["end"]) || strtotime($Rows[0]["end"]) < strtotime("today")) {
      return array("status" => 0, "code" => "regiserror", "debug" => "registrations_row_expired");
    }

    $UserID = $Rows[0]["userid"];

    $Data = array(
      "id"     => $UserID,
      "active" => "n"
    );
    $SQL  = "select username, gender, title, firstname, lastname";
    $SQL .= " from users where id = :id and active = :active";
    $Rows = $this->execDB($SQL, $Data);

    if (count($Rows) == "1")  return array("regis" => $Rows[0]);

    // TEMP diagnostics (2026-07): reveal *why* the pending-user lookup missed -
    // does a user with this id even still exist, and what's its current "active"?
    $Rows = $this->execDB("select username, active from users where id = :id", array("id" => $UserID));
    return array(
      "status" => 0,
      "code"   => "regiserror",
      "debug"  => "user_not_found_or_not_pending",
      "debuguserid" => $UserID,
      "debuguser"   => count($Rows) == 1 ? $Rows[0] : "no user row with this id"
    );
  }

  protected function getUserDB($User)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      "username" => $User,
    );
    if (MODULE == "hosting") $Data["active"] = "y";
    $SQL  = "select username, clients, gender, title, firstname, lastname, mail, start, avatar";
    if (MODULE == "hosting") $SQL .= " from users        where username = :username and active = :active";
    if (MODULE == "admin")   $SQL .= " from hostingusers where username = :username";
    if (MODULE == "user")    $SQL .= " from adminusers   where username = :username";
    $Rows = $this->execDB($SQL, $Data);

    if (count($Rows) == "1") {
      $Row = $Rows[0];
      $Row["clients"] = (array) json_decode($Row["clients"]);
      sort($Row["clients"]);
      return $Row;
    }
    return [];
  }

  // Schutz vor SQL-Injektion in dynamischen Bezeichnern (18.08.2026).
  //
  // read()/readoptions() reichen "sorts" und die Schluessel von "filters"
  // unveraendert aus dem Anfrage-Body in die Abfrage. Spaltennamen und die
  // Sortierklausel lassen sich - anders als Werte - NICHT als Parameter binden,
  // also wurden sie roh verkettet. Ueber "sorts" liess sich damit eine
  // Unterabfrage einschleusen ("sorts":["(select ...)"]) und blind an Daten
  // anderer Tabellen gelangen, obwohl der Zugriff auf eine Tabelle und einen
  // Client beschraenkt sein soll.
  //
  // Beides sind Bezeichner, keine Werte: sie duerfen ausschliesslich eine
  // Spalte der GERADE gelesenen Tabelle benennen. Genau diese Spalten stehen
  // schon in $Fields (aus information_schema, weiter oben in readDB). Was nicht
  // darin steht, wird verworfen.
  private function validColumn($Name, $Fields)
  {
    $Name = strtolower(trim((string) $Name));
    return isset($Fields[$Name]) ? $Name : "";
  }

  // Sortierliste saeubern: je Eintrag genau eine bekannte Spalte, dahinter
  // hoechstens "asc"/"desc". Alles andere fliegt raus, der Rest bleibt in der
  // gewuenschten Reihenfolge erhalten.
  private function safeSorts($Sorts, $Fields)
  {
    $Out = array();
    foreach ((array) $Sorts as $Sort) {
      if (!is_string($Sort) && !is_numeric($Sort))  continue;
      $Parts = preg_split('/\s+/', trim((string) $Sort));
      $Col   = $this->validColumn($Parts[0], $Fields);
      if (!$Col)  continue;
      $Dir   = (isset($Parts[1]) && strtolower($Parts[1]) === "desc") ? " desc" : "";
      $Out[] = $this->ident($Col) . $Dir;
    }
    return $Out;
  }

  // Mantel um readDBRows(): schaltet die SQL-Erzeugung auf den Treiber der
  // Zielumgebung und stellt sie danach zuverlaessig zurueck.
  //
  // Noetig, weil readDBRows() sein SQL ueber schemaOf()/ident()/isPG() baut -
  // alles am lokalen Treiber haengend. Bei einem Fernzugriff auf eine Umgebung
  // mit anderem Treiber kam sonst PostgreSQL-Syntax bei einer MySQL-Datenbank
  // an: Spalten in doppelten Anfuehrungszeichen (dort Zeichenketten, keine
  // Bezeichner) und "admin.configs" statt "lukida_admin.configs".
  //
  // finally und nicht einfach eine Zeile am Ende: readDBRows() steigt an
  // mehreren Stellen vorzeitig aus, und ein stehengebliebener Schalter wuerde
  // die naechste OERTLICHE Abfrage im falschen Dialekt bauen (20.08.2026).
  protected function readDB($Area, $Client = "", $Start = 0, $Limit = 25, $Filters = [], $Sorts = [], $Group = "", $Env = "", $Special = "", $Search = "")
  {
    $this->beginEnvDriver($Env);
    try {
      return $this->readDBRows($Area, $Client, $Start, $Limit, $Filters, $Sorts, $Group, $Env, $Special, $Search);
    } finally {
      $this->endEnvDriver();
    }
  }

  protected function readDBRows($Area, $Client = "", $Start = 0, $Limit = 25, $Filters = [], $Sorts = [], $Group = "", $Env = "", $Special = "", $Search = "")
  {
    // One-way hashed per-user storage (favors, historys, profiles): single row per client+user,
    // whole payload kept in the "data" JSON column and returned verbatim (not wrapped as a row).
    if ( in_array($Area, array("favors", "historys", "profiles", "settings")) )
    {
      // Diese Bereiche haengen am angemeldeten Nutzer. Ohne gueltiges
      // Benutzer-Token ist $this->user null - dann gibt es keinen Schluessel,
      // und es waere falsch, stattdessen den Hash der leeren Zeichenkette zu
      // verwenden: darunter landeten die Daten ALLER nicht angemeldeten
      // Aufrufer in derselben Zeile (2026-08-11).
      if ( !trim((string) $this->user) )  return ([]);

      $UserHash = $this->getUserHash($this->user);
      $SQL  = "select data from " . $Area . " where client = :client and " . $this->ident("user") . " = :user";
      $Rows = $this->execDB($SQL, array("client" => $Client, "user" => $UserHash), false, $Env);

      if ( !count($Rows) )
      {
        // Migrate a row still keyed under the legacy plain SHA-256 hash to the new
        // HMAC-keyed one, so it keeps being found under its new key from now on.
        $LegacyHash = $this->getHash($this->user);
        $LegacyRows = $this->execDB($SQL, array("client" => $Client, "user" => $LegacyHash), false, $Env);
        if ( count($LegacyRows) == 1 )
        {
          $this->execDB("update " . $Area . " set " . $this->ident("user") . " = :newuser where client = :client and " . $this->ident("user") . " = :olduser",
                         array("newuser" => $UserHash, "client" => $Client, "olduser" => $LegacyHash), false, $Env);
          $Rows = $LegacyRows;
        }
      }

      if ( count($Rows) == 1 && isset($Rows[0]["data"]) )
      {
        return (array) json_decode($Rows[0]["data"], true);
      }
      return ([]);
    }

    // Prepare data

    // Get field types
    $Fields = [];
    $Data   = array(
      "schema" => $this->schemaName(MODULE),
      "area"   => $Area
    );
    $SQL   = "SELECT column_name AS colname, data_type AS coltype FROM information_schema.columns where table_schema = :schema and table_name = :area";
    $Rows  = $this->execDB($SQL, $Data, false, $Env);

    foreach ($Rows as $Row) {
      $Fields[$Row["colname"]] = $Row["coltype"];
    }

    // Wrong area, no data
    if (!count($Rows))  return ([]);

    $ClientsTables = array("adminusers", "users");

    $Data   = [];
    $Where  = false;
    if (MODULE != "hosting" && $Client && !in_array($Area, $ClientsTables)) $Data['client']  = $Client;

    // Determine special filter type (e.g.  "day:2026-07-15" or "month:2026-07" or "year:2026")
    $SpecialType  = "";
    $SpecialValue = "";
    if ($Special && strpos($Special, ":") !== false) {
      list($SpecialType, $SpecialValue) = explode(":", $Special, 2);
    }
    // "monthhours"/"yearhours": wie "day", nur ueber einen ganzen Monat bzw. ein
    // ganzes Jahr - 24 Stundenspalten statt einer Gesamtsumme, dafuer ohne "day"
    // in Auswahl und Gruppierung (2026-08-22).
    //
    // Gebraucht fuer die Sitzungs-Diagramme: dort sind die 24 Spalten bei
    // stat "Count" die Uhrzeit, bei stat "Length" dagegen Laengenklassen
    // ("unter 1 h", "1-2 h", ... siehe batches/phps/BuildSessionStats.php). Eine
    // Tagesreihe wie bei "month"/"year" wuerde genau die Verteilung einebnen,
    // um die es in beiden Diagrammen geht.
    $HoursOverRange = in_array($SpecialType, array("monthhours", "yearhours"));
    $StatsAgg = (in_array($Area,["statslive", "adminstatslive"]) && in_array($SpecialType, array("day", "month", "year", "monthhours", "yearhours")));

    // Day-view stats requests carry the date as a plain "day" filter instead of going
    // through the special:"day" mechanism used by month/year. Route them through the
    // same aggregation so they get summed across all countries too instead of a raw,
    // unaggregated (and since the country filter was removed, now multi-row) result.
    if (!$StatsAgg && in_array($Area, ["statslive", "adminstatslive"]) && isset($Filters["day"]) && !is_array($Filters["day"])) {
      $SpecialType  = "day";
      $SpecialValue = $Filters["day"];
      unset($Filters["day"]);
      $StatsAgg = true;
    }

    if ($StatsAgg) {
      $HourFields = [];
      for ($Hour = 0; $Hour < 24; $Hour++)  $HourFields[] = "hour_" . str_pad($Hour, 2, "0", STR_PAD_LEFT);
      if ($SpecialType == "day" || $HoursOverRange) {
        // Keep hourly resolution: one sum(hour_XX) column per hour instead of a single daily total.
        // Sum across all countries (no "country" in the select/group-by) rather than per country.
        $HourSums = array_map(function ($Field) { return "sum(" . $Field . ") as " . $Field; }, $HourFields);
        // Ueber einen Zeitraum faellt "day" weg - sonst kaeme je Tag eine eigene
        // Zeile zurueck statt der einen Verteilung ueber den ganzen Zeitraum.
        $SQL  = "select category, stat, api" . ($HoursOverRange ? "" : ", day") . ", " . implode(", ", $HourSums) . " from " . $Area;
      } else {
        $SQL  = "select category, stat, api, day, sum(" . implode(" + ", $HourFields) . ") as sum from " . $Area;
      }
    } elseif ($Group == "sum") {
      $SQL  = "select count(*) as count from " . $Area;
    } else {
      $SQL  = "select * from " . $Area;
    }
    if (MODULE != "hosting" && $Client) {
      if (in_array($Area, $ClientsTables)) {
        // "clients" ist eine JSON-Liste ('["HalleLIT", ...]'), gesucht wird per
        // Teilstring. $Client kommt bei MODULE "admin" aus dem Anfrage-Body
        // (adminclient), deshalb als Parameter binden statt einzusetzen
        // (18.08.2026).
        //
        // UNTER POSTGRESQL MUSS DIE SPALTE ERST TEXT WERDEN (29.08.2026).
        // Dort ist "clients" vom Typ jsonb, und darauf gibt es kein LIKE:
        //
        //   operator does not exist: jsonb ~~ unknown
        //
        // execDB() verschluckt den Fehler und liefert eine leere Menge - die
        // Benutzerliste des Admin-Moduls kam dadurch immer als "nodata"
        // zurueck, ohne dass irgendwo etwas von einem Fehler stand. Unter
        // MySQL ist die Spalte Text, dort bleibt es wie bisher.
        $SQL .= " where " . ($this->isPG() ? "clients::text" : "clients") . " like :clientlike";
        $Data["clientlike"] = "%" . $Client . "%";
      } else {
        $SQL .= " where client = :client";
      }
      $Where = true;
    }
    if (count($Filters)) {
      $Count = 0;
      foreach ($Filters as $Flid => $Filt) {
        // Der Filterschluessel ist ein Spaltenname - gegen die Tabelle pruefen,
        // sonst laesst sich auch hier Fremdes einschleusen. Die Werte werden
        // gebunden statt in die Anweisung geschrieben (18.08.2026).
        $Col = $this->validColumn($Flid, $Fields);
        if (!$Col)  continue;
        if (is_array($Filt)) {
          $Ps = array();
          foreach (array_values($Filt) as $Vi => $Val) {
            $P    = "flt_" . $Count . "_" . $Vi;
            $Ps[] = ":" . $P;
            $Data[$P] = $Val;
          }
          if (!count($Ps))  continue;
          $SQL .= (!$Where ? " where " : " and ") . $this->ident($Col) . " in (" . implode(", ", $Ps) . ")";
        } else {
          $P = "flt_" . $Count;
          $SQL .= (!$Where ? " where " : " and ") . $this->ident($Col) . " = :" . $P;
          $Data[$P] = $Filt;
        }
        $Count++;
        $Where = true;
      }
    }
    // Freitextsuche (14.09.2026).
    //
    // Ein Wort, gesucht in ALLEN Textspalten des Bereichs - beim Textbaustein
    // also im Schluessel und in beiden Sprachen, beim Fall in Bezeichnung und
    // Notiz. Wer "vormerk" eingibt, weiss meist nicht, in welcher Spalte es
    // steht; er weiss nur, dass es vorkommt.
    //
    // Die Spalten stammen aus information_schema (siehe $Fields oben), nicht
    // aus der Anfrage - eingeschleust werden kann hier also nichts. Der Wert
    // selbst wird gebunden.
    //
    // Kleinschreibung auf beiden Seiten: PostgreSQL vergleicht LIKE
    // gross/klein genau, MySQL nicht. Ohne lower() faende dieselbe Eingabe je
    // Umgebung etwas anderes.
    $Search = trim((string) $Search);
    if ($Search !== "") {
      $Teile = array();
      foreach ($Fields as $Sp => $Typ) {
        if ($Sp == "client")  continue;
        if (strpos($Typ, "char") === false && strpos($Typ, "text") === false)  continue;
        $Teile[] = "lower(" . $this->ident($Sp) . ") like :suchwort";
      }
      if (count($Teile)) {
        $SQL .= (!$Where ? " where " : " and ") . "(" . implode(" or ", $Teile) . ")";
        // "%" und "_" sind fuer LIKE Platzhalter. Wer eine Signatur mit
        // Unterstrich sucht, meint aber den Unterstrich - deshalb werden sie
        // entschaerft. Der umgekehrte Schraegstrich ist in beiden Datenbanken
        // das Fluchtzeichen und muss zuerst verdoppelt werden.
        $Wort = str_replace(array("\\", "%", "_"), array("\\\\", "\\%", "\\_"), mb_strtolower($Search));
        $Data["suchwort"] = "%" . $Wort . "%";
        $Where = true;
      }
    }

    if ($Special || $StatsAgg) {
      if ($Special == "current") {
        // Zeitfenster: MySQL "NOW() - INTERVAL 5 MINUTE", PostgreSQL
        // "now() - interval \'5 minutes\'"; "end" braucht dort Klammerung.
        $SQL .= (!$Where ? " where " : " and ")
              . ($this->isPG() ? " now() - interval '5 minutes' BETWEEN start AND \"end\""
                               : " NOW() - INTERVAL 5 MINUTE BETWEEN start AND end");
        $Where = true;
      } elseif ($StatsAgg && $SpecialType == "day") {
        $SQL .= (!$Where ? " where " : " and ") . "day = :specialday";
        $Data["specialday"] = $SpecialValue;
        $Where = true;
      } elseif ($StatsAgg && ($SpecialType == "month" || $SpecialType == "monthhours")) {
        // Bereich statt "day like \'2026-08-%\'" (2026-08-22): "day" ist eine
        // DATE-Spalte, und LIKE darauf ist unter PostgreSQL ein harter Fehler
        // ("operator does not exist: date ~~ unknown"), den execDB() als leeres
        // Ergebnis verschluckt - die Monats- und Jahresdiagramme blieben ohne
        // jede Meldung leer. MySQL wandelt still um und lieferte richtig, was
        // den Fehler bis hierher unentdeckt liess. Der Bereichsvergleich gilt
        // auf beiden Systemen und kann obendrein einen Index nutzen.
        $Von = $SpecialValue . "-01";
        $SQL .= (!$Where ? " where " : " and ") . "day >= :specialfrom and day < :specialto";
        $Data["specialfrom"] = $Von;
        $Data["specialto"]   = date("Y-m-d", strtotime($Von . " +1 month"));
        $Where = true;
      } elseif ($StatsAgg && ($SpecialType == "year" || $SpecialType == "yearhours")) {
        $SQL .= (!$Where ? " where " : " and ") . "day >= :specialfrom and day < :specialto";
        $Data["specialfrom"] = $SpecialValue . "-01-01";
        $Data["specialto"]   = ((int) $SpecialValue + 1) . "-01-01";
        $Where = true;
      }
    }

    if ($StatsAgg) {
      $SQL .= " group by category, stat, api" . ($HoursOverRange ? "" : ", day");
    }

    $SafeSorts = $this->safeSorts($Sorts, $Fields);
    if (count($SafeSorts)) {
      $SQL .= " order by " . implode(", ", $SafeSorts);
    } elseif ($StatsAgg && !$HoursOverRange) {
      $SQL .= " order by day";
    } elseif ($HoursOverRange) {
      // Ueber einen Zeitraum ist "day" weder ausgewaehlt noch gruppiert -
      // danach zu sortieren waere unter PostgreSQL ein harter Fehler.
      $SQL .= " order by category, stat, api";
    }

    // Stats aggregation always returns the full day/month/year (max 366 days), ignore pagination
    if ($StatsAgg) {
      $SQL .= " limit 200000";
    } else {
      // MySQL kennt "limit <start>, <anzahl>", PostgreSQL nur
      // "limit <anzahl> offset <start>" (14.08.2026).
      $SQL .= $this->isPG() ? (" limit " . $Limit . " offset " . $Start)
                            : (" limit " . $Start . ", " . $Limit);
    }

    // if ( $StatsAgg ) $this->debug("ALX3", $SpecialType);
    // if ( $StatsAgg ) $this->debug("ALX3", $SQL );
    // if ( $StatsAgg ) $this->debug("ALX3", $Data );

    $Rows = $this->execDB($SQL, $Data, false, $Env);

    // $this->debug("ALX3", $Rows );

    foreach ($Rows as $i => &$Row) {
      foreach ($Fields as $Field => $Type) {
        if (!isset($Rows[$i][$Field]) || !trim($Rows[$i][$Field]))  continue;

        // Convert special datatypes for database storage
        if ($Type == "date") {
          // Date
          $Rows[$i][$Field] = substr($Rows[$i][$Field], 8, 2) . "." . substr($Rows[$i][$Field], 5, 2) . "." . substr($Rows[$i][$Field], 0, 4);
        } elseif ($Type == "datetime") {
          // DateTime
          $Rows[$i][$Field] = substr($Rows[$i][$Field], 8, 2)  . "." . substr($Rows[$i][$Field], 5, 2) . "." . substr($Rows[$i][$Field], 0, 4) . " "
            . substr($Rows[$i][$Field], 11, 5);
        } elseif ($this->isJsonType($Type)) {
          // JSON
          $Rows[$i][$Field] = (array) json_decode($Rows[$i][$Field], true);
        } elseif ($Type == "float") {
          // Float
          $Rows[$i][$Field] = number_format($Rows[$i][$Field], 2, ".", "");
        } elseif ($Type == "password") {
          // Password
          $Rows[$i][$Field] = "";
        } elseif ($Type == "blob") {
          // Blob
          $Rows[$i][$Field] = $this->DecodeArray($this->DecryptString($Rows[$i][$Field]));
        }
      }
    }

    return $Rows;
  }

  protected function updSessionDB($Session = "")
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (!$Session)  $Session = $this->getUID();

    $Data = array(
      "token" => $Session,
      "start" => date("Y-m-d H:i:s"),
      "end"   => date("Y-m-d H:i:s")
    );
    if (MODULE != "hosting") $Data['client']  = $this->client;

    if (MODULE == "hosting") {
      $E = $this->ident("end");
      $SQL  = "insert into sessions (token, start, " . $E . ") values (:token, :start, :end)";
      $SQL .= $this->sqlDup($E . "=:end", array("token"));
    } else {
      $E = $this->ident("end");
      $SQL  = "insert into sessions (client, token, start, " . $E . ") values (:client, :token, :start, :end)";
      $SQL .= $this->sqlDup($E . "=:end", array("token", "client"));
    }
    $this->execDB($SQL, $Data);
  }

  protected function updDeviceDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $User    = (isset($this->posted["login"]))               ? $this->posted["login"]               : "";
    if (!$User) $User = (isset($this->posted["userid"]))   ? $this->posted["userid"]              : "";
    $Token   = (isset($this->posted["tokens"]["device"]))    ? $this->posted["tokens"]["device"]    : "";
    $Serial  = (isset($this->posted["platform"]["serial"]))  ? $this->posted["platform"]["serial"]  : "";
    $Product = (isset($this->posted["platform"]["product"])) ? $this->posted["platform"]["product"] : "";
    if (!$User || !$Token) return;

    $Data = array(
      "user"    => $User,
      "token"   => $Token,
      "product" => $Product,
      "serial"  => $Serial,
      "start"   => date("Y-m-d H:i:s"),
      "end"     => date("Y-m-d H:i:s")
    );
    if (MODULE != "hosting")  $Data['client']  = $this->client;

    if (MODULE == "hosting") {
      $U = $this->ident("user"); $E = $this->ident("end");
      $SQL  = "insert into devices (" . $U . ", token, product, serial, start, " . $E . ") values (:user, :token, :product, :serial, :start, :end)";
      $SQL .= $this->sqlDup("product=:product, serial=:serial, " . $E . "=:end", array("user", "token"));
    } else {
      $U = $this->ident("user"); $E = $this->ident("end");
      $SQL  = "insert into devices (client, " . $U . ", token, product, serial, start, " . $E . ") values (:client, :user, :token, :product, :serial, :start, :end)";
      $SQL .= $this->sqlDup("product=:product, serial=:serial, " . $E . "=:end", array("client", "user", "token"));
    }
    $this->execDB($SQL, $Data);
  }

  protected function updPlatformDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Platform = (isset($this->posted["platform"])    && $this->posted["platform"]    != "") ? $this->posted["platform"]    : "";
    if ($Platform == "") return;

    // Set browser & version statistics
    if (isset($Platform['browser']) && trim($Platform['browser'])) {
      $this->updStatDB("Platform", "browser_" . $Platform['browser']);
    }

    // Set colorDepth
    if (isset($Platform['colordepth']) && trim($Platform['colordepth'])) {
      $this->updStatDB("Platform", "colordepth_" . $Platform['colordepth']);
    }

    if (isset($Platform['cordova']) && trim($Platform['cordova'])) {
      $this->updStatDB("Platform", "cordova_" . $Platform['cordova']);
    }

    // Set cpu
    if (isset($Platform['cpu']) && trim($Platform['cpu'])) {
      $this->updStatDB("Platform", "cpu_" . $Platform['cpu']);
    }

    // Set device statistics
    if (isset($Platform['device']) && trim($Platform['device'])) {
      $this->updStatDB("Platform", "device_" . $Platform['device']);
    }

    // Set engine statistics
    if (isset($Platform['engine']) && trim($Platform['engine'])) {
      $this->updStatDB("Platform", "engine_" . $Platform['engine']);
    }

    // Set os name & version statistics
    if (isset($Platform['os']) && trim($Platform['os'])) {
      $this->updStatDB("Platform", "os_" . $Platform['os']);
    }

    // Set screen resolution statistics
    if (isset($Platform['screen']) && trim($Platform['screen'])) {
      $this->updStatDB("Platform", "screen_" . $Platform['screen']);
    }

    // Set product statistics
    if (isset($Platform['product']) && trim($Platform['product'])) {
      $this->updStatDB("Platform", "product_" . $Platform['product']);
    }
  }

  protected function updFavorsDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (MODULE == "hosting") {
      $U = $this->ident("user");
      $SQL  = "insert into userfavors (" . $U . ", category, categoryid, favorized, updated)";
      $SQL .= " values (:user, :category, :categoryid, :favorized, :updated)";
      $SQL .= $this->sqlDup("updated=:updated", array("user", "category", "categoryid"));
    } else {
      $U = $this->ident("user");
      $SQL  = "insert into userfavors (client, " . $U . ", category, categoryid, favorized, updated)";
      $SQL .= " values (:client, :user, :category, :categoryid, :favorized, :updated)";
      $SQL .= $this->sqlDup("updated=:updated", array("client", "user", "category", "categoryid"));
    }
    $Rows = 0;
    foreach ($this->posted["favors"] as $Favor) {
      $Tmp = explode("_", $Favor);
      if (!array_key_exists($Tmp[0], $this->structure)) continue;
      $Data = array(
        "user"       => $this->user,
        "category"   => $Tmp[0],
        "categoryid" => $Tmp[1],
        "favorized"  => date("Y-m-d H:i:s"),
        "updated"    => date("Y-m-d H:i:s")
      );
      if (MODULE != "hosting")  $Data['client']  = $this->client;
      $Rows += $this->execDB($SQL, $Data);
    }
    return ($Rows >= 0) ? true : false;
  }

  protected function delFavorDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $SQL  = "delete from userfavors where " . $this->ident("user") . "=:user and category=:category and categoryid=:categoryid";
    if (MODULE != "hosting") {
      $SQL .= " and client=:client";
    }

    foreach ($this->posted["favordel"] as $Favor) {
      $Tmp = explode("_", $Favor);
      $Data = array(
        "user"       => $this->user,
        "category"   => $Tmp[0],
        "categoryid" => $Tmp[1]
      );
      if (MODULE != "hosting")  $Data['client']  = $this->client;
      $Rows += $this->execDB($SQL, $Data);
    }
    return ($Rows >= 0) ? true : false;
  }

  protected function updSearchesDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (MODULE == "hosting") {
      $U = $this->ident("user");
      $SQL  = "insert into usersearches (" . $U . ", category, text, searched, amount)";
      $SQL .= " values (:user, :category, :text, :searched, 1)";
      $SQL .= $this->sqlDup("searched=:searched, amount=usersearches.amount+1", array("user", "category", "text"));
    } else {
      $U = $this->ident("user");
      $SQL  = "insert into usersearches (client, " . $U . ", category, text, searched, amount)";
      $SQL .= " values (:client, :user, :category, :text, :searched, 1)";
      $SQL .= $this->sqlDup("searched=:searched, amount=usersearches.amount+1", array("client", "user", "category", "text"));
    }
    $Rows = 0;
    foreach ($this->posted["searches"] as $Search) {
      $Data = array(
        "user"     => $this->user,
        "category" => $Search["area"],
        "text"     => isset($Search["text"]) ? $Search["text"] : "",
        "searched" => date("Y-m-d H:i:s", strtotime($Search["date"]))
      );
      if (MODULE != "hosting")  $Data['client']  = $this->client;
      $Rows += $this->execDB($SQL, $Data);
    }
    return ($Rows >= 0) ? true : false;
  }

  protected function updSettingDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (MODULE == "hosting") {
      $U = $this->ident("user");
      $SQL  = "insert into usersettings (" . $U . ", category, setting, value)";
      $SQL .= " values (:user, :category, :setting, :value)";
      $SQL .= $this->sqlDup("value=:value", array("user", "category", "setting"));
    } else {
      $U = $this->ident("user");
      $SQL  = "insert into usersettings (client, " . $U . ", category, setting, value)";
      $SQL .= " values (:client, :user, :category, :setting, :value)";
      $SQL .= $this->sqlDup("value=:value", array("client", "user", "category", "setting"));
    }
    $Rows = 0;
    foreach ($this->posted["settings"] as $Cat => $Settings) {
      foreach ($Settings as $Setting => $Value) {
        $Data = array(
          "user"     => $this->user,
          "category" => $Cat,
          "setting"  => $Setting,
          "value"    => $Value
        );
        if (MODULE != "hosting")  $Data['client']  = $this->client;
        $Rows += $this->execDB($SQL, $Data);
      }
    }
    return ($Rows >= 0) ? true : false;
  }

  protected function getVersionDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array('client'  => $this->client);
    $SQL  = "select counter, value from versions where client in ('All', :client)";

    $Rows = $this->execDB($SQL, $Data);

    $Data = [];
    foreach ($Rows as $Row) {
      $Data[$Row["counter"]] = $Row["value"];
    }

    if (!isset($Data["Main"]) || !isset($Data["Sub"]) || !isset($Data[$this->client]) || !isset($Data["ValidUntil"])) return [];

    // $Valid = $Data["APITokenValidUntil"];
    // $Valid = DateTime::createFromFormat('d.m.Y H:i', $Data["APITokenValidUntil"])->format('c');

    // Return value
    return (array(
      "version"    => $Data["Main"] . "." . $Data["Sub"] . "." . $Data[$this->client],
      "validuntil" => $Data["ValidUntil"]
    ));
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************

  public function getBatchAreasDB($Module)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      "module" => $Module
    );

    $SQL  = "select area, icon, sort from " . $this->schemaOf("hosting") . ".areas";
    $SQL .= " where module = :module";
    $SQL .= " order by sort";
    $Rows = $this->execDB($SQL, $Data);

    $Areas   = [];
    foreach ($Rows as $Row) {
      $Areas[$Row["area"]] = array(
        "icon" => $Row["icon"],
        "code" => "area." . $Row["area"],
        "sort" => $Row["sort"]
      );
    }

    // Return value
    return $Areas;
  }

  public function getBatchAdminConfigsDB($Client, $Selection = [], $Complete = false)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array('client'  => $Client);
    if (in_array(MODE, array("local", "development")))  $Selection[] = 'development';

    $SQL  = "select category, setting, value from " . $this->schemaOf("hosting") . ".adminconfigs where client = :client";
    if (!$Complete) $SQL .= " and category in ('" . implode("', '", $Selection) . "')";

    $Rows = $this->execDB($SQL, $Data);
    return $Rows;
  }

  public function getBatchAdminLinksDB($Client)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array('client'  => $Client);
    $SQL  = "select shortcut, german, english, link from " . $this->schemaOf("hosting") . ".adminlinks where client = :client";

    // Return value
    return $this->execDB($SQL, $Data);
  }

  public function getBatchAdminTextsDB($Client, $Selection = [], $NotSelection = [], $Complete = false)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $Data = array(
      "client" => $Client,
    );
    // Texte der ANWENDUNG zuerst (03.09.2026, am 12.09.2026 nach appl
    // verlagert): sie gelten fuer alle Clients und stehen deshalb nicht in
    // "texts". Welche das sind und wo sie liegen, weiss nur die Anwendung -
    // base fragt nur nach und mischt sie UNTER die Clienttexte, ein
    // gleichnamiger Clienttext gewinnt also weiterhin. Ohne den Haken bleibt
    // es bei den Clienttexten allein.
    $ApplTexts = method_exists($this, "getApplBatchTextsDB")
              ? (array) $this->getApplBatchTextsDB($Selection, $NotSelection, $Complete)
              : array();

    $SQL  = "SELECT shortcut, german, english FROM " . $this->schemaOf("hosting") . ".admintexts";
    $SQL .= " WHERE client = :client";
    if (!$Complete && count($Selection))    $SQL .= " AND ( shortcut     like '" . implode("' OR shortcut like '", $Selection) . "')";
    if (!$Complete && count($NotSelection)) $SQL .= " AND ( shortcut not like '" . implode("' AND shortcut not like '", $NotSelection) . "')";
    $Rows = $this->ExecDB($SQL, $Data);

    // Return fetched data - Clienttexte ZULETZT, ein gleichnamiger Clienttext
    // gewinnt damit weiterhin ueber den zentralen Bibliothekstext.
    $Texts = $ApplTexts;
    foreach ($Rows as $Row) {
      $Texts[$Row["shortcut"]] = array(
        "german"  => $Row["german"]  !== null && trim($Row["german"])  !== '' ? trim($Row["german"])  : "",
        "english" => $Row["english"] !== null && trim($Row["english"]) !== '' ? trim($Row["english"]) : ""
      );
    }
    return $Texts;
  }

  public function getBatchHostingConfigsDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $SQL  = "select category, setting, value, name from " . $this->schemaOf("hosting") . ".configs";
    return $this->execDB($SQL);
  }

  public function getBatchHostingTextsDB()
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    $SQL  = "SELECT shortcut, german, english FROM " . $this->schemaOf("hosting") . ".texts";
    return $this->execDB($SQL);
  }

  public function getCounterDB($Category, $Counter)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (trim($Counter) == "") return "";

    // Start SQL transaction
    $this->db->begin();

    // Update counter
    $Data = array(
      "category" => $Category,
      "counter"  => $Counter
    );
    if (MODULE != "hosting")  $Data['client']  = $this->client;

    if (MODULE == "hosting") {
      $SQL = "insert into counters (category, counter, value)         values (:category, :counter, 1)" . $this->sqlDup("value = counters.value + 1", array("category", "counter"));
    } else {
      $SQL = "insert into counters (client, category, counter, value) values (:client, :category, :counter, 1)" . $this->sqlDup("value = counters.value + 1", array("client", "category", "counter"));
    }
    $Res = $this->execDB($SQL, $Data);

    // Select value
    $Data = array(
      "category" => $Category,
      "counter"  => $Counter
    );
    if (MODULE != "hosting")  $Data['client']  = $this->client;

    if (MODULE == "hosting") {
      $SQL = "SELECT value from counters where category=:category and counter=:counter";
    } else {
      $SQL = "SELECT value from counters where client=:client and category=:category and counter=:counter";
    }
    $Row = $this->execDB($SQL, $Data);

    // Close SQL transaction
    $this->db->commit();

    // Return value
    return ((count($Row) == 1) ? $Row[0]["value"] : "");
  }

  public function logDB($Report, $Category = "system", $Log = "-", $State = "OK", $Data = "", $Client = null)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if (trim($Report) == "")  return;

    $Log = array(
      "category" => $Category,
      "log"      => $Log,
      "state"    => $State,
      "report"   => $Report,
      "data"     => json_encode($Data, true),
      "created"  => date("Y-m-d H:i:s"),
      "user"     => $this->user,
      "avatar"   => $this->avatar
    );
    // storeRowDB()/removeRowDB() pass the client the record was actually saved
    // under (e.g. the selected AdminClient library) - use that instead of the
    // fixed per-token $this->client so log entries can be found again by
    // read()'s client-scoped filter (system.php read(): $Client = $AdminClient
    // for MODULE=="admin").
    if (MODULE != "hosting")  $Log['client']  = ($Client !== null && $Client !== "") ? $Client : $this->client;

    // Fill logs
    if (MODULE == "hosting") {
      $SQL  = "insert into logs (category, log, state, report, data, created, " . $this->ident("user") . ", avatar)";
      $SQL .= " values (:category, :log, :state, :report, :data, :created, :user, :avatar)";
    } else {
      $SQL  = "insert into logs (client, category, log, state, report, data, created, " . $this->ident("user") . ", avatar)";
      $SQL .= " values (:client, :category, :log, :state, :report, :data, :created, :user, :avatar)";
    }
    $this->execDB($SQL, $Log);
  }

  public function updStatDB($Category, $Stat, $Count = 1)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    if ($Category == "" || $Stat == "") return;

    // Ohne Client nicht schreiben (03.09.2026).
    //
    // Der Konstruktor des user-Moduls liest Levels, Cases, CasesMedia, Links,
    // Collections und BMS - und zaehlt dabei die Cache-Treffer mit. Steht kein
    // Client fest (Aufruf ohne gueltigen Schluessel), scheitert jede dieser
    // sechs Einfuegungen an der NOT-NULL-Bedingung von stats.client: sechs
    // PDO-Fehler je Aufruf im Protokoll, gemessen 30 Stueck an einem halben Tag.
    // Unter MySQL fiel das nie auf, dort ist die Spalte nachgiebiger.
    //
    // Ein Zaehler ohne Client hat ohnehin keine Aussage. Das hosting-Modul
    // schreibt die Spalte gar nicht erst mit und bleibt deshalb aussen vor.
    if (MODULE != "hosting" && trim((string) $this->client) == "") return;

    // Schreibweise (2026-08-15, Vorgabe des Projekteigentuemers): Kategorie und
    // Wert beginnen IMMER mit einem Grossbuchstaben.
    //
    // Bewusst hier und nicht an den rund zwanzig Aufrufstellen je API: so gilt
    // die Regel auch fuer jeden kuenftigen Aufruf, und es gibt keine Stelle,
    // die man beim Nachziehen uebersehen kann. Vorher schrieben etwa
    // base/system.php "login" und appl/user/user.php "Login" - unter MySQL
    // faellt das nicht auf (die Kollation vergleicht gross/klein-blind), unter
    // PostgreSQL werden daraus ZWEI Zeilen in der Auswertung.
    //
    // Ausnahme "Platform": dort sind die Praefixe technische Bezeichner
    // (os_iOS|26.6, browser_Chrome|150, screen_1280|672). "Os_iOS" waere keine
    // Vereinheitlichung, sondern eine Verschlimmbesserung - deshalb bleibt
    // diese eine Kategorie, wie sie ist.
    $Category = ucfirst($Category);
    if ($Category != "Platform")  $Stat = ucfirst($Stat);

    $APIStat = (isset($this->posted["stat"])   && $this->posted["stat"]   != "") ?  preg_replace("/[^a-z0-9]+/", "", strtolower($this->posted["stat"])) : "-";
    $Hour    = date('H');

    // 1. Runde Ohne Country
    $StaData = array(
      "category" => $Category,
      "stat"     => $Stat,
      "day"      => date("Y-m-d"),
      "country"  => substr($this->getCountry2IP(), 0, 50),
      "api"      => substr($APIStat, 0, 50)
    );
    if (MODULE != "hosting")  $StaData['client']  = $this->client;
    if (MODULE == "hosting") {
      $SQL = "insert into stats (category, stat, day, country, api, hour_" . $Hour . ") values (:category, :stat, :day, :country, :api, 1)"
           . $this->sqlDup("hour_" . $Hour . "=stats.hour_" . $Hour . "+" . $Count, array("category", "stat", "day", "country", "api"));
    } else {
      $SQL = "insert into stats (client, category, stat, day, country, api, hour_" . $Hour . ") values (:client, :category, :stat, :day, :country, :api, " . $Count . ")"
           . $this->sqlDup("hour_" . $Hour . "=stats.hour_" . $Hour . "+" . $Count, array("client", "category", "stat", "day", "country", "api"));
    }
    $this->execDB($SQL, $StaData);
  }

  public function storeRegisDB($DataPost)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Checks
    $Regis = (isset($DataPost["regis"]) && trim($DataPost["regis"]) != "") ? trim($DataPost["regis"]) : "";
    if (!$Regis) {
      return (array("status" => -1, "code" => "storeregiserror"));
    }

    // Re-resolve the registration server-side instead of trusting the posted username:
    // only a still-valid, not-yet-expired regisid may activate the (still inactive) user it was issued for.
    $Data = array("regisid" => $Regis);
    // "end" ist unter PostgreSQL ein RESERVIERTES WORT und muss geklammert
    // werden - ident() macht das treiberrichtig. Unkgeklammert ist die
    // Anweisung dort ein harter Syntaxfehler ("syntax error at or near
    // \"end\""), den execDB() verschluckt: es kam eine leere Menge zurueck,
    // und beide Pruefungen unten lasen daraus "Link ungueltig, abgelaufen
    // oder schon benutzt". Damit war JEDER Registrierungslink sofort tot
    // (31.08.2026). Unter MySQL ist "end" nicht reserviert, dort fiel es nie
    // auf.
    $SQL  = "select userid, " . $this->ident("end") . " from registrations where regisid = :regisid";
    $Rows = $this->execDB($SQL, $Data);
    if (count($Rows) != 1 || !isset($Rows[0]["end"]) || strtotime($Rows[0]["end"]) < strtotime("today")) {
      return (array("status" => -1, "code" => "storeregiserror"));
    }
    $UserID = $Rows[0]["userid"];

    $Data = array("id" => $UserID, "active" => "n");
    $SQL  = "select username from users where id = :id and active = :active";
    $Rows = $this->execDB($SQL, $Data);
    if (count($Rows) != 1) {
      return (array("status" => -1, "code" => "storeregiserror"));
    }

    // Update
    $SQL   = "update users set firstname = :firstname, lastname = :lastname, pwhash = :pwhash, avatar = :avatar, gender = :gender, title = :title, active = :active";
    $SQL  .= " where id = :id";
    $Data  = array(
      "firstname" => $DataPost["firstname"],
      "lastname"  => $DataPost["lastname"],
      "pwhash"    => password_hash($DataPost["password"], PASSWORD_DEFAULT),
      "avatar"    => $DataPost["avatar"],
      "gender"    => $DataPost["gender"],
      "title"     => $DataPost["title"],
      "id"        => $UserID,
      "active"    => 'y'
    );

    try {
      // Execute update
      $this->execDB($SQL, $Data);

      // Single-use link: invalidate the registration once it has been consumed
      $this->execDB("delete from registrations where regisid = :regisid", array("regisid" => $Regis));

      return (array(
        "status" => 0,
        "code"   => "storeregissuccessful"
      ));
    } catch (\PDOException $e) {
      $err = $e->errorInfo;
      return (array(
        "status" => -1,
        "code"   => "storeregiserror",
        "more"   => $err
      ));
    }
  }

  public function storeRowDB($Area, $DataPost, $Client, $Replace = false)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // One-way hashed per-user storage (favors, historys, profiles): upsert a single row per
    // client+user, whole posted payload kept verbatim in the "data" JSON column.
    if ( in_array($Area, array("favors", "historys", "profiles", "settings")) )
    {
      // Siehe readDB(): ohne angemeldeten Nutzer gibt es keinen Schluessel.
      // Hier wiegt das schwerer als beim Lesen - geschrieben wuerde in EINE
      // gemeinsame Zeile unter dem Hash der leeren Zeichenkette.
      if ( !trim((string) $this->user) )
      {
        return (array("status" => -1, "code" => "storeerror", "debug" => "no_user_identity"));
      }

      $UserHash = $this->getUserHash($this->user);

      // Migrate a row still keyed under the legacy plain SHA-256 hash to the new
      // HMAC-keyed one first, so the upsert below updates it instead of creating a duplicate.
      $LegacyHash = $this->getHash($this->user);
      // "user" ist unter PostgreSQL ein reserviertes Wort - ident() klammert es.
      $U = $this->ident("user");
      $this->execDB("update " . $Area . " set " . $U . " = :newuser where client = :client and " . $U . " = :olduser",
                     array("newuser" => $UserHash, "client" => $Client, "olduser" => $LegacyHash));

      $Data = array(
                    "client" => $Client,
                    "user"   => $UserHash,
                    "data"   => json_encode($DataPost, true)
                   );

      // Zweiter Upsert neben dem in storeRowDB() weiter unten - dieser hier
      // gilt fuer die nutzergebundenen Bereiche. Bis 14.08.2026 stand hier
      // fest ON DUPLICATE KEY UPDATE; unter PostgreSQL brach die Anweisung ab,
      // execDB() verschluckte den Fehler und der Aufrufer meldete trotzdem
      // Erfolg - die Zeile fehlte einfach.
      if ( $this->isPG() )
      {
        $SQL  = "insert into " . $Area . " (client, " . $U . ", data) values (:client, :user, :data)";
        $SQL .= " on conflict (client, " . $U . ") do update set data = EXCLUDED.data returning id";
      }
      else
      {
        $SQL  = "insert into " . $Area . " (client, user, data) values (:client, :user, :data)";
        $SQL .= $this->sqlDup("data = :data2", array("client", "user"));
        $Data["data2"] = $Data["data"];
      }
      try
      {
        $this->execDB($SQL, $Data);
        return (array(
          "status" => 0,
          "code"   => "storesuccessful"
        ));
      }
      catch(\PDOException $e)
      {
        $err = $e->errorInfo;
        return (array(
          "status" => -1,
          "code"   => "storeerror",
          "more"   => $err
        ));
      }
    }

    // Checks

    // Prepare data
    $ID     = (isset($DataPost["id"])     && trim($DataPost["id"])     != "") ? trim($DataPost["id"])     : "";
    if (!$ID) unset($DataPost["id"]); // Important for MySQL (not try to strore empty value for AI value)
    $FID    = (isset($DataPost["fid"])    && trim($DataPost["fid"])    != "") ? trim($DataPost["fid"])    : "";

    // Get field types
    $Types = [];
    $Data  = array(
      "schema" => $this->schemaName(MODULE),
      "table"  => $Area
    );
    $SQL   = "SELECT column_name AS colname, data_type AS coltype FROM information_schema.columns where table_schema = :schema and table_name = :table";
    $Rows  = $this->execDB($SQL, $Data);
    foreach ($Rows as $Row) {
      $Types[$Row["colname"]] = $Row["coltype"];
    }

    if ($FID) {
      $Data = array(
        "type" => isset($DataPost["type"]) ? $DataPost["type"] : "",
        "fid"  => $FID
      );
      if ($Client)  $Data['client']  = $Client;
      $SQL  = "select id from " . $Area  . " where type = :type and fid = :fid";
      if ($Client)  $SQL .= " and client = :client";
      $Rows = $this->execDB($SQL, $Data);
      if (count($Rows) == 1 && isset($Rows[0]["id"])) {
        $ID = $Rows[0]["id"];
      }
      // ToDo Delete more FIDs if present
    }

    // Read old data
    if ($ID) {
      $Mode = "UPD";
      unset($DataPost["id"]);
      $Data = array(
        "id" => $ID
      );
      if ($Client)  $Data['client']  = $Client;
      $SQL  = "select * from " . $Area  . " where id = :id";
      if ($Client)  $SQL .= " and client = :client";
      $Rows = $this->execDB($SQL, $Data);
      foreach ($Rows[0] as $Field => $Value) {
        if ($Types[$Field] == "float") {
          $Old[$Field] = number_format($Value, 2, ".", "");
        }
        // elseif ( $Types[$Field] == "blob" )
        // {
        //   // Blob
        //   $Old[$Field] = $this->DecodeArray($this->DecryptString($Value));
        // }
        else {
          $Old[$Field] = $Value;
        }
      }
    } else {
      $Mode = "INS";
      $Old  = [];
    }

    // Create log enties after converting special fields like date
    $Log  = [];
    $Data = [];
    if ($Client)  $DataPost['client']  = $Client;
    foreach ($DataPost as $Field => &$Value) {
      // Ein Feld, das die Tabelle gar nicht hat, wird UEBERGANGEN (29.08.2026).
      //
      // Vorher lief der Zugriff auf $Types[$Field] ins Leere und der ganze
      // Aufruf endete mit HTTP 500 und "Undefined array key" - einer weissen
      // Seite statt einer Meldung. Ein Client, der ein zusaetzliches Feld
      // mitschickt (eine aeltere App, ein Tippfehler im Katalog), riss damit
      // das ganze Speichern mit.
      if (!isset($Types[$Field])) {
        unset($DataPost[$Field]);
        continue;
      }

      // *** Pre comparison ***

      // Convert special datatypes for database storage
      //
      // Leeres Datums-/Zeitfeld als NULL, nicht als "" (18.08.2026). Ein leerer
      // String ist unter MySQL geduldet (wird stillschweigend zu 0000-00-00
      // o.ae.), unter PostgreSQL bricht er die GANZE Anweisung ab
      // ("invalid input syntax for type date: \"\""). Genau daran scheiterte
      // das Speichern eines Clients, sobald das Feld "kickoffdate" leer blieb -
      // gemeldet fuer newui, das dabei ebenfalls nicht ankam, weil das eine
      // UPDATE alle Felder zusammen setzt. Die betroffenen Spalten sind
      // nullable, NULL ist also der richtige Leerwert.
      $IstZeitTyp = in_array($Types[$Field], array(
        "date", "datetime", "timestamp", "time",
        "timestamp without time zone", "timestamp with time zone",
        "time without time zone", "time with time zone",
      ));

      // DASSELBE FUER ZAHLEN (29.08.2026).
      //
      // Ein leeres Zahlenfeld kam bisher als "" in die Anweisung, und
      // PostgreSQL bricht daran ab:
      //
      //   invalid input syntax for type integer: ""
      //
      // Und zwar die GANZE Anweisung - ein einziges leeres Feld verhinderte
      // also das Speichern des ganzen Satzes. Aufgefallen an den Terminen: ein
      // neuer Termin ohne Angabe zur Wiederholung laesst "length" leer, und
      // damit liess sich kein einziger neuer Termin anlegen.
      //
      // MySQL schluckt "" stillschweigend (es wird zu 0) - deshalb ist das
      // erst mit PostgreSQL aufgefallen und deshalb steht es hier neben der
      // gleichlautenden Regel fuer Datumsfelder.
      $IstZahlTyp = in_array($Types[$Field], array(
        "integer", "bigint", "smallint", "numeric", "decimal",
        "real", "double precision", "int", "tinyint", "mediumint",
        "float", "double",
      ));

      if (($IstZeitTyp || $IstZahlTyp) && trim((string) $Value) === "") {
        $Value = null;
      } elseif ($Types[$Field] == "date" && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim((string) $Value), $M)) {
        // Date: NUR die deutsche Schreibweise umdrehen (05.09.2026).
        //
        // Hier stand "strlen($Value) == 10" und danach ein blindes
        // Umsortieren der Zeichen. Eine ISO-Angabe ist aber EBENFALLS zehn
        // Zeichen lang, und dann kam Unsinn heraus:
        //
        //   1970-08-03  ->  8-03-0--19
        //   ERROR: invalid input syntax for type date: "8-03-0--19"
        //
        // Das trifft jeden Weg, der einen Satz liest und unveraendert
        // zurueckschreibt: readDB() macht "select *" und liefert eine
        // date-Spalte deshalb so, wie PostgreSQL sie ausgibt - in ISO. Wer
        // den Wert nicht anfasst, schickt ihn genauso zurueck.
        //
        // Die alten Masken kamen durch, weil sie das Datum selbst hin- und
        // herrechneten (PersonDialog.vue tat das bis zum 31.08.2026). Sobald
        // eine Maske die Werte durchreicht - der allgemeine Bereichsbrowser
        // tut das seit jeher -, schlaegt es zu.
        //
        // ISO braucht ohnehin keine Umrechnung: PostgreSQL und MySQL nehmen
        // "JJJJ-MM-TT" unmittelbar an. Also nur noch anfassen, was wirklich
        // "TT.MM.JJJJ" ist.
        $Value = $M[3] . "-" . $M[2] . "-" . $M[1];
      } elseif ($Types[$Field] == "datetime"
                && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})[ T](\d{2}):(\d{2})/', trim((string) $Value), $M)) {
        // DateTime - dieselbe Ueberlegung wie beim Datum darueber.
        $Value = $M[3] . "-" . $M[2] . "-" . $M[1] . " " . $M[4] . ":" . $M[5] . ":00";
      } elseif (in_array($Types[$Field], array("float", "real", "double precision", "numeric", "decimal", "double"))) {
        // Kommazahl (05.09.2026 erweitert).
        //
        // Hier stand nur "float". Diesen Typnamen meldet MySQL - PostgreSQL
        // nennt dieselbe Spalte "real", "double precision" oder "numeric".
        // Unter PostgreSQL lief die Umwandlung also NIE, und eine deutsche
        // Eingabe ging unveraendert an die Datenbank:
        //
        //   ERROR: invalid input syntax for type real: "1,50"
        //
        // Getroffen hat es contracts.costs sowie logbooks.liter sowie
        // logbooks.price - jeden Vertrag und jede Fahrt, die jemand mit Komma
        // eintippt. Dieselbe Luecke wie bei der Regel "leer wird NULL" weiter
        // oben, die deshalb schon laenger alle diese Namen auffuehrt.
        //
        // ZWEI SCHREIBWEISEN, wie beim Datum darueber (05.09.2026).
        //
        // readDB() gibt eine Kommazahl als number_format($v, 2, ".", "")
        // heraus, also "1234.50" mit PUNKT. Wer einen Satz liest und
        // unveraendert zurueckschickt - der allgemeine Bereichsbrowser tut
        // genau das -, schickt also einen Punkt. Die alte Umrechnung warf
        // jeden Punkt weg und machte daraus das HUNDERTFACHE:
        //
        //   1234.50  ->  123450.00
        //
        // Das waere still passiert, ohne Fehlermeldung. Ein falscher Betrag
        // faellt erst auf, wenn jemand ihn liest.
        //
        // Deshalb entscheidet das KOMMA:
        //
        //   enthaelt ein Komma  -> deutsche Eingabe. Punkte sind
        //                          Tausendertrenner und fallen weg, das
        //                          letzte Komma wird zum Dezimalpunkt.
        //                          "1.234,50" -> 1234.50
        //   kein Komma          -> schon Maschinenschreibweise, unangetastet
        //                          uebernehmen. "1234.50" -> 1234.50
        if ( strpos((string) $Value, ",") !== false )
        {
          $Tmp = str_replace(",", "§§", $Value);
          $Tmp = str_replace(array(",", "."), "", $Tmp);
          $Value = str_replace("§§", ".", $Tmp);
        }
        $Value = number_format((float) $Value, 2, ".", "");
      } elseif ($Types[$Field] == "password") {
        // Password
        $Value = strlen(trim($Value)) ? $this->getHash($Value) : "";
      } elseif ($this->isJsonType($Types[$Field])) {
        // JSON/JSONB - NICHT ZWEIMAL VERPACKEN (05.09.2026).
        //
        // Hier stand nur json_encode(). Das ist richtig, solange der Aufrufer
        // ein Feld oder ein Objekt schickt. Die Oberflaeche schickt aber eine
        // ZEICHENKETTE: FeldEingabe.vue schreibt bei einer Mehrfachauswahl
        // JSON.stringify(...) ins Feld, also den Text '["Alex"]'.
        //
        // json_encode() darauf ergibt "[\"Alex\"]" - gueltiges JSON, aber
        // eine ZEICHENKETTE statt einer Liste. In der Spalte steht danach
        // etwas, das nur so aussieht wie vorher:
        //
        //   erwartet   ["Alex", "Susi"]
        //   gespeichert "[\"Alex\", \"Susi\"]"
        //
        // Gemeldet als "Bekannt ueber wird nicht gespeichert". Es wurde
        // gespeichert - nur unbrauchbar. readDB() macht daraus beim Lesen
        // (array) json_decode(...) und damit ein Feld mit EINEM Element, das
        // den ganzen Text enthaelt. Und jsonb_exists(knownby, 'Alex') findet
        // nichts mehr - womit auch die Geburtstagserinnerung leer ausginge.
        //
        // Deshalb: was schon gueltiges JSON ist, geht unveraendert durch.
        // Alles andere wird verpackt, wie bisher.
        $Roh = is_string($Value) ? trim($Value) : null;
        if ( $Roh !== null && $Roh !== "" && json_decode($Roh) !== null )
          $Value = $Roh;                      // kommt schon als JSON
        elseif ( $Roh !== null && strtolower($Roh) === "null" )
          $Value = "null";
        else
          $Value = json_encode($Value, true); // Feld, Objekt oder Freitext
      }

      // var_dump($Old[$Field]);
      // var_dump($Value);

      // Log Data
      if ($Types[$Field] != "password") {
        if (isset($Old[$Field])) {
          // *** Main comparison ***
          if (serialize($Old[$Field]) != serialize($Value)) {
            $Log[$Field]  = array(
              "old" => $Old[$Field],
              "new" => $Value
            );
          }
        } else {
          if ($Value) {
            $Log[$Field]  = array(
              "old" => "-",
              "new" => $Value
            );
          }
        }
      }

      // *** Post comparison ***
      $Data[$Field] = $Value;
    }

    // var_dump($Log);

    if (!count($Data))  return [];

    // Built insert
    if ($ID) {
      // Update
      $SQL   = "update " . $Area  . " set ";
      $First = true;
      foreach ($Data as $Field => $Value) {
        if (!$First) $SQL .= ", ";
        $SQL .= $this->ident($Field) . " = :" . $Field;
        $First = false;
      }
      $SQL .= " where id=:id";
    } elseif ($Replace) {
      // Einfuegen, sonst aktualisieren - treiberabhaengig, siehe sqlUpsert().
      // Das Konfliktziel braucht nur PostgreSQL; es wird aus dem eindeutigen
      // Schluessel der Tabelle gelesen (2026-08-14).
      $SQL = $this->sqlUpsert($Area, array_keys($Data), $this->uniqueKeyOf($Area));
    } else {
      // Insert. Unter PostgreSQL muss die Kennung mit zurueckkommen - es gibt
      // dort kein LAST_INSERT_ID() (2026-08-14).
      $QCols = array();
      foreach (array_keys($Data) as $F)  $QCols[] = $this->ident($F);
      $SQL = "insert into " . $Area  . " (" . implode(", ", $QCols) . ") values (:" . implode(", :", array_keys($Data)) . ")";
      if ($this->isPG())  $SQL .= " returning id";
    }

    if ($ID)  $Data["id"] = $ID;
    try {
      // Execute insert/update
      //
      // Vor dem Schreiben die Fehlermarke leeren und danach pruefen (18.08.2026).
      // execDB() faengt eine PDOException ab, protokolliert sie und gibt [] zurueck -
      // ein UPDATE, das dabei scheitert (z.B. eine ungueltige Datumsangabe), war so
      // vom leeren Ergebnis eines gelungenen Schreibens nicht zu unterscheiden, und
      // storeRowDB() meldete faelschlich Erfolg. Beim INSERT faengt das die
      // newID-Pruefung weiter unten ab, beim UPDATE gab es nichts dergleichen.
      $this->lastDBError = "";
      $WriteResult = $this->execDB($SQL, $Data);
      if (trim((string) $this->lastDBError) !== "") {
        return (array("status" => -1, "code" => "storeerror", "more" => $this->lastDBError));
      }

      if (!$ID) {
        // execDB() returns ["newID" => ...] for a successful "insert " statement, or
        // [] if it swallowed a PDOException internally (e.g. a missing NOT NULL
        // column) - without this check a silently failed insert would still read back
        // LAST_INSERT_ID() from whatever auto-increment row this connection last
        // touched (e.g. sessions, from updSessionDB() in the request constructor),
        // reporting success with a completely unrelated id.
        if (empty($WriteResult["newID"])) {
          return (array(
            "status" => -1,
            "code"   => "storeerror",
            "debug"  => $this->lastDBError // TEMP diagnostics (2026-07), remove once bug is confirmed fixed
          ));
        }
        // Kennung der eingefuegten Zeile. Unter PostgreSQL liefert execDB()
        // sie bereits ueber RETURNING mit - LAST_INSERT_ID() gibt es dort nicht.
        if ($this->isPG()) {
          $ID = $WriteResult["newID"];
        } else {
          $SQL = "SELECT LAST_INSERT_ID() as id";
          $Row = $this->execDB($SQL);
          $ID  = $Row[0]["id"];
        }
      }

      // Log
      if (count($Log)) {
        $this->logDB($Mode, $Area, $ID, "OK", $Log, $Client);
      }

      return (array(
        "status" => 0,
        "id"     => $ID,
        "code"   => "storesuccessful"
      ));
    } catch (\PDOException $e) {
      $err = $e->errorInfo;
      return (array(
        "status" => -1,
        "code"   => "storeerror",
        "more"   => $err
      ));
    }
  }

  public function removeRowDB($Area, $ID, $Client)
  {
    if (APPDEBUG["FKT"]) $this->debug("FKT", __METHOD__);

    // Checks

    // Prepare data
    $Data = array(
      "id" => $ID
    );
    if ($Client)  $Data['client']  = $Client;
    $SQL  = "select * from " . $Area  . " where id = :id";
    if ($Client)  $SQL .= " and client = :client";
    $Rows = $this->execDB($SQL, $Data);

    if (!count($Rows)) {
      return (array(
        "status" => -1,
        "code"   => "nothingtoremove"
      ));
    }

    foreach ($Rows[0] as $Field => $Value) {
      $Old[$Field] = $Value;
    }
    unset($Old["id"]);

    $Log  = [];
    foreach ($Old as $Field => $Value) {
      $Log[$Field]  = array(
        "old" => $Old[$Field],
        "new" => "-"
      );
    }

    // A user who hasn't completed the registration link yet still has a row
    // in "registrations" pointing at this id - remove it first so it can't
    // block (or orphan-reference) the user delete below.
    if ($Area == "users" && MODULE == "hosting") {
      $this->execDB("delete from registrations where userid = :userid", array("userid" => $ID));
    }

    $Data = array("id" => $ID);
    $SQL  = "delete from " . $Area . " where id = :id";
    // execDB() swallows PDOExceptions internally and returns [] on failure (e.g.
    // a foreign-key violation), so this used-to-be try/catch(\PDOException) never
    // actually triggered - a failed delete silently reported "removesuccessful".
    // A clean single-row delete returns the affected row count (1); anything
    // else (0 rows, or [] from a swallowed exception) means it did not happen.
    $Result = $this->execDB($SQL, $Data);
    if ($Result !== 1) {
      return (array(
        "status" => -1,
        "code"   => "removeerror"
      ));
    }

    // Log
    $this->logDB("DEL", $Area, $ID, "OK", $Log, $Client);

    return (array(
      "status" => 1,
      "code"   => "removesuccessful"
    ));
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}
