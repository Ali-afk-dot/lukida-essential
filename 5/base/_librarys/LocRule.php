<?php

// Standortregeln aus der Standorte.txt (impexp) - regulaere Ausdruecke (15.09.2026)
//
// Eine Regel steht als "<ILN><Trenner><Wert>" in locations["exp"][<Gruppe>]
// (Trenner ":" bei loc/sig/aui, "@" bei man). Drei Formen:
//
//   60:HB           genau dieser Wert
//   60:T*           beginnt mit "T" (Stern nur am Ende)
//   60:^T\d{3}      regulaerer Ausdruck - erkennbar am "^" direkt hinter dem Trenner
//
// Ein Ausdruck gilt fuer den Wert HINTER dem Trenner und ist immer am Anfang
// verankert. Ohne "$" am Ende genuegt der Anfang ("^T\d{3}" trifft auch
// "T4567"), mit "$" muss der ganze Wert passen ("^\d{3}$").
//
// Dieselbe Regel wirkt an drei Stellen, die sich einig sein muessen:
//   - Import (admin.php): check() lehnt fehlerhafte Ausdruecke ab
//   - Exemplar -> Standortgruppe (item.php): matches() mit PCRE
//   - Standortfacette und -filter (solr.php): toSolr() als Lucene-Regex
// Deshalb ist nur die Schnittmenge beider Dialekte erlaubt; alles andere
// meldet check() beim Import, statt dass PHP und Solr verschieden zaehlen.
//
// Nicht erlaubt: (?...) ausser (?:...), Rueckbezuege (\1), \b \B \A \z \G \p
// \Q usw., genuegsame/possessive Quantoren (*? +? *+), "^" und "$" mitten im
// Ausdruck. Gross-/Kleinschreibung zaehlt (wie bei den festen Werten).
//
// ";", "|" und "§" trennen in der Standorte.txt die Felder; im Ausdruck
// stehen sie als {semikolon}, {pipe} und {paragraph} (siehe decode()).
//
// Die Klasse liegt in API 4 und API 5 identisch.

class LocRule
{
  private static $Cache = array();

  // Platzhalter der Standorte.txt aufloesen
  public static function decode($Value)
  {
    return str_replace(array("{semikolon}", "{pipe}", "{paragraph}"), array(";", "|", "§"), $Value);
  }

  // Ist der Wert (ohne ILN) ein regulaerer Ausdruck?
  public static function isRegex($Value)
  {
    return substr((string) $Value, 0, 1) === "^";
  }

  // Ist die Regel (mit ILN) ein regulaerer Ausdruck?
  public static function isRegexRule($Rule, $Sep = ":")
  {
    $Pos = strpos((string) $Rule, $Sep);
    return $Pos !== false && substr($Rule, $Pos + 1, 1) === "^";
  }

  // Ausdruck (ohne ILN, mit "^") pruefen - "" wenn in Ordnung, sonst der Grund
  public static function check($Value)
  {
    $P = self::parse($Value);
    return $P["error"];
  }

  // Passt der Exemplarwert "<ILN><Trenner><Wert>" zur Regel? Fuer alle drei Formen.
  public static function matches($Rule, $Value, $Sep = ":")
  {
    if ( self::isRegexRule($Rule, $Sep) )
    {
      $RPos = strpos($Rule,  $Sep);
      $VPos = strpos($Value, $Sep);
      if ( $VPos === false || substr($Rule, 0, $RPos) !== substr($Value, 0, $VPos) )  return false;

      $P = self::parse(substr($Rule, $RPos + 1));
      if ( $P["error"] !== "" )  return false;

      set_error_handler(function() { return true; });
      $Hit = preg_match($P["pcre"], substr($Value, $VPos + 1));
      restore_error_handler();
      return $Hit === 1;
    }

    if ( substr($Rule, -1) == "*" )
    {
      $Pos = strpos($Rule, "*");
      return substr($Rule, 0, $Pos) == substr($Value, 0, $Pos);
    }
    return $Rule == $Value;
  }

  // Regel "<ILN>:^..." als Lucene-Regex fuer ein Solr-Feld wie standort_iln_str_mv,
  // z. B. "60:^T\d{3}" -> "/60:(T\d{3}).*/". Lucene verankert immer am ganzen
  // Wert, daher ".*" statt eines fehlenden "$". Ungueltig -> "" (Aufrufer laesst
  // die Regel weg; der Import laesst sie ohnehin nicht durch).
  public static function toSolr($Rule, $Sep = ":")
  {
    $Pos = strpos($Rule, $Sep);
    if ( $Pos === false )  return "";

    $P = self::parse(substr($Rule, $Pos + 1));
    if ( $P["error"] !== "" )  return "";

    $Head = self::luceneLiteral(substr($Rule, 0, $Pos + 1));
    $Body = $P["lucene"] === "" ? "" : "(" . $P["lucene"] . ")";
    return "/" . $Head . $Body . ($P["end"] ? "" : ".*") . "/";
  }

  // Fester Text innerhalb eines Lucene-Regex
  private static function luceneLiteral($Text)
  {
    return preg_replace('/[^A-Za-z0-9_:\x80-\xFF]/', '\\\\$0', $Text);
  }

  // Zerlegt "^..." in die PCRE- und die Lucene-Fassung
  private static function parse($Value)
  {
    if ( isset(self::$Cache[$Value]) )  return self::$Cache[$Value];

    $Res = array("pcre" => "", "lucene" => "", "end" => false, "error" => "");
    if ( !self::isRegex($Value) )
    {
      $Res["error"] = "beginnt nicht mit ^";
      return self::$Cache[$Value] = $Res;
    }

    $Body     = substr($Value, 1);
    $Len      = strlen($Body);
    $Pcre     = "";
    $Luc      = "";
    $InClass  = false;
    $ClassPos = 0;      // Zeichen seit "[" bzw. "[^" - ein "]" an erster Stelle ist Text
    $Quant    = false;  // voriges Zeichen war ein Quantor
    $Depth    = 0;
    $Special  = "/@#&<>~\" ";  // in Lucene Operatoren (bzw. Trenner), in PCRE Text

    for ( $i = 0; $i < $Len; $i++ )
    {
      $C = $Body[$i];

      if ( $C === "\\" )
      {
        if ( $i + 1 >= $Len )  return self::fail($Value, "endet mit \\");
        $N = $Body[$i + 1];
        if ( ctype_alnum($N) && strpos("dDwWsS", $N) === false )
          return self::fail($Value, "\\" . $N . " ist nicht erlaubt (nur \\d \\D \\w \\W \\s \\S)");
        if ( ctype_alpha($N) )
        {
          // Klassen fuer PCRE ausgeschrieben: mit /u waeren \d und \w dort
          // Unicode-weit ("Ö" ist ein \w), in Lucene nur ASCII.
          $Set = array("d" => "0-9", "w" => "A-Za-z0-9_", "s" => " \\t\\n\\r\\f\\x0B");
          $L   = strtolower($N);
          if ( $InClass && $N !== $L )  return self::fail($Value, "\\" . $N . " ist innerhalb von [...] nicht erlaubt");
          $Pcre .= $InClass ? $Set[$L] : ( "[" . ($N !== $L ? "^" : "") . $Set[$L] . "]" );
        }
        else
        {
          $Pcre .= $C . $N;
        }
        $Luc  .= $C . $N;
        $i++;
        $Quant = false;
        if ( $InClass )  $ClassPos++;
        continue;
      }

      if ( $InClass )
      {
        if ( $C === "]" && $ClassPos > 0 )
        {
          $InClass = false;
          $Pcre   .= $C;
          $Luc    .= $C;
        }
        elseif ( $C === "^" && $ClassPos == 0 && substr($Body, $i - 1, 1) === "[" )
        {
          $Pcre .= $C;
          $Luc  .= $C;
        }
        else
        {
          if ( $C === "[" && substr($Body, $i + 1, 1) === ":" )  return self::fail($Value, "[:...:]-Klassen sind nicht erlaubt");
          $Pcre .= $C;
          $Luc  .= ( $C === "]" || strpos($Special, $C) !== false ) ? "\\" . $C : $C;
          $ClassPos++;
        }
        continue;
      }

      switch ( $C )
      {
        case "[":
          $InClass  = true;
          $ClassPos = 0;
          $Quant    = false;
          $Pcre    .= $C;
          $Luc     .= $C;
          break;

        case "(":
          if ( substr($Body, $i + 1, 1) === "?" )
          {
            if ( substr($Body, $i + 2, 1) !== ":" )  return self::fail($Value, "(?...) ist nicht erlaubt, nur (?:...)");
            $Pcre .= "(?:";
            $Luc  .= "(";
            $i    += 2;
          }
          else
          {
            $Pcre .= $C;
            $Luc  .= $C;
          }
          $Depth++;
          $Quant = false;
          break;

        case ")":
          if ( --$Depth < 0 )  return self::fail($Value, "Klammer ) ohne (");
          $Pcre .= $C;
          $Luc  .= $C;
          $Quant = false;
          break;

        case "*":
        case "+":
        case "?":
          if ( $Quant )  return self::fail($Value, "doppelter Quantor (z. B. *? oder ++) ist nicht erlaubt");
          if ( $Pcre === "" || substr($Pcre, -1) === "(" || substr($Pcre, -1) === "|" )  return self::fail($Value, $C . " ohne Zeichen davor");
          $Pcre .= $C;
          $Luc  .= $C;
          $Quant = true;
          break;

        case "{":
          if ( preg_match('/\G\{(\d+)(,(\d*))?\}/', $Body, $M, 0, $i) )
          {
            if ( $Quant )  return self::fail($Value, "doppelter Quantor ist nicht erlaubt");
            if ( isset($M[3]) && $M[3] !== "" && intval($M[3]) < intval($M[1]) )  return self::fail($Value, $M[0] . ": Obergrenze kleiner als Untergrenze");
            $Pcre .= $M[0];
            $Luc  .= $M[0];
            $i    += strlen($M[0]) - 1;
            $Quant = true;
          }
          else
          {
            // PCRE nimmt ein "{" ohne Quantor stillschweigend als Zeichen - meist
            // ist es ein Tippfehler ("\d{3"). Als Zeichen bitte "\{" schreiben.
            return self::fail($Value, "{ ohne gueltigen Quantor {n}, {n,} oder {n,m} (als Zeichen: \\{)");
          }
          break;

        case "}":
          return self::fail($Value, "} ohne { (als Zeichen: \\})");

        case "$":
          if ( $i != $Len - 1 )  return self::fail($Value, "\$ ist nur am Ende erlaubt");
          $Res["end"] = true;
          break;

        case "^":
          return self::fail($Value, "^ ist nur am Anfang erlaubt");

        default:
          $Pcre .= $C;
          $Luc  .= strpos($Special, $C) !== false ? "\\" . $C : $C;
          $Quant = false;
      }
    }

    if ( $InClass )   return self::fail($Value, "Zeichenklasse [ ohne ]");
    if ( $Depth > 0 ) return self::fail($Value, "Klammer ( ohne )");

    $Res["pcre"]   = "\x01^(?:" . $Pcre . ")" . ($Res["end"] ? "$" : "") . "\x01uD";
    $Res["lucene"] = $Luc;

    // Zum Schluss PCRE selbst fragen - faengt ab, was die Zerlegung durchlaesst
    set_error_handler(function() { return true; });
    $Ok = preg_match($Res["pcre"], "");
    restore_error_handler();
    if ( $Ok === false )  return self::fail($Value, "ungueltiger Ausdruck");

    return self::$Cache[$Value] = $Res;
  }

  private static function fail($Value, $Reason)
  {
    return self::$Cache[$Value] = array("pcre" => "", "lucene" => "", "end" => false, "error" => $Reason);
  }
}
