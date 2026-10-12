<?php

class solr
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************

  // Suchbefehl der Oberflaeche ("titel(Faust)", "author:Goethe") => Solr-Felder.
  // Sonderfaelle mit eigenem Aufbau (von/bis, geo, ppnlink) stehen in
  // getFieldQuery(); unbekannte Befehle gehen unveraendert als Feldname durch.
  private const FIELDS = array(
    "acqdate"            => array("last_changed_iln_str_mv"),
    "erwdatum"           => array("last_changed_iln_str_mv"),
    "author"             => array("author", "author2", "author_os_txtP_mv", "GND_txt_mv"),
    "autor"              => array("author", "author2", "author_os_txtP_mv", "GND_txt_mv"),
    "call"               => array("abrufzeichen_iln_scis_mv"),
    "abruf"              => array("abrufzeichen_iln_scis_mv"),
    "class"              => array("class"),
    "sachgebiet"         => array("class"),
    "classlocal"         => array("notation_local_iln_str_mv"),
    "client"             => array("selectbib_iln_str_mv"),
    "mandant"            => array("selectbib_iln_str_mv"),
    "collection"         => array("collection"),
    "collection_details" => array("collection_details"),
    "details"            => array("collection_details"),
    "package"            => array("collection_details"),
    "paket"              => array("collection_details"),
    "comment"            => array("exemplarkommentar_str_mv"),
    "kommentar"          => array("exemplarkommentar_str_mv"),
    "corporation"        => array("author_corporate", "GND_txt_mv"),
    "koerper"            => array("author_corporate", "GND_txt_mv"),
    "country"            => array("countryofpublication_str_mv"),
    "land"               => array("countryofpublication_str_mv"),
    "doi"                => array("doi_str_mv"),
    "foreignid"          => array("foreignid"),
    "format"             => array("format_phy_str_mv"),
    "format2"            => array("format"),
    "genre"              => array("genre_facet"),
    "id"                 => array("id"),
    "ppn"                => array("id"),
    "isn"                => array("isbn", "issn"),
    "language"           => array("language"),
    "sprache"            => array("language"),
    "langcode"           => array("lang_code"),
    "sprachcode"         => array("lang_code"),
    "location"           => array("standort_iln_str_mv", "standort_weitere_iln_str_mv"),
    "standort"           => array("standort_iln_str_mv", "standort_weitere_iln_str_mv"),
    "new"                => array("selektneu_str_mv"),
    "neu"                => array("selektneu_str_mv"),
    "norm"               => array("normlink_prefix_str_mv"),
    "openaccess"         => array("isfreeaccess_bool"),
    "ppnlink"            => array("ppnlink"),
    "prov"               => array("provenience_txtP_mv"),
    "publisher"          => array("publisher"),
    "series"             => array("series", "series2"),
    "reihe"              => array("series", "series2"),
    "shelfmark"          => array("signature_iln_scis_mv", "signature_weitere_iln_str_mv"),
    "signature"          => array("signature_iln_scis_mv", "signature_weitere_iln_str_mv"),
    "signatur"           => array("signature_iln_scis_mv", "signature_weitere_iln_str_mv"),
    "subject"            => array("topic", "GND_str_mv", "topic_unstemmed"),
    "schlagwort"         => array("topic", "GND_str_mv", "topic_unstemmed"),
    "title"              => array("title_short", "title_full_unstemmed", "title_full", "title", "title_os_txtP_mv", "title_alt"),
    "titel"              => array("title_short", "title_full_unstemmed", "title_full", "title", "title_os_txtP_mv", "title_alt"),
    "topic"              => array("topic"),
    "thema"              => array("topic"),
    "topicfacet"         => array("topic_facet"),
    "university"         => array("hochschulschrift_txt_mv"),
    "hochschule"         => array("hochschulschrift_txt_mv"),
    "year"               => array("publishDateSort"),
    "jahr"               => array("publishDateSort"),
  );

  // Zusatzfelder bei phonetischer Suche
  private const FIELDSSOUND = array(
    "author" => "authorSound",
    "autor"  => "authorSound",
    "title"  => "title_fullSound",
    "titel"  => "title_fullSound",
  );

  // Befehle, deren Werte je ILN als "<ILN>:<Wert>" im Index stehen ...
  // ("sachgebiet" stand hier bis 14.09.2026 mit drin - das Feld "class" hat
  // aber keine ILN-Werte, "sachgebiet(bkl)" fand darum nichts, "class(bkl)" schon.)
  private const ILNCOLON = array("classlocal", "location", "shelfmark", "signatur", "signature", "standort");
  // ... und als "<ILN>@<Wert>"
  private const ILNAT    = array("abruf", "acqdate", "call", "comment", "erwdatum", "kommentar", "neu", "new");

  // Facetten, deren Zahlen die Oberflaeche anfordern kann
  private const COUNTS   = array("types", "formats", "languages", "topicfacets", "classlocals",
                                 "locations", "formatsmain", "genres", "topicrvks", "topicbkls");

  private $add;
  private $CS;
  private $level;
  private $phonetic;
  private $sortacq;
  private $QueryArr;
  private $SkipID;
  // Standort-Facette: Ersatzschluessel der facet.query -> Gruppencode.
  // Warum Ersatzschluessel und nicht der Code selbst: ClausthalTU fuehrt eine
  // Gruppe "--%%--", und ein "%" im {!key=...} zerlegt Solr den lokalen
  // Parameter. Die Nummer ist unverfaenglich, die Zuordnung steht hier.
  private $locfacetkeys;

  public function __construct($CS)
  {
    $this->CS           = $CS;
    $this->add          = false;
    $this->phonetic     = false;
    $this->QueryArr     = array();
    $this->locfacetkeys = array();

    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);
  }

  // *******************************************************************
  // ************* P R I V A T E ********* R E Q U E S T ***************
  // *******************************************************************

  private function searchSolr($URL, $Query = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Ein Handle je PHP-Lauf (14.09.2026): Hauptsuche, Eltern und KI-Vorschlaege
    // gehen alle an findex - so bleibt die TLS-Verbindung offen, statt je
    // Abfrage neu aufgebaut zu werden.
    static $ch = null;
    if ( $ch === null )  $ch = curl_init();

    curl_setopt_array($ch, array(
      CURLOPT_URL            => $URL . "&",
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $Query,     // fertig kodiert aus buildQueryString(), wegen mehrfacher fq nicht http_build_query()
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 60,         // 29.06.2026, 16 Uhr
      // gzip (14.09.2026): 50 Saetze mit fullrecord_marcxml sind 600 kB, gepackt
      // 60 kB - gemessen 0,67 s statt 0,41 s gegen findex.gbv.de.
      CURLOPT_ENCODING       => "",
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
    ));
    $Response = curl_exec($ch);

    if ( $CurlErrNo = curl_errno($ch) )
    {
      // Special treatment for ongoing timeout errors
      if ( $CurlErrNo == 28 )
      {
        file_put_contents(TRANSFERSDIR . "SolrTimeouts.txt", date("Y.m.d H:i:s") . " " . $URL . $Query . PHP_EOL, FILE_APPEND);
      }
      else
      {
        $this->CS->debug("ERR", array("type"      => "Solr Query",
                                      "errorCurl" => $CurlErrNo,
                                      "errorHTTP" => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                                      "url"       => $URL,
                                      "query"     => $Query));
      }
      return array();
    }

    // Solr meldet Fehler als JSON mit HTTP 400/500 - die gehen hier bewusst
    // durch, processSolr() wertet "error" aus. Nur was gar kein JSON ist
    // (Proxy-Fehlerseite, abgebrochene Antwort), wird hier protokolliert.
    $Result = json_decode($Response, true);
    if ( !is_array($Result) )
    {
      $this->CS->debug("ERR", array("type"      => "Solr Response",
                                    "errorHTTP" => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                                    "errorJSON" => json_last_error_msg(),
                                    "url"       => $URL,
                                    "query"     => $Query,
                                    "response"  => substr((string) $Response, 0, 500)));
      return array();
    }
    return $Result;
  }

  // Parameter fuer den POST-Rumpf (14.09.2026).
  //
  // Vorher gingen Schluessel und Werte UNKODIERT hinaus. Jede Eingabe mit "&"
  // oder "+" wurde damit verfaelscht: "Kunst & Kultur" suchte nur nach "kunst"
  // (369.998 statt 11.547 Treffer bei VechtaUB), "C++" nach "c", und ueber "&"
  // liessen sich beliebige Solr-Parameter (rows, fl, qt, ...) einschleusen.
  private function buildQueryString()
  {
    $Parts = array();
    foreach ( $this->QueryArr as $Key => $Values )
    {
      foreach ( (array) $Values as $Value )
      {
        $Parts[] = rawurlencode($Key) . "=" . rawurlencode((string) $Value);
      }
    }
    return "&" . implode("&", $Parts);
  }

  private function addFilter($Filter)
  {
    if ( trim($Filter) !== "" )  $this->QueryArr["fq"][] = $Filter;
  }

  // *******************************************************************
  // ************* P R I V A T E ********* Q U E R Y *******************
  // *******************************************************************

  private function getFieldPhrase($Val, $Fields)
  {
    return implode($Val . " OR ", $Fields) . $Val;
  }

  // Standortregeln als Solr-Bedingung (15.09.2026): wie getFacetQuery(), aber
  // Regeln mit "^" (regulaere Ausdruecke aus der Standorte.txt) gehen als
  // Lucene-Regex hinaus statt maskiert - siehe base/_librarys/LocRule.php.
  // Bewusst NUR fuer die Regeln aus locations["exp"]: die Werte stammen aus dem
  // Import, nie aus der Anfrage.
  private function getLocFacetQuery($Vals, $Fields)
  {
    $Plain = array();
    $Regex = array();
    foreach ( $Vals as $Val )
    {
      if ( LocRule::isRegexRule($Val) )
      {
        $R = LocRule::toSolr($Val);
        if ( $R !== "" )  $Regex[] = $R;
      }
      else
      {
        $Plain[] = $Val;
      }
    }
    if ( !count($Regex) )  return $this->getFacetQuery($Vals, $Fields);

    $Tmp = array();
    foreach ( $Fields as $Field )
    {
      $Or = array();
      if ( count($Plain) )  $Or[] = $Field . ":(" . implode(" OR ", array_map(fn($Val) => $this->CleanVal($Val, true), $Plain)) . ")";
      foreach ( $Regex as $R )  $Or[] = $Field . ":" . $R;
      $Tmp[] = implode(" OR ", $Or);
    }
    return implode(" OR ", $Tmp);
  }
  private function getFacetQuery($Vals, $Fields)
  {
    $Vals = array_map(fn($Val) => $this->CleanVal($Val, true), $Vals);
    $Or   = implode(" OR ", $Vals);

    $Tmp = array();
    foreach ( $Fields as $Field )
    {
      $Tmp[] = $Field . ":(" . $Or . ")";
    }
    return implode(" OR ", $Tmp);
  }

  private function CleanVal($Val, $FormatSpace = false)
  {
    // Kein "%" mehr in der Liste (14.09.2026): Das "%25" war der Behelf fuer
    // den unkodierten POST-Rumpf. Seit buildQueryString() kodiert, kaeme es
    // woertlich als "%25" bei Solr an.
    $From = array( "-",  "+",  ":",  "/",  "(",  ")",  "&&",  "||",  "!",  "{",  "}",  "[",  "]",  "^",  "~");
    $To   = array("\-", "\+", "\:", "\/", "\(", "\)", "\&&", "\||", "\!", "\{", "\}", "\[", "\]", "\^", "\~");
    if ( $FormatSpace )
    {
      $From[] =  " ";
      $To[]   = "\ ";
    }
    $Val = str_replace($From, $To, $Val);

    // Add brackets
    if ( strpos($Val, " ") !== false )  $Val = "(" . $Val . ")";

    return $Val;
  }

  private function splitPhraseValues($Phrase)
  {
    $Parts     = array();
    $Buffer    = "";
    $Depth     = 0;
    $QuoteChar = "";
    $Len       = strlen($Phrase);

    for ($i = 0; $i < $Len; $i++) {
      $Char = $Phrase[$i];

      // Inside a quoted section: keep everything literal until the matching quote
      if ($QuoteChar !== "") {
        $Buffer .= $Char;
        if ($Char == $QuoteChar) $QuoteChar = "";
        continue;
      }

      if ($Char == "\"" || $Char == "'") {
        $QuoteChar = $Char;
      } elseif ($Char == "(") {
        $Depth++;
      } elseif ($Char == ")") {
        if ($Depth > 0) $Depth--;
      } elseif ($Char == "," && $Depth == 0) {
        // Only split on commas outside brackets and quotes
        $Parts[] = $Buffer;
        $Buffer  = "";
        continue;
      }

      $Buffer .= $Char;
    }
    $Parts[] = $Buffer;

    return $Parts;
  }

  private function getFieldQuery($IntCom, $Query)
  {
    $Com  = strtolower(trim($IntCom));
    $ILNs = $this->CS->configs["ilns"] ?? array();

    // Remove unwanted spaces, split by commas when not in brackets or quotes
    $PhVals = array_map("trim", $this->splitPhraseValues(preg_replace('/\s+/', ' ', trim($Query))));

    // Geo: Breite, Laenge, Radius. Die Werte gehen NICHT durch CleanVal(),
    // das haette das Minus negativer Koordinaten maskiert (14.09.2026).
    if ( $Com == "geo" )
    {
      if ( count($PhVals) < 3 || !is_numeric($PhVals[0]) || !is_numeric($PhVals[1]) || !is_numeric($PhVals[2]) )  return "";
      return $this->getGeoFilter($PhVals[0], $PhVals[1], $PhVals[2]);
    }

    $NewVals = array();
    foreach ( $PhVals as $Val )
    {
      // Add ILNs to phrase by :
      if ( in_array($Com, self::ILNCOLON) )
      {
        foreach ( $ILNs as $ILN )
        {
          $NewVals[] = ( substr($Val,0,1) == "\"" )
                     ? "\"" . $ILN . ":" . substr($Val,1)
                     : $this->CleanVal($ILN . ":" . $Val, true);
        }
      }
      // Add ILNs to phrase by @
      elseif ( in_array($Com, self::ILNAT) )
      {
        foreach ( $ILNs as $ILN )
        {
          $NewVals[] = $this->CleanVal($ILN . "@" . $Val, true);
        }
      }
      // Publisher
      elseif ( $Com == "publisher" )
      {
        $NewVals[] = $this->CleanVal($Val, true);
      }
      // Add ISIL to phrase by (DE-627)
      elseif ( $Com == "norm" )
      {
        $NewVals[] = "\(DE\-627\)" . $Val;
        $NewVals[] = "\(DE\-588\)" . $Val;
      }
      // Ensure chars
      else
      {
        $NewVals[] = $this->CleanVal($Val, false);
      }
    }

    switch ( $Com )
    {
      case "from":
      case "von":
        return "hierarchy_sort_str:[" . ($NewVals[0] ?? "*") . " TO *]";
      case "until":
      case "bis":
        return "hierarchy_sort_str:[* TO " . ($NewVals[0] ?? "*") . "]";
    }

    $Phrase = ":(" . implode(" OR ", $NewVals) . ")";
    $Fields = self::FIELDS[$Com] ?? array($IntCom);
    if ( $this->phonetic && isset(self::FIELDSSOUND[$Com]) )  $Fields[] = self::FIELDSSOUND[$Com];

    $FQuery = $this->getFieldPhrase($Phrase, $Fields);
    if ( $Com == "ppnlink" )  $FQuery .= " OR id" . $Phrase;  // Add root id to result

    return $FQuery;
  }

  private function getGeoFilter($Lat, $Lng, $Radius)
  {
    // Vorher "{!geofilt}&sfield=...&pt=...&d=..." - das ging nur, weil der
    // unkodierte Rumpf die "&" als eigene Parameter las. Als lokale Parameter
    // ist es dasselbe (gegen findex geprueft: 13.617 = 13.617 Treffer).
    return "{!geofilt sfield=coordinates_geo2 pt=" . $Lat . "," . $Lng . " d=" . $Radius . "}";
  }

  // Die Standort-Gruppen dieses Clients, fertig fuer je EINE facet.query
  // (09.09.2026). Rueckgabe: Gruppencode => Liste der Standortwerte.
  //
  // Massgeblich ist dieselbe Quelle, aus der auch der FILTER gebaut wird
  // (locations["exp"][<Code>]["loc"], siehe doSearch()) - nur so kann die Zahl
  // an der Facette gar nicht erst von der Trefferzahl nach dem Klick
  // abweichen. Insbesondere werden - wie dort - nur "loc"-Bedingungen
  // ausgewertet; die "sig"/"man"-Zweige sind im Filter seit jeher
  // auskommentiert, und eine Facette, die feiner zaehlt als ihr Filter
  // trennt, waere wieder eine Zahl, die nicht haelt.
  //
  // Gruppen ohne "loc" fallen heraus (heute HamburgHSU "iln60", nur "man"):
  // fuer sie baut der Filter kein fq, ein Klick wuerde also nichts einschraenken.
  private function locationFacetGroups()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( !isset($this->CS->locations["exp"]) || !is_array($this->CS->locations["exp"]) )  return array();

    $Groups = array();
    foreach ( $this->CS->locations["exp"] as $Code => $Conds )
    {
      if ( !isset($Conds["loc"]) || !is_array($Conds["loc"]) || !count($Conds["loc"]) )  continue;
      $Groups[$Code] = $Conds["loc"];
    }

    // ErfurtUB: die fuenf Lesesaal-Gruppen erscheinen als EINE "ls" - so hat es
    // die alte Verdichtung gehalten, und genau so loest der Filter ein
    // angeklicktes "ls" wieder auf (siehe doSearch(), Facette locations).
    if ( $this->CS->client == "ErfurtUB" )
    {
      $Vals  = array();
      foreach ( array("lsz", "ls0", "ls1", "ls2", "ls3") as $M )
      {
        if ( isset($Groups[$M]) )  $Vals = array_merge($Vals, $Groups[$M]);
        unset($Groups[$M]);
      }
      if ( count($Vals) )  $Groups["ls"] = array_values(array_unique($Vals));
    }

    return $Groups;
  }

  private function isFlag($Arr, $Key)
  {
    return isset($Arr[$Key]) && $Arr[$Key];
  }

  private function isConfigYes($Category, $Setting)
  {
    return isset($this->CS->configs[$Category][$Setting]) && $this->CS->configs[$Category][$Setting] == "y";
  }

  private function hasValues($Arr, $Key)
  {
    return isset($Arr[$Key]) && is_array($Arr[$Key]) && count($Arr[$Key]);
  }

  // *******************************************************************
  // ************* P R I V A T E ********* R E S P O N S E *************
  // *******************************************************************

  private function formatClasslocalCode($Shortcut)
  {
    return str_replace(" ", "§", $this->formatClasslocalLabel($Shortcut));
  }

  private function formatClasslocalLabel($Shortcut)
  {
    $Tmp = explode(":", $Shortcut);
    if (count($Tmp) < 2) return "";

    return trim($Tmp[1]);
  }

  private function formatLabelTF($Label)
  {
    return str_replace(",", "{c}", trim($Label));
  }

  // Solr liefert Feld-Facetten als flache Liste [Wert, Anzahl, Wert, Anzahl, ...].
  // Rueckgabe: Liste von [Wert, Anzahl].
  private function facetPairs($Result, $Field)
  {
    $List = $Result["facet_counts"]["facet_fields"][$Field] ?? null;
    if ( !is_array($List) )  return array();

    $Pairs = array();
    for ( $Count = 0; isset($List[$Count]) && isset($List[$Count+1]); $Count += 2 )
    {
      $Pairs[] = array($List[$Count], $List[$Count+1]);
    }
    return $Pairs;
  }

  private function processSolr($Url, $Query)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Result = $this->searchSolr($Url, $Query);

    // fill container
    $Container = isset($Result["response"]["docs"]) ? $Result["response"]["docs"] : array();

    // Not fill / check these values on additional querys like parents
    if ( $this->add )  return $Container;

    // Error
    if ( isset($Result["error"]) )
    {
      $this->CS->results["status"]  = (isset($Result["error"]["code"]))           ? $Result["error"]["code"]           : "0";
      $this->CS->results["message"] = (isset($Result["error"]["msg"]))            ? $Result["error"]["msg"]            : "";
      $this->CS->results["qtime"]   = (isset($Result["responseHeader"]["QTime"])) ? $Result["responseHeader"]["QTime"] : "0";
      return array();
    }

    $this->CS->results["status"]   = "0";
    $this->CS->results["qtime"]    = isset($Result["responseHeader"]["QTime"]) ? str_replace(".", ",", round($Result["responseHeader"]["QTime"] / 1000, 2)) : "";
    $this->CS->results["hits"]     = isset($Result["response"]["numFound"])    ? $Result["response"]["numFound"]    : "0";
    $this->CS->results["hitspack"] = isset($Result["response"]["docs"])        ? count($Result["response"]["docs"]) : "0";
    $this->CS->results["start"]    = isset($Result["response"]["start"])       ? $Result["response"]["start"]       : "0";

    // Remove SkipID
    if ( $this->SkipID )
    {
      $Idx = array_search($this->SkipID, array_column($Container, "id"), true);
      if ( $Idx !== false )
      {
        array_splice($Container, $Idx, 1);
        $this->CS->results["hitspack"] = $this->CS->results["hitspack"] - 1;
        $this->CS->results["hits"]     = $this->CS->results["hits"] - 1;
      }
    }

    // Spell
    $Suggestions = $Result["spellcheck"]["suggestions"] ?? null;
    if ( is_array($Suggestions) )
    {
      for ( $Count = 0; isset($Suggestions[$Count]) && isset($Suggestions[$Count+1]); $Count += 2 )
      {
        $this->CS->results["spell"][$Suggestions[$Count]] = array_column($Suggestions[$Count+1]["suggestion"] ?? array(), "word");
      }
    }

    // Highlight
    if ( isset($Result["highlighting"]) && is_array($Result["highlighting"]) )
    {
      foreach ( $Result["highlighting"] as $HID => $Snip )
      {
        if ( !isset($Snip["fulltext"]) || !is_array($Snip["fulltext"]) )  continue;
        $this->CS->results["highlight"][$HID] = array_reverse(array_values($Snip["fulltext"]));
        if ( isset($this->CS->results["highlight"][$HID][9]) )  $this->CS->results["highlight"][$HID][9] = "+";
      }
    }

    $this->readFacets($Result);

    return $Container;
  }

  private function readFacets($Result)
  {
    $Counts = &$this->CS->results["counts"];

    // Facet type
    foreach ( $this->facetPairs($Result, "remote_bool") as [$Val, $Cnt] )
    {
      $Type          = ( $Val == "true" ) ? "online" : "physical";
      $Counts["types"][$Type] = array("label" => $this->CS->getText("type." . $Type),
                                      "count" => $Cnt);
    }

    // Facet format
    foreach ( $this->facetPairs($Result, "format_phy_str_mv") as [$Val, $Cnt] )
    {
      $Counts["formats"][$Val] = array("label" => $this->CS->getText("format." . $Val),
                                       "count" => $Cnt);
    }

    // Facet languages
    foreach ( $this->facetPairs($Result, "lang_code") as [$Val, $Cnt] )
    {
      $L = $this->CS->getText("lang." . $Val);
      if ( $L )  $Counts["languages"][] = array("code" => $Val, "label" => $L, "count" => $Cnt);
    }

    // Facet topicfacets
    foreach ( $this->facetPairs($Result, "topic_facet") as [$Val, $Cnt] )
    {
      $Counts["topicfacets"][] = array("label" => $this->formatLabelTF($Val), "count" => $Cnt);
    }

    // Facet classlocal
    $Code2Expand = $this->isConfigYes("facet", "classlocalsexpandcode");
    foreach ( $this->CS->configs["ilns"] ?? array() as $ILN )
    {
      foreach ( $this->facetPairs($Result, "locclass_" . $ILN) as [$Val, $Cnt] )
      {
        if ( $Code2Expand )
        {
          $C   = $this->formatClasslocalCode($Val);
          $C2E = $this->CS->getText("classlocal." . strtolower(str_replace("§", "", $C)));
          if ( $C2E )  $Counts["classlocals"][] = array("code" => $C, "label" => $C2E, "count" => $Cnt);
          continue;
        }

        $Label = $this->formatClasslocalLabel($Val);
        // BerlinWiko: Skip alle years besides 1999/2000 and 2023/2024
        if ( $this->CS->client == "BerlinWiko" && $Label != "1999/2000" && !preg_match('/^\d{4}\/\d{2}$/', $Label) )  continue;

        $Counts["classlocals"][] = array("code" => $Label, "label" => $Label, "count" => $Cnt);
      }
    }

    // Facet location - je Gruppe eine facet.query, siehe doSearch()
    if ( count($this->locfacetkeys) && isset($Result["facet_counts"]["facet_queries"])
      && is_array($Result["facet_counts"]["facet_queries"]) )
    {
      foreach ( $this->locfacetkeys as $Key => $Code )
      {
        if ( !isset($Result["facet_counts"]["facet_queries"][$Key]) )  continue;
        $Cnt = (int) $Result["facet_counts"]["facet_queries"][$Key];
        // Leere Gruppen weglassen - das tat vorher facet.mincount=1.
        if ( $Cnt < 1 )  continue;
        $Counts["locations"][$Code] = array("code"  => $Code,
                                            "count" => $Cnt);
      }
    }

    // Facet formatsmain
    foreach ( $this->facetPairs($Result, "format_main_str_mv") as [$Val, $Cnt] )
    {
      $Counts["formatsmain"][] = array("label" => $Val, "count" => $Cnt);
    }

    // Facet genre
    foreach ( $this->facetPairs($Result, "format_details_str_mv") as [$Val, $Cnt] )
    {
      $Counts["genres"][] = array("label" => $Val, "count" => $Cnt);
    }

    // Facet bkl
    foreach ( $this->facetPairs($Result, "topic_bkl") as [$Val, $Cnt] )
    {
      $Code = substr($Val, 4, 5);  // Code 5 chars
      $CRec = $this->CS->getCDBClass("bkl", $Code);
      if ( isset($CRec["description"]) )  $Counts["topicbkls"][] = array("label" => $CRec["description"], "code" => "bkl " . $Code, "count" => $Cnt);
    }

    // Facet rvk
    foreach ( $this->facetPairs($Result, "topic_rvk") as [$Val, $Cnt] )
    {
      $Code = substr($Val, 4);  // Code x chars
      $CRec = $this->CS->getCDBClass("rvk", $Code);
      if ( isset($CRec["description"]) )  $Counts["topicrvks"][] = array("label" => $CRec["description"], "code" => "rvk " . $Code, "count" => $Cnt);
    }

    // Die Referenz legt "counts" als null an, auch wenn keine Facette kam -
    // vorher gab es den Schluessel dann gar nicht.
    unset($Counts);
    if ( array_key_exists("counts", $this->CS->results) && $this->CS->results["counts"] === null )  unset($this->CS->results["counts"]);
  }

  // Standort-Facette: beschriften und ordnen (09.09.2026)
  //
  // Die Verdichtung von Rohwerten auf Gruppen stand frueher hier - sie ist
  // entfallen, weil Solr die Zuordnung jetzt selbst macht (je Gruppe eine
  // facet.query, siehe doSearch()). Damit sind auch der handgeschriebene
  // Platzhaltervergleich und der ErfurtUB-Sonderfall weg; letzterer sitzt
  // jetzt an EINER Stelle, in locationFacetGroups().
  //
  // Was bleibt: die Beschriftung (20.08.2026) und die Reihenfolge.
  private function finishLocationFacet()
  {
    if ( $this->add || !isset($this->CS->results["counts"]["locations"])
      || !is_array($this->CS->results["counts"]["locations"])
      || !count($this->CS->results["counts"]["locations"]) )  return;

    $New = $this->CS->results["counts"]["locations"];

    // Standort-Beschriftung serverseitig aufloesen: Bisher kam nur der Code,
    // und der Client bildete daraus "location.<code>". Wichtig dabei die
    // bestehende Semantik - Standorte OHNE Textbaustein werden nicht
    // angezeigt. Deshalb wird "label" nur gesetzt, wenn es einen Text gibt;
    // der Client blendet Zeilen ohne Beschriftung weiterhin aus.
    foreach ( $New as $K => $Loc )
    {
      if ( !is_array($Loc) || !isset($Loc["code"]) )  continue;
      $Txt = $this->CS->getText("location." . strtolower($Loc["code"]));
      if ( $Txt !== "" )  $New[$K]["label"] = $Txt;
    }

    $Counts = array_column($New, 'count');
    array_multisort($Counts, SORT_DESC, $New);
    $this->CS->results["counts"]["locations"] = $New;
  }

  // Sortierung nach Erwerbungsdatum (nur innerhalb der geladenen Seite).
  //
  // Im Index steht je ILN "<ILN>@TT-MM-JJ". Korrigiert am 14.09.2026:
  //   - Saetze ohne Datum der eigenen ILN fielen vorher ganz aus der
  //     Trefferliste. Jetzt stehen sie am Ende.
  //   - Das frueheste Datum wurde als Text "TT-MM-JJ" verglichen, also nach
  //     dem Tag statt nach dem Jahr.
  //   - ILN "13" griff auch die Eintraege von ILN "131".
  //   - Das Jahr ist zweistellig: "99" sortierte vor "26", also 1999 als
  //     neuer als 2026.
  private function acquisitionDate($Date)
  {
    $Parts = explode("-", trim($Date));
    if ( count($Parts) != 3 )  return "";

    list($Day, $Month, $Year) = $Parts;
    if ( strlen($Year) == 2 )  $Year = ( (int) $Year > (int) date("y") ? "19" : "20" ) . $Year;
    return $Year . "-" . str_pad($Month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($Day, 2, "0", STR_PAD_LEFT);
  }

  private function sortByAcquisition($Data)
  {
    $ILNs    = $this->CS->configs["ilns"] ?? array();
    $ACQSort = array();
    foreach ( $Data as $i => $R )
    {
      if ( !isset($R["last_changed_iln_str_mv"]) || !is_array($R["last_changed_iln_str_mv"]) )  continue;

      foreach ( $R["last_changed_iln_str_mv"] as $S )
      {
        foreach ( $ILNs as $ILN )
        {
          if ( strncmp($ILN . "@", $S, strlen($ILN) + 1) !== 0 )  continue;
          $Date = $this->acquisitionDate(substr($S, strlen($ILN) + 1));
          if ( $Date === "" )  continue;
          if ( !isset($ACQSort[$i]) || $Date < $ACQSort[$i] )  $ACQSort[$i] = $Date;
        }
      }
    }
    arsort($ACQSort, SORT_STRING);

    $Sorted = array();
    foreach ( array_keys($ACQSort) as $i )  $Sorted[] = $Data[$i];
    foreach ( $Data as $i => $R )
    {
      if ( !isset($ACQSort[$i]) )  $Sorted[] = $R;
    }
    return $Sorted;
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  private function doSearch()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Search         = $this->CS->search;
    $SType          = (isset($Search["search"]["type"])   && trim($Search["search"]["type"]) == "lukida") ? "lukida" : "findex";
    $SQuery         = (isset($Search["search"]["query"])  && trim($Search["search"]["query"]))        ? trim($Search["search"]["query"])  : "*";
    $SUrl           = (isset($Search["url"])              && trim($Search["url"]))                    ? trim($Search["url"])              : "";
    $this->phonetic = $this->isFlag($Search, "phonetic");
    $this->SkipID   = (isset($Search["search"]["skipid"]) && trim($Search["search"]["skipid"]))       ? trim($Search["search"]["skipid"]) : "";
    $Facets         = ( isset($Search["facets"]) && is_array($Search["facets"]) ) ? $Search["facets"] : array();
    $Counts         = ( isset($Search["counts"]) && is_array($Search["counts"]) ) ? $Search["counts"] : array();
    $ILNs           = $this->CS->configs["ilns"] ?? array();

    // Enhance query
    if ( $SType == "lukida" )
    {
      // Important for Regex detection
      $SQuery .= " ";
      $BracketsRegex = "/([a-zA-Z]+)(?:\((?P<paren>(?:[^()\"']*|\"[^\"]*\"|'[^']*'|\([^()]*\))*)\)|:(?P<colon>\"[^\"]*\"|'[^']*'|[a-zA-Z0-9_\-*]+))/";

      preg_match_all($BracketsRegex, $SQuery, $Matches, PREG_SET_ORDER);
      $Filters = array();
      foreach ( $Matches as $m )
      {
        // Beide Formen stehen im Regex als Alternative: "feld(wert)" fuellt die
        // Gruppe "paren", "feld:wert" die Gruppe "colon". PHP liefert im Treffer
        // aber nur die Gruppen BIS zur letzten, die teilgenommen hat - greift
        // die Klammerform, fehlt "colon" im Array ueberhaupt. Ein direkter
        // Zugriff war deshalb "Undefined array key \"colon\"" (gemeldet vom
        // Testsystem am 2026-08-22).
        //
        // Ausgeloest hat das jede leere Klammer ("topic()"): "paren" ist dann
        // zwar da, aber leer, und der ternaere Ausdruck griff auf das fehlende
        // "colon" durch.
        //
        // Und der Vergleich ist ausdruecklich auf "" statt empty(): empty()
        // haelt auch den String "0" fuer leer, womit ein Filter mit dem Wert 0
        // ("year(0)") in denselben Zugriff gelaufen waere - und selbst richtig
        // ausgelesen verloren gegangen waere.
        $Paren = isset($m['paren']) ? $m['paren'] : "";
        $Colon = isset($m['colon']) ? $m['colon'] : "";
        $Wert  = ($Paren !== "") ? $Paren : $Colon;

        // Ohne Wert kein Filter. Der Ausdruck faellt weiter unten ohnehin aus
        // der Suchanfrage heraus; einen leeren fq daraus zu bauen wuerde die
        // Trefferliste stillschweigend leeren, statt die sinnlose Eingabe
        // einfach zu uebergehen.
        //
        // Geprueft wird auf den getrimmten Wert - "topic(  )" landet sonst als
        // Leerzeichenkette hier durch und erst in getFieldQuery() im selben
        // leeren Filter. Weitergereicht wird trotzdem der ungetrimmte Wert,
        // damit sich an keinem bisher gueltigen Filter etwas aendert.
        if ( trim($Wert) === "" )  continue;

        // Jeder Befehl wird ein eigener Filter, auch wenn derselbe Befehl
        // mehrfach vorkommt (14.09.2026). Vorher war der Befehl der Schluessel,
        // bei "author(Goethe) author(Schiller)" galt also nur Schiller. Jetzt
        // gilt wie bei verschiedenen Befehlen UND; ODER bleibt das Komma
        // ("author(Goethe, Schiller)").
        $Filters[] = array($m[1], $Wert);
      }
      $SQuery = trim(preg_replace($BracketsRegex, "", $SQuery) ?? $SQuery);

      foreach ( $Filters as [$Command, $Filter] )
      {
        $this->addFilter($this->getFieldQuery($Command, $Filter));
      }
      if ( isset($this->QueryArr["fq"]) )  $this->QueryArr["fq"] = array_values(array_unique($this->QueryArr["fq"]));

      // "up_date" ist der Zeitstempel der letzten Indexaenderung. Er ist die
      // Grundlage des Satz-Caches: nur ein wirklich geaenderter Satz laeuft neu
      // durch den MARC-Treiber (03.09.2026, s. user.php::readCache2Marc).
      $this->QueryArr["fl"][] = "id,fullrecord_marcxml,format_phy_str_mv,remote_bool,collection,collection_details,publishDateSort,last_changed_iln_str_mv,up_date";

      // Target Fields
      if ( $SQuery )
      {
        if ( isset($Facets["target"]) && $Facets["target"] == "fulltext" )
        {
          $QueryFields = array("fulltext^100");
        }
        else
        {
          $QueryFields = array("title_short^770",       "title_shortGer^760",     "title_full_unstemmed^750",   "title_full^740",   "title_fullGer^730",   "title^720",
                               "title_alt^710",         "title_new^700",          "topic_unstemmed^610",        "topic^600",        "author^520",          "author2^510",
                               "author_fuller^500",     "genre_facet^200",        "geographic^210",             "series2^100",      "series^110",          "allfieldsGer^10",
                               "allfields^10",          "allfields_unstemmed^10", "allfields_whitespace^10",    "contents^10",      "description^5",       "fulltext^5",
                               "fulltext_unstemmed^10", "GND_txt_mv^1",           "isbn^1",                     "issn^1");
          if ( $this->phonetic )  array_push($QueryFields, "title_fullSound^690", "authorSound^490", "allfieldsSound^9", "fulltextSound^9");
        }
        $this->QueryArr["qf"][] = implode(" ", $QueryFields);
      }
    }
    $this->QueryArr["q"][] = trim($SQuery) ? trim($SQuery) : "*";

    // Basic
    if ( isset($Search["start"]) )  $this->QueryArr["start"][] = $Search["start"];
    if ( isset($Search["limit"]) )  $this->QueryArr["rows"][]  = $Search["limit"];

    $NewSearch     = isset($Search["start"]) && $Search["start"] == 0;
    $this->level   = isset($Search["level"]) ? $Search["level"] : "";

    // Sort
    $Sort          = isset($Search["sort"]) ? $Search["sort"] : "";
    $this->sortacq = ( $Sort == "acqdesc" );
    $SortField     = ( $this->CS->client == "HalleLIT" && $this->level == "ulbrepos" ) ? "hierarchy_sort_str" : "publishDateSort";
    if     ( $Sort == "yeardesc" )  $SortBy = $SortField . " desc";
    elseif ( $Sort == "yearasc"  )  $SortBy = $SortField . " asc";
    else                            $SortBy = "score desc";
    // "id" als letztes Kriterium (14.09.2026): Bei Gleichstand (gleicher Score,
    // gleiches Jahr, bei "*" sogar alle Treffer) lieferte findex die Treffer
    // bei jeder Anfrage in anderer Reihenfolge - beim Nachladen erschienen
    // Saetze doppelt, andere nie. Kostet gemessen rund 20 ms.
    $this->QueryArr["sort"][] = $SortBy . ",id asc";
    // Das allein reicht bei Textsuchen nicht: Die Replikate eines Shards
    // rechnen leicht verschiedene Scores (gemessen 8878,6 gegen 8947,8 fuer
    // denselben Satz), je nach Replikat kippt also die Reihenfolge. Deshalb
    // geht dieselbe Suche immer an dasselbe Replikat - ueber einen Hash von q,
    // damit sich die Last weiter auf alle Replikate verteilt.
    $this->QueryArr["shards.preference"][] = "replica.base:stable:hash:q";

    // Spellcheck ( Only on NewSearches )
    if ( isset($Search["spell"]) && $NewSearch )
    {
      $this->QueryArr["spellcheck"][] = "true";
      $this->QueryArr["shards.qt"][]  = "/spell";
    }

    // Online / haptic
    if ( isset($Facets["type"]) && in_array($Facets["type"], array("online","physical")) )
    {
      $this->addFilter("remote_bool:" . ( $Facets["type"] == "online" ? "true" : "false" ));
    }

    // Facet time period
    $Range = array("min" => 785, "max" => intval(date("Y")));
    $Min   = ( isset($Facets["yearmin"]) && is_numeric($Facets["yearmin"])
            && $Facets["yearmin"] >= $Range["min"] && $Facets["yearmin"] <= $Range["max"] ) ? $Facets["yearmin"] : $Range["min"];
    $Max   = ( isset($Facets["yearmax"]) && is_numeric($Facets["yearmax"])
            && $Facets["yearmax"] >= $Range["min"] && $Facets["yearmax"] <= $Range["max"] ) ? $Facets["yearmax"] : $Range["max"];
    if ( $Min != $Range["min"] || $Max != $Range["max"] )
    {
      $this->addFilter("publishDateSort:[" . str_pad($Min, 4, "0", STR_PAD_LEFT) . " TO " . str_pad($Max, 4, "0", STR_PAD_LEFT) . "]");
    }

    // Facet formats
    if ( $this->hasValues($Facets, "formats") )    $this->addFilter($this->getFacetQuery($Facets["formats"], array("format_phy_str_mv")));

    // Facet maps (nur Zahlen - die Werte kommen ungeprueft von der Oberflaeche)
    if ( isset($Facets["maps"]["lat"]) && isset($Facets["maps"]["lng"]) && isset($Facets["maps"]["radius"])
      && is_numeric($Facets["maps"]["lat"]) && is_numeric($Facets["maps"]["lng"]) && is_numeric($Facets["maps"]["radius"]) )
    {
      $this->addFilter($this->getGeoFilter($Facets["maps"]["lat"], $Facets["maps"]["lng"], $Facets["maps"]["radius"]));
    }

    // Facet languages
    if ( $this->hasValues($Facets, "languages") )  $this->addFilter($this->getFacetQuery($Facets["languages"], array("lang_code")));

    // Facet topicfacets
    if ( $this->hasValues($Facets, "topicfacets") )
    {
      $this->addFilter($this->getFacetQuery(str_replace("{c}", ",", $Facets["topicfacets"]), array("topic_facet")));
    }

    // Facet classlocals
    if ( $this->hasValues($Facets, "classlocals") )
    {
      $Code2Expand = $this->isConfigYes("facet", "classlocalsexpandcode");
      $Ss = array();
      foreach ( $ILNs as $ILN )
      {
        foreach ( $Facets["classlocals"] as $I )
        {
          $Ss[] = $ILN . ":" . ($Code2Expand ? str_replace("§", " ", $I) : $I);
        }
      }
      $this->addFilter($this->getFacetQuery($Ss, array("notation_local_iln_str_mv")));
    }

    // Facet locations
    $LocWOnline = $this->isConfigYes("facet", "locationswithonlines");
    if ( $this->hasValues($Facets, "locations") )
    {
      $Codes = $Facets["locations"];
      if ( $this->CS->client == "ErfurtUB" && ($ErfK = array_search("ls", $Codes)) !== false )
      {
        unset($Codes[$ErfK]);
        $Codes = array_merge($Codes, array("lsz", "ls0", "ls1", "ls2", "ls3"));
      }

      // Nur "loc"-Bedingungen; "sig"/"man" sind seit jeher nicht im Filter,
      // siehe locationFacetGroups().
      $NewL = array();
      foreach ( $Codes as $Code )
      {
        if ( isset($this->CS->locations["exp"][$Code]["loc"]) && is_array($this->CS->locations["exp"][$Code]["loc"]) )
        {
          $NewL = array_merge($NewL, $this->CS->locations["exp"][$Code]["loc"]);
        }
      }
      if ( count($NewL) )
      {
        $Q = $this->getLocFacetQuery(array_values(array_unique($NewL)), array("standort_iln_str_mv"));
        $this->addFilter($LocWOnline ? "(" . $Q . " OR remote_bool:true)" : $Q);
      }
    }

    // Facet formatsmain
    if ( $this->hasValues($Facets, "formatsmain") )  $this->addFilter($this->getFacetQuery($Facets["formatsmain"], array("format_main_str_mv")));

    // Facet genres
    if ( $this->hasValues($Facets, "genres") )       $this->addFilter($this->getFacetQuery($Facets["genres"], array("format_details_str_mv")));

    // Facet bkl
    if ( $this->hasValues($Facets, "topicbkls") )    $this->addFilter($this->getFacetQuery($Facets["topicbkls"], array("topic_browse")));

    // Facet rvk
    if ( $this->hasValues($Facets, "topicrvks") )    $this->addFilter($this->getFacetQuery($Facets["topicrvks"], array("topic_browse")));

    // Facet links ( Format per entry: Command(Value1, Value2) )
    if ( $this->hasValues($Facets, "links") )
    {
      $LinkGroups = array();
      foreach ( $Facets["links"] as $Link )
      {
        if ( preg_match('/^([a-zA-Z]+)\((.*)\)$/', trim($Link), $LinkMatch) )
        {
          $LinkGroups[$LinkMatch[1]][] = $LinkMatch[2];
        }
      }
      foreach ( $LinkGroups as $LinkCommand => $LinkValues )
      {
        $this->addFilter($this->getFieldQuery($LinkCommand, implode(",", $LinkValues)));
      }
    }

    // Facet numbers ( Only on NewSearches )
    //
    // Korrigiert am 14.09.2026: Die Bedingung lautete "$NewSearch && A || B || ...".
    // Weil && staerker bindet als ||, galt "nur bei neuer Suche" allein fuer die
    // Typ-Facette - beim Nachladen jeder weiteren Seite wurden alle anderen
    // Facetten (inkl. einer facet.query je Standortgruppe) erneut gerechnet.
    // Die Oberflaeche behaelt ihre Facetten, wenn eine Antwort keine bringt.
    $WantCounts = false;
    foreach ( self::COUNTS as $C )
    {
      if ( $this->isFlag($Counts, $C) )  $WantCounts = true;
    }
    if ( $NewSearch && $WantCounts )
    {
      // Start facets
      $this->QueryArr["facet"][]      = "true";
      $this->QueryArr["facet.sort"][] = "count";

      // Normal facets (without iln)
      $Limit = 25;
      if ( $this->isFlag($Counts, "types") )        $this->QueryArr["facet.field"][] = "remote_bool";
      if ( $this->isFlag($Counts, "formats") )      $this->QueryArr["facet.field"][] = "format_phy_str_mv";
      foreach ( array("languages" => "lang_code", "topicfacets" => "topic_facet", "formatsmain" => "format_main_str_mv", "genres" => "format_details_str_mv") as $C => $Field )
      {
        if ( !$this->isFlag($Counts, $C) )  continue;
        $this->QueryArr["facet.field"][]                   = $Field;
        $this->QueryArr["f." . $Field . ".facet.limit"][]  = $Limit;
      }

      // Special facet classlocals of local ilns
      if ( isset($Counts["classlocals"]) )
      {
        foreach ( $ILNs as $ILN )
        {
          $this->QueryArr["facet.field"][] = "{!key=locclass_" . $ILN . " facet.prefix=" . $ILN . ":}notation_local_iln_str_mv";
        }
        $this->QueryArr["f.notation_local_iln_str_mv.facet.mincount"][] = 1;
        $this->QueryArr["f.notation_local_iln_str_mv.facet.limit"][]    = $Limit;
      }

      // Standort-Facette: je Gruppe EINE facet.query (09.09.2026)
      //
      // Vorher: eine Feld-Facette auf standort_iln_str_mv mit facet.prefix je
      // ILN, und die Verdichtung auf die Gruppen lief hinterher in PHP. Das
      // hatte zwei Fehler, die beide erst beim Nachrechnen auffielen
      // (VechtaUB, gemeldet 09.09.2026):
      //
      //   1. Abgeschnitten. Solr lieferte nur die $Limit = 25 haeufigsten
      //      ROHWERTE. VechtaUB hat 75, GoettingenSUB 616. Alles ab Rang 26
      //      kam nie an: bei VechtaUB 1.349 Treffer, die in "Sonderstandort"
      //      und "Magazin" gehoert haetten. Bei einem Client, dessen Gruppe
      //      nur aus seltenen Werten besteht, faellt die Gruppe ganz aus.
      //   2. Doppelt gezaehlt. Die Verdichtung addierte die Zaehler der
      //      Rohwerte; ein Satz mit Bestand in Magazin UND Mediothek zaehlte
      //      zweimal. VechtaUB "Magazin": 198.886 an der Facette, 196.909
      //      Saetze nach dem Klick.
      //
      // Jetzt fragt die Facette dasselbe, was der Filter filtert - gebaut mit
      // derselben Routine (getFacetQuery) aus derselben Quelle
      // (locations["exp"]). Die Zahl KANN damit nicht mehr abweichen.
      //
      // Gemessen gegen findex.gbv.de im Suchraum "all": GoettingenSUB (198
      // Gruppen, groesster Client) 0,98 s statt 1,88 s, VechtaUB 0,54 s statt
      // 2,77 s. Es ist billiger, nicht teurer: eine Feld-Facette muss auf 322
      // Mio. Saetzen alle Terme zaehlen, die facet.query laufen ueber den
      // Filter-Cache. Der Abfragerumpf waechst (17,7 kB bei GoettingenSUB) -
      // searchSolr() schickt ohnehin per POST, das traegt.
      //
      // Was dabei NICHT mehr sichtbar ist: Standortwerte aus dem Index, fuer
      // die es keine Regel gibt. Sie fielen auch vorher schon lautlos heraus
      // (VechtaUB: 11 Werte, 7.724 ueber die Facette unerreichbare Saetze).
      // Dafuer gibt es jetzt others/checks/standorte_ohne_regel.py.
      if ( isset($Counts["locations"]) )
      {
        $this->locfacetkeys = array();

        // Wie im Filter: bei "locationswithonlines" gehoeren die Online-Titel
        // in jede Standortgruppe. Fehlte das hier, waere die Zahl bei den
        // betroffenen Clients sofort wieder eine andere als nach dem Klick.
        foreach ( $this->locationFacetGroups() as $Code => $Vals )
        {
          $Key = "loc" . count($this->locfacetkeys);
          $this->locfacetkeys[$Key] = $Code;

          $Q = $this->getLocFacetQuery($Vals, array("standort_iln_str_mv"));
          if ( $LocWOnline )  $Q = "(" . $Q . " OR remote_bool:true)";

          $this->QueryArr["facet.query"][] = "{!key=" . $Key . "}" . $Q;
        }
      }

      // Special facets topicbkls and topicrvks because of prefix bkl/rvk
      foreach ( array("topicbkls" => "bkl", "topicrvks" => "rvk") as $C => $Prefix )
      {
        if ( !isset($Counts[$C]) )  continue;
        $this->QueryArr["facet.field"][]                   = "{!key=topic_" . $Prefix . " facet.prefix=" . $Prefix . "}topic_browse";
        $this->QueryArr["f.topic_browse.facet.mincount"][] = 1;
        $this->QueryArr["f.topic_browse.facet.limit"][]    = $Limit;
      }
    }

    // Highlighting
    if ( $this->isConfigYes("init", "highlight") )
    {
      $this->QueryArr["hl"][]                  = "true";
      $this->QueryArr["hl.fl"][]               = "fulltext";
      $this->QueryArr["hl.snippets"][]         = "10";
      $this->QueryArr["hl.simple.pre"][]       = "<em>";
      $this->QueryArr["hl.simple.post"][]      = "</em>";
      $this->QueryArr["hl.maxAnalyzedChars"][] = 100000;
    }

    $Q = $this->buildQueryString();

    // Log Solr query
    if ( APPDEBUG["SLQ"] ) $this->CS->debug("SLQ", $SUrl . $Q);

    // Perform Query
    $Data = $this->processSolr($SUrl, $Q);

    // Log ID List
    if ( APPDEBUG["IDS"] )
    {
      $DataIDs = array_column($Data, "id");
      foreach ( is_array(APPDEBUG["IDS"]) ? APPDEBUG["IDS"] : array(trim(APPDEBUG["IDS"])) as $ID )
      {
        if ( ($Key = array_search($ID, $DataIDs)) !== false )
        {
          $this->CS->debug("IDS", $ID);
          $this->CS->debug("SLD", $Data[$Key]);
        }
      }
    }

    // Log Solr data
    if ( APPDEBUG["SLD"] ) $this->CS->debug("SLR", $Data);

    $this->finishLocationFacet();

    if ( $this->sortacq && count($Data) )  $Data = $this->sortByAcquisition($Data);

    return $Data;
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function getSearch($Add)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $this->add = $Add;
    $this->CS->works["solr"] = $this->doSearch();
  }

  public function getProject($URL, $Query, $Project)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $this->CS->works["project"][$Project] = $this->processSolr($URL, $Query);
  }
}

?>
