<?php

class general
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  // Fat Free System
  public $f3;

  // Avaiable Functions
  protected $avail;

  public function __construct($f3)
  {
    // Assign basic settings
    $this->f3    = $f3;

    date_default_timezone_set('Europe/Berlin');

    // Define debug modes
    define('APPDEBUG', array("PATH" => PROJECTDIR . "logs" . DIRECTORY_SEPARATOR,
                             "FKT"  => $this->f3->get("appdebug.fkt"),
                             "PAY"  => $this->f3->get("appdebug.pay"),
                             "LOG"  => $this->f3->get("appdebug.log"),
                             "SYS"  => $this->f3->get("appdebug.sys"),
                             "SQQ"  => $this->f3->get("appdebug.sqq"),
                             "SQP"  => $this->f3->get("appdebug.sqp"),
                             "SQD"  => $this->f3->get("appdebug.sqd"),
                             "OSC"  => $this->f3->get("appdebug.osc"),
                             "OSR"  => $this->f3->get("appdebug.osr"),
                             "SFC"  => $this->f3->get("appdebug.sfc"),
                             "SLQ"  => $this->f3->get("appdebug.slq"),
                             "SLD"  => $this->f3->get("appdebug.sld"),
                             "MCD"  => $this->f3->get("appdebug.mcd"),
                             "BMI"  => $this->f3->get("appdebug.bmi"),
                             "BMO"  => $this->f3->get("appdebug.bmo"),
                             "MED"  => $this->f3->get("appdebug.med"),
                             "ADD"  => $this->f3->get("appdebug.add"),
                             "IDS"  => $this->f3->get("appdebug.ids")
                           ));

    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Define backup dir
    define('ASSETSDIR',        PROJECTDIR . "assets"  . DIRECTORY_SEPARATOR);

    // Define backup dir
    define('BACKUPSDIR',       PROJECTDIR . "backups" . DIRECTORY_SEPARATOR);

    // Define cache dirs
    define('CACHESDIR',        PROJECTDIR . "caches"  . DIRECTORY_SEPARATOR);

    // Zwischenspeicher der Zuordnung Adresse -> Land (17.08.2026).
    //
    // Liegt bewusst NICHT unter dem Tagesverzeichnis und nicht unter einem
    // Client: die Zuordnung haengt weder vom Tag noch vom Client ab. Vorher
    // stand sie in <cache>/<JJJJMMTT>/<Client>/ips/ und wurde damit fuer jeden
    // der 33 Clients und an jedem neuen Tag erneut zentral nachgeschlagen -
    // eine Abfrage, die auf esx-107 die ganze Tabelle liest.
    //
    // "caches" (Mehrzahl) nach der Festlegung des Projekteigentuemers: dieses
    // Verzeichnis wird in eigenen Zyklen je nach Inhalt geleert, waehrend
    // "cache" taeglich geleert wird. Deshalb steht hier fest "caches" und
    // nicht CACHESDIR - das zeigt in API 4 auf "cache".
    define('IPCACHEDIR',       PROJECTDIR . "caches" . DIRECTORY_SEPARATOR . "ips" . DIRECTORY_SEPARATOR);

    // Define library dirs
    define('SYSTEMLIBRARYDIR', PROJECTDIR . "apis/" . API . "/base/_librarys" . DIRECTORY_SEPARATOR);
    define('LIBRARYDIR',       PROJECTDIR . "apis/" . API . "/appl/_librarys" . DIRECTORY_SEPARATOR);

    // Define transfer dir
    define('TRANSFERSDIR',     PROJECTDIR . "transfers" . DIRECTORY_SEPARATOR);

    // Load other librarys
    spl_autoload_register(function($library)
    {
      $ds      = DIRECTORY_SEPARATOR;
      $library = str_replace('\\', $ds, $library);

      // Appl path
      $file    = PROJECTDIR . "lukida-light/" . API . "/appl/_librarys/" . $library . '.php';
      if (!is_readable($file))
      {
        // System path
        $file    = PROJECTDIR . "lukida-light/" . API . "/base/_librarys/" . $library . '.php';
        if (!is_readable($file))  return;
      }
      require_once $file;
    });

    set_error_handler( function ($severity, $message, $file, $line)
    {
      throw new ErrorException($message, $severity, $severity, $file, $line);
    });
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************
  private function getLogFile()
  {
    $Modul = (defined('MODULE')) ? MODULE . "_" : "";
    return APPDEBUG["PATH"] . "Log_" . $Modul . date("Y.m.d") . ".txt";
  }

  // Eine Zeile an die Tagesdatei haengen.
  //
  // Die Datei kann vom Webserver ODER von einem Batch angelegt werden, und das
  // sind verschiedene Systemnutzer. Wer sie als Erster des Tages anlegt, darf
  // die anderen nicht fuer den Rest des Tages aussperren - deshalb einmal beim
  // Anlegen die Rechte oeffnen. Nur dann, nicht bei jeder Zeile.
  private function writeLogFile($Text)
  {
    $Datei = $this->getLogFile();
    $Neu   = !file_exists($Datei);

    file_put_contents($Datei, $Text, FILE_APPEND);

    if ( $Neu )  @chmod($Datei, 0666);
  }

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function print2File($Data)
  {
    $this->writeLogFile(date("Y.m.d H:i:s") . " " . print_r($Data, true) . PHP_EOL);
  }

  // ── Auszeichnung fuer die Oberflaeche ───────────────────────────────────
  //
  // Textwerte der API duerfen Teile FETT oder KURSIV markieren; die Oberflaeche
  // rendert das an jeder Stelle gleich (User2026: components/ApiText.vue).
  //
  //   **fett**        __kursiv__        **__beides__**
  //
  // Bewusst DOPPELTE Zeichen und kein Markdown-artiges einfaches "*":
  // Bibliotheksdaten enthalten laufend einzelne Sternchen, etwa Lebensdaten in
  // Personennamen ("Kuba *1914-1967*"). Ein einfaches Sternchen als Marker
  // haette die kursiv gesetzt. Doppelte Zeichen kommen dort praktisch nicht vor
  // (2026-08-25).
  const MARK_BOLD   = "**";
  const MARK_ITALIC = "__";

  public function markBold($Text)
  {
    $Text = (string) $Text;
    return trim($Text) === "" ? $Text : self::MARK_BOLD . $Text . self::MARK_BOLD;
  }

  public function markItalic($Text)
  {
    $Text = (string) $Text;
    return trim($Text) === "" ? $Text : self::MARK_ITALIC . $Text . self::MARK_ITALIC;
  }

  // Nur den WERT hinter einem Etikett auszeichnen: aus "Signatur: GC 5927"
  // wird "Signatur: **GC 5927**". Steht das Etikett nicht davor, wird der
  // ganze Text ausgezeichnet - so verlangt es die Vorgabe fuer die Signaturen,
  // deren Text je Bibliothek aus einer eigenen Regeldatei kommt und das
  // Etikett nicht zwingend enthaelt.
  public function markValueBold($Text, $Label = "")
  {
    $Text  = (string) $Text;
    if ( trim($Text) === "" )  return $Text;

    $Label = trim((string) $Label);
    if ( $Label !== "" )
    {
      $Sep = $Label . ":";
      if ( strncasecmp($Text, $Sep, strlen($Sep)) === 0 )
      {
        $Rest = substr($Text, strlen($Sep));
        $Wert = ltrim($Rest);
        if ( trim($Wert) === "" )  return $Text;
        // Der Abstand hinter dem Doppelpunkt bleibt, wie er war.
        $Luft = substr($Rest, 0, strlen($Rest) - strlen($Wert));
        return $Sep . $Luft . $this->markBold($Wert);
      }
    }
    return $this->markBold($Text);
  }

  public function format2German($Str)
  {
    return date("d.m.Y", strtotime($Str));
  }

  public function daysLeft($strDate)
  {
    return date_diff(date_create($strDate), date_create())->format('%a');
  }

  public function print2Screen($Data)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    if ( php_sapi_name() != "cli" ) echo "<pre>";
    var_dump($Data);
    if ( php_sapi_name() != "cli" ) echo "</pre>";
  }

  public function debug($Type, $Data, $Full = false)
  {
  	if ( $Full )
  	{
      $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10); // Limit depth and ignore args for performance
      $reducedTrace = [];
      foreach ($trace as $step) {
      	if ( isset($step['function']) && $step['function'] == "call" )	break;
      	if ( isset($step['function']) && $step['function'] == "debug" )	continue;
        $reducedTrace[] = ($step['class']        ?? 'unknown')                  . " "
                        . (str_pad($step['line'] ?? '0', 5, " ", STR_PAD_LEFT)) . ": "
                        . ($step['function']     ?? 'unknown');
      }
      $this->writeLogFile(date("Y.m.d H:i:s") . " " . $Type . "_TRACE " . print_r(array_reverse($reducedTrace), true) . PHP_EOL);
    }

    if ( !is_string($Data)  )
    {
      $this->writeLogFile(date("Y.m.d H:i:s") . " " . $Type . " " . print_r($Data, true) . PHP_EOL);
    }
    else
    {
      $this->print2File($Type . " " . $Data);
    }
  }

  public function isWin()
  {
    return ((strtolower(substr(PHP_OS,0,3)) == "win") ? true : false);
  }

  public function getUID()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  public function writeFile($Files, $Line)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    foreach ( $Files as $File )
    {
      file_put_contents($File, $Line . "\n", FILE_APPEND);
    }
  }

  public function removeFile($Files)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    foreach ( $Files as $File )
    {
      if (file_exists($File)) unlink($File);
    }
  }

  // $Quiet=true haelt die Ausgabe des Kommandos zurueck, statt sie wie system()
  // direkt durchzureichen - BEI FEHLER wird sie trotzdem vollstaendig
  // ausgegeben, bevor die Exception fliegt. Gedacht fuer die sehr gespraechigen
  // Werkzeugaufrufe in den Batch-Skripten (vite, 7z, rsync -v), deren Ausgabe im
  // Erfolgsfall niemand liest, im Fehlerfall aber die einzige Diagnosequelle ist
  // (2026-08-05: Wunsch nach weniger Rauschen im Batch-Log-Fenster).
  public function cmd2System($Command, $Quiet = false)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);

    if ( $Quiet )
    {
      exec($Command . " 2>&1", $Output, $RetVal);
      if ( $RetVal !== 0 && count($Output) )  echo implode(PHP_EOL, $Output) . PHP_EOL;
    }
    else
    {
      system($Command, $RetVal);
    }
    if ( APPDEBUG["SYS"] ) $this->debug("SYS",getcwd() . ": " . $Command);
    // A non-zero exit (e.g. "npx vite build" failing) used to be silently swallowed
    // here - the caller (UpdateClients.php etc.) never checked cmd2System()'s return
    // value, so a failed build just fell through to zipping whatever stale dist/
    // output already existed and reported the client as successful. Throwing makes
    // this catchable by the per-client try/catch in UpdateClients.php's main loop.
    if ( $RetVal !== 0 )
    {
      throw new \RuntimeException("cmd2System failed (exit " . var_export($RetVal, true) . "): " . $Command);
    }
    return $RetVal;
  }


  // Warum das Anlegen scheiterte - und zwar SO, dass man es findet (01.09.2026).
  //
  // Vorher standen hier zwei leere catch-Bloecke. Ein misslungenes mkdir() war
  // damit vollstaendig still, und der Fehler tauchte erst beim naechsten
  // Schreibversuch auf:
  //
  //   file_put_contents(/home/lukida/cache/20260901/HannoverGWLB/ips/…):
  //   Failed to open stream: No such file or directory
  //
  // Das nennt die Folge, nicht die Ursache. Wer das liest, sucht nach einer
  // fehlenden Datei - dabei fehlt das VERZEICHNIS, und der Grund dafuer
  // (meist die Rechte am uebergeordneten Verzeichnis) stand nirgends.
  //
  // Der erste Schreibende legt den Tagesbaum an; laeuft das einmal unter einem
  // anderen Benutzer als die Weboberflaeche, kommt sie fuer den Rest des Tages
  // nicht mehr hinein. Genau diese Auskunft gehoert ins Protokoll.
  private function scheiternGemeldet($Path, $Grund)
  {
    // Den ERSTEN VORHANDENEN Vorfahren suchen. Bei rekursivem mkdir() fehlt
    // der direkte Elternteil fast immer selbst - blockiert hat der tiefste
    // Ordner, den es schon gibt, und nur dessen Rechte sind aufschlussreich.
    $Eltern = dirname($Path);
    while ( $Eltern !== "" && $Eltern !== "/" && $Eltern !== "." && !is_dir($Eltern) )
    {
      $Weiter = dirname($Eltern);
      if ( $Weiter === $Eltern )  break;
      $Eltern = $Weiter;
    }
    $Hinweis = is_dir($Eltern)
      ? ("uebergeordnet: " . $Eltern . " (" . substr(sprintf("%o", @fileperms($Eltern)), -4)
         . ", Eigentuemer " . (function_exists("posix_getpwuid")
             ? (posix_getpwuid(@fileowner($Eltern))["name"] ?? @fileowner($Eltern))
             : @fileowner($Eltern)) . ")")
      : ("kein vorhandener uebergeordneter Ordner gefunden: " . $Eltern);

    $Wer = function_exists("posix_geteuid") && function_exists("posix_getpwuid")
      ? (posix_getpwuid(posix_geteuid())["name"] ?? "?") : "?";

    $this->debug("LOG", "createPath: " . $Grund . " | als Benutzer " . $Wer . " | " . $Hinweis);
  }

  public function createPath($Path)
  {
    if ( !file_exists($Path) )
    {
      try {
        if ( !mkdir($Path, 0777, true) && !is_dir($Path) ) {
          throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: $Path");
        }
        else {
          if ( !$this->isWin() ) system('chmod o+w ' . escapeshellarg($Path));
        }
      } catch (\RuntimeException $e) {
        $this->scheiternGemeldet($Path, $e->getMessage());
      } catch (\ErrorException $e) {
        // mkdir-Warning wurde vom Error-Handler in Exception umgewandelt
        $this->scheiternGemeldet($Path, $e->getMessage());
      }
    }
  }

  public function getHash($Str)
  {
    return hash("sha256", trim((string) $Str));
  }

  public function verifyHash($Str, $Hash)
  {
    return ( hash("sha256", trim($Str)) == $Hash );
  }

  // Deterministic but non-reversible lookup key: keyed hash (HMAC-SHA256) instead of
  // plain SHA-256, so the pseudonymized "user" key in favors/historys/profiles can't be
  // brute-forced/rainbow-tabled without the secret pepper. Same input always yields the
  // same output (needed to re-find the row), but it can't be turned back into the value.
  public function getUserHash($Str)
  {
    // (string) davor: ohne Anmeldung ist $Str null, und trim(null) ist seit PHP 8.1
    // veraltet. Die Aufrufer lehnen einen leeren Nutzer inzwischen ohnehin ab
    // (systemdb.php), das hier ist nur die Absicherung (2026-08-11).
    return hash_hmac("sha256", trim((string) $Str), "lukida-essential-2026");
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************


  /* **OPEN**
  public function PerformOSAction($Type, $Action, $Filter)
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);
    if ( $Type == "" || $Action == "" ) return array("status" => -1,"code" => "missingvalue");
    $Action = PROJECTDIR . "batches" . DIRECTORY_SEPARATOR . $Action . (($this->isWin()) ? ".bat" : ".sh");
    $Action = ( $this->isWin() ) ? "\"" . $Action . "\"" : str_replace(" ", "\ ", $Action);

    if ( isset($Filter["client"]) ) $Action .= " " . $Filter["client"];

    if ( APPDEBUG["OSC"] ) $this->debug("OSC", $Type . ": " . $Action);
    $More = shell_exec($Action);
    if ( $More )
    {
      if ( APPDEBUG["OSR"] ) $this->debug("OSR", "OK");
      return (array(
        "status" => 0,
        "code"   => "performsuccessful",
        "more"   => preg_split('/\r\n|\r|\n/', $More)
      ));
    }
    else
    {
      if ( APPDEBUG["OSR"] ) $this->debug("OSR", "ER: " . $More);
      return (array(
        "status" => -1,
        "code"   => "performerror",
        "more"   => preg_split('/\r\n|\r|\n/', $More)
      ));
    }
  }
  */
}

?>