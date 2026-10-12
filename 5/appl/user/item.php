<?php

class item
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  private $CS;
  private $aps;
  private $awop;
  private $best;
  private $casesetnr;
  private $childid;
  private $comment;
  private $epn;
  private $id;
  private $lightsblue;
  private $lightsgreen;
  private $lightsyellow;
  private $location;
  private $mode;
  private $one;
  private $pid;
  private $signature;
  private $stock;

  public function __construct($CS)
  {

    // Inits which are static over multiple IDs
    // ID-dependend vars: Initialized by public function getItems
    $this->CS        = $CS;
    $this->mode      = (MODE == "production") ? "prod" : "test";
    $this->awop      = (isset($this->CS->configs["item"]["articleswithoutparents"]) && trim($this->CS->configs["item"]["articleswithoutparents"]) == "y")
                       ? true : false;
    $this->aps       = (isset($this->CS->configs["item"]["articleparentshift"])     && trim($this->CS->configs["item"]["articleparentshift"])     == "y")
                       ? true : false;
                       
    // Farben der Quasar-Palette (https://quasar.dev/style/color-palette).
    // Dort hat jede Grundfarbe zusaetzlich Abstufungen mit Zahlensuffix
    // ("light-green-10", "green-5", ...). Frueher standen hier nur einzelne
    // Schreibweisen - "light-green" fehlte z.B., waehrend "light-green-10"
    // aufgefuehrt war. Ein Exemplar mit "light-green" hob die Gruppenfarbe
    // deshalb NICHT auf gruen an, die Gruppe blieb rot (2026-08-08 gemeldet
    // fuer id 175990859). Die Abstufungen werden jetzt in normLight()
    // abgeschnitten, hier stehen nur noch die Grundfarben.
    $this->lightsblue   = array("indigo", "blue", "light-blue", "cyan");
    $this->lightsgreen  = array("green", "light-green", "lime", "teal");
    $this->lightsyellow = array("amber", "orange", "yellow", "deep-orange");

    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);
  }

  // *************************************************
  // ************* P R I V A T E *********************
  // *************************************************

  // ************* T O O L S *************************
  // Articleparentshift may only apply to haptic articles (not online, format
  // "article") - an online article or a non-article (e.g. a haptic journal)
  // must never be shifted onto its parent.

  // Abstufungen der Quasar-Palette auf ihre Grundfarbe zurueckfuehren:
  // "light-green-10" -> "light-green", "green-5" -> "green". Ohne das muesste
  // jede der 14 Abstufungen je Farbe einzeln aufgefuehrt werden.

  // db-Angabe zu einer ILN aus der verschachtelten iln2bms-Struktur.
  private function ilnDB($ILN)
  {
    if ( !$ILN || !isset($this->CS->configs["iln2bms"][$ILN]) )  return "";
    $Map = $this->CS->configs["iln2bms"][$ILN];
    if ( !is_array($Map) )  return "";
    // Flache Altform (nur ein BMS je ILN) weiterhin verstehen.
    if ( isset($Map["db"]) )  return $Map["db"];
    $First = reset($Map);
    return ( is_array($First) && isset($First["db"]) ) ? $First["db"] : "";
  }

  private function normLight($Light)
  {
    return preg_replace('/-\d{1,2}$/', '', strtolower(trim((string) $Light)));
  }


  // Duerfen fuer diesen Satz Elterndaten geerbt werden? (17.08.2026)
  //
  // "Erben" meint hier die alte Mechanik: ein Satz ohne eigene Exemplare
  // uebernimmt die des Elternteils. Sie hat bewusst KEINE Format- oder
  // Besitzkriterien - fuer Baende mehrbaendiger Werke ist sie richtig so.
  //
  // Neu ist allein "!$this->aps": Bibliotheken, die den Artikel-Parentshift
  // einsetzen, sollen keinen Shift durch einen ANDEREN Mechanismus bekommen.
  // Dort gilt ausschliesslich die Regel mit den fuenf Kriterien.
  //
  // Anlass war ErfurtUB (aps = y): die Saetze 587725478, 1605750824 und
  // 333327225 sind Buecher aus fremdem Bestand, zeigten aber die Exemplare
  // ihrer Elternteile - nicht durch den Shift (der verlangt formatcode
  // "article"), sondern durch dieses Erben.
  private function useParent()
  {
    return ( $this->pid && !$this->awop && !$this->aps );
  }

  private function isHapticArticle()
  {
    // Dieselben fuenf Kriterien wie in user.php, wo der Shift selbst entschieden
    // wird - bis 11.08.2026 pruefte diese Stelle nur zwei davon (nicht online,
    // Format "article") und lief damit auch fuer Artikel OHNE Eltern, mit
    // eigenen M980-Exemplaren oder aus fremdem Bestand an. Beide Stellen
    // muessen dasselbe verstehen, sonst weicht die Exemplarausgabe von der
    // Shift-Entscheidung ab.
    //   1. Eltern vorhanden UND geladen
    //   2. nicht online (Solr-Feld remote_bool)
    //   3. keine eigenen Exemplare aus M980 (M981/M856 bewusst nicht)
    //   4. formatcode == "article"
    //   5. eigener Bestand (owner / getOwnILNsOrCols)
    // $this->pid ist rootid des Datensatzes - dieselbe Kennung, die user.php
    // ueber $this->hierarchy["childs"][$ID] verwendet (siehe readCache2Marc).
    // "owner" steht hier noch zur Verfuegung: aus der Ausgabe entfernt wird es
    // erst ganz am Ende von user.php, lange nach der Exemplarverarbeitung.
    $Online = isset($this->one["online"])                ? $this->one["online"]                : false;
    $Format = isset($this->one["formatcode"])            ? $this->one["formatcode"]            : "";
    $Phys   = isset($this->one["itemcount"]["physical"]) ? $this->one["itemcount"]["physical"] : 0;
    $Owner  = isset($this->one["owner"])                 ? $this->one["owner"]                 : false;

    return ( $this->pid
          && isset($this->CS->works["add"][$this->pid])
          && $Owner
          && !$Online
          && !$Phys
          && $Format == "article" );
  }

  private function checkStrInWildArr($Str, $Arr)
  {
    // $this->CS->debug("ALXI1", $Str);
    // $this->CS->debug("ALXI2", $Arr);
    // Schleife Standord-Gruppen
    foreach ( $Arr as $One )
    {
      if ( substr($One, -1) == "*" )
      {
        $Pos = strpos($One, "*");
        if ( substr($One, 0, $Pos) == substr($Str, 0, $Pos) ) return true;
      }
      else
      {
        if ( $One == $Str )  return true;
      }
    }
    return false;
  }

  private function checkArrInWildArr($Arr1, $Arr2)
  {
    // $this->CS->debug("ALXI1", $Arr1);
    // $this->CS->debug("ALXI2", $Arr2);
    // Schleife Standord-Gruppen
    foreach ( $Arr1 as $Str )
    {
      foreach ( $Arr2 as $One )
      {
        if ( substr($One, -1) == "*" )
        {
          $Pos = strpos($One, "*");
          if ( substr($One, 0, $Pos) == substr($Str, 0, $Pos) ) return true;
        }
        else
        {
          if ( $One == $Str )  return true;
        }
      }
    }
    // $this->CS->debug("ALXI2E", "Ende");
    return false;
  }

  // "Es liegt eine PAIA-Antwort vor" - fuer p.paia und p.nopaia (09.09.2026).
  //
  // Gemeldet an MagdeburgStendalHS 369453840: der Fall "Interne Leihe SDL
  // (ohne Kosten)" (casesmedia, mit "p.paia") erschien nie, obwohl der Satz
  // eine PAIA-Antwort hat - und "Fernleihe mit Kosten" ("p.nopaia") kam auch
  // dann durch, wenn eine vorlag.
  //
  // Grund: die beiden Bausteine lasen $DAIA, und $DAIA entsteht aus
  // $Item["id"] - der PAIA-Kennung EINER Exemplarzeile. Die Medienfaelle
  // (casesmedia) werden aber ohne Exemplar geprueft: setitems() ruft getExps()
  // dort mit einem LEEREN $Item auf, weil sie fuer den ganzen Satz gelten und
  // nicht fuer eine Zeile. Damit war $DAIA auf der Medienebene immer false -
  // "p.paia" konnte nie zutreffen, "p.nopaia" immer.
  //
  // Auf der Exemplarebene bleibt alles wie bisher. Auf der Medienebene wird
  // jetzt der Satz gefragt: hat IRGENDEIN Exemplar eine PAIA-Kennung? Dieselbe
  // Quelle und dasselbe Muster benutzt "p.nobandlist" seit jeher fuer seinen
  // Medienzweig ($this->CS->paias[<Satz>]).
  private function hasPaia(&$Item)
  {
    // Exemplarebene: die Zeile selbst traegt eine PAIA-Kennung.
    if ( count($Item) )  return ( isset($Item["id"]) && trim($Item["id"]) != "" );

    // Medienebene: am Satz fragen.
    if ( !isset($this->CS->paias[$this->id]) || !is_array($this->CS->paias[$this->id]) )  return false;
    foreach ( $this->CS->paias[$this->id] as $PI )
    {
      if ( isset($PI["id"]) && trim($PI["id"]) != "" )  return true;
    }
    return false;
  }

  private function isLimitation($Item, $Limit)
  {
    return
       ( (isset($Item["loanitems"][0]["limitation"])         && is_array($Item["loanitems"][0]["limitation"])
       && count($Item["loanitems"][0]["limitation"])         && in_array($Limit, $Item["loanitems"][0]["limitation"]) )
      || (isset($Item["presentationitems"][0]["limitation"]) && is_array($Item["presentationitems"][0]["limitation"])
       && count($Item["presentationitems"][0]["limitation"]) && in_array($Limit, $Item["presentationitems"][0]["limitation"]) )
      || (isset($Item["interloanitems"][0]["limitation"])    && is_array($Item["interloanitems"][0]["limitation"])
       && count($Item["interloanitems"][0]["limitation"])    && in_array($Limit, $Item["interloanitems"][0]["limitation"]) ) ) ? true : false;
  }

  private function setLog($Group, $Message)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $this->one["logs"][] = str_pad($Group, 10, " ") . ": " . $Message;
  }

  private function getArrReducedWildArr($RedArray, $MainArray)
  {
    foreach ( $MainArray as $MInd => $Str )
    {
      foreach ( $RedArray as $One )
      {
        if ( substr($One, -1) == "*" )
        {
          $Pos = strpos($One, "*");
          if ( substr($One, 0, $Pos) == substr($Str, 0, $Pos) ) unset($MainArray[$MInd]);
        }
        else
        {
          if ( $One == $Str )  unset($MainArray[$MInd]);
        }
      }
    }
    return $MainArray;
  }

  private function getMarcSubFieldArray($Field, $Subfield)
  {
    $Vals = array();
    if ( isset($this->one["marc"][$Field]) && is_array($this->one["marc"][$Field]) )
    {
      foreach ($this->one["marc"][$Field] as $Rec )
      {
        if ( array_key_exists($Subfield, $Rec) && is_array($Rec[$Subfield]) )
        {
          foreach ($Rec[$Subfield] as $Val)
          {
            if ( !in_array($Val, $Vals) )  $Vals[] = $Val;
          }
        }
      }
    }
    return $Vals;
  }

  private function getMarcSubFieldsArray($Field, $Subfields = array())
  {
    $Recs = array();
    if ( isset($this->one["marc"][$Field]) && is_array($this->one["marc"][$Field]) )
    {
      foreach ($this->one["marc"][$Field] as $Rec )
      {
        $Vals = array();
        foreach ($Rec as $Subf => $SubRec)
        {
          if ( in_array($Subf, $Subfields) )
          {
            if (is_array($SubRec) )
            {
              foreach ($SubRec as $SubV)
              {
                $Vals[$Subf][] = $SubV;
              }
            }
            else
            {
              $Vals[$Subf][] = $SubRec;
            }
          }
        }
        if ( count($Vals) ) $Recs[] = $Vals;
      }
    }
    return $Recs;
  }

  private function addArr2Arr(&$Arr1, $Arr2)
  {
    $Arr1 = array_unique(array_merge($Arr1, $Arr2));
  }

  private function getPage()
  {
    // $this->CS->debug("ALX333", $this->one["marc"]["M773"]);
    $Recs = $this->getMarcSubFieldsArray("M773", array("I1", "g"));
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["I1"][0]) || $Rec["I1"][0] != 1 || !isset($Rec["g"]) || !is_array($Rec["g"]) )  continue;

      foreach ($Rec["g"] as $G)
      {
        if ( substr($G, 0, 6) == "pages:" ) return substr($G,6);
      }
    }
    // $this->CS->debug("ALX333", $Recs);
    return "";
  }

  private function getVolume()
  {
    // $this->CS->debug("ALX333", $this->one["marc"]["M773"]);
    $Recs = $this->getMarcSubFieldsArray("M773", array("I1", "g"));
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["I1"][0]) || $Rec["I1"][0] != 1 || !isset($Rec["g"]) || !is_array($Rec["g"]) )  continue;

      $Tmp  = [];
      foreach ($Rec["g"] as $G)
      {
        if ( substr($G, 0, 7) == "volume:" ) $Tmp[] = substr($G,7);
        if ( substr($G, 0, 5) == "year:" )   $Tmp[] = "(" . substr($G,5) . ")";
        if ( substr($G, 0, 7) == "number:" ) $Tmp[] = ", " . substr($G,7);
      }
      return implode(" ", $Tmp);
    }
    // $this->CS->debug("ALX333", $Recs);
    return "";
  }

  private function getMainData()
  {
    // $this->CS->debug("ALX343", $this->one);
    $More   = array();
    $More[] = "ID:\\t"             . $this->id;
    $More[] = "Titel:\\t"          . $this->CS->getMarcText($this->id, "bib_01");
    $More[] = "Veröffentlicht:\\t" . $this->CS->getMarcText($this->id, "bib_12", 1) . ", "
                                   . $this->CS->getMarcText($this->id, "bib_12", 2) . ", "
                                   . $this->CS->getMarcText($this->id, "bib_12", 3);
    $More[] = "Autoren:\\t"        . $this->CS->getMarcText($this->id, "bib_04");
    $More[] = "Beteiligte:\\t"     . $this->CS->getMarcText($this->id, "bib_05");
    return implode("\\n", $More);
  }

  private function repairLink($Link, $Item = [])
  {
    // $this->CS->debug("ALX3", $Str);
     if (  stripos($Link, "§")      !== false )     $Link = str_ireplace("§",      "#",       $Link);
     if (  stripos($Link, "<id>")   !== false )     $Link = str_ireplace("<id>",   $this->id, $Link);
     if (  stripos($Link, "<isbn>") !== false )
     {
       $Fill = isset($this->CS->works["marc"][$this->id]["marc"]["M020"][0]["a"][0])
               ? $this->CS->works["marc"][$this->id]["marc"]["M020"][0]["a"][0]
               : "";
       if ( $Fill )                                $Link = str_ireplace("<isbn>", $Fill,     $Link);
     }
     if (  stripos($Link, "<M980.d>") !== false )
     {
       $Fill = isset($Item["d"][0]) && trim($Item["d"][0]) && $Item["d"][0] != "--%%--" ? $Item["d"][0] : "";
       if ( $this->CS->client == "PotsdamUB" )
       {
         $Switch = array("1080" => "11frh",
                         "1180" => "09frh",
                         "2080" => "golm",
                         "3080" => "bbb",
                         "4080" => "10frh");
         if ( array_key_exists($Fill, $Switch) )   $Fill = $Switch[$Fill];
       }
       if ( $Fill )                                $Link = str_ireplace("<M980.d>", urlencode($Fill), $Link);
     }
     if (  stripos($Link, "<M980.f>") !== false )
     {
       $Fill = isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" ? $Item["f"][0] : "";
       if ( $Fill )                                $Link = str_ireplace("<M980.f>", urlencode($Fill), $Link);
     }
     return $Link;
  }

  private function replacekeywords($Str)
  {
     return str_ireplace(array("<point>", "<comma>"), array(".", ","), $Str);
  }

  // ************* E L E M E N T S *******************
  private function getActionElements($Case, $Item=array())
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( !isset($Case["detailaction"]) || !$Case["detailaction"] )  return array();

    $More = explode(".", $Case["detailaction"]);
    $DA   = array_shift($More);
    $DA  .= "." . array_shift($More);
    $More = implode(".", $More);
    $More = explode(",", $More);
    $More = array_map(array($this,"replacekeywords"), $More);

    if ( substr($DA,0,2) == "l." )
    {
      $L = substr($DA,2);
      // Link Block
      // $this->CS->debug("ALXLML", $this->CS->links);

      if ( isset($this->CS->links[$L]) )
      {
        if ( $this->CS->lang == "g" && ( !isset($this->CS->links[$L]["german"])  || !isset($this->CS->links[$L]["germanlink"])) )  return [];
        if ( $this->CS->lang == "e" && ( !isset($this->CS->links[$L]["english"]) || !isset($this->CS->links[$L]["englishlink"])) ) return [];

        return  array(
                                  "type"    => "link",
                                  "link"    => array("url"    => $this->repairLink($this->CS->lang == "g" ? $this->CS->links[$L]["germanlink"] : $this->CS->links[$L]["englishlink"]),
                                                    "label"   => $this->CS->lang == "g" ? $this->CS->links[$L]["german"] : $this->CS->links[$L]["english"],
                                                     "target" => "_blank"),
                                  "icon"    => "mdi-link",
                                  "button"  => $Case["detailbutton"]
                        );
      }
    }

    switch ($DA)
    {
      // PAIA Block
      case  "p.order":
      {
        return array(
                                    "type"    => "request",
                                    "request" => array("id"  => (isset($Item["id"])) ? $Item["id"] : "",
                                                       "type" => "order"),
                                    "icon"    => "mdi-arrow-right-bold-box-outline",
                                    "button"  => $Case["detailbutton"]
                          );
        break;
      }
      case  "p.ordergroups":
      {
        // $this->CS->debug("ALXMore", $More);

        // Allowed: 2 items
        if ( count($More) != 2 ) break;

        return array(
                                    "type"      => "request",
                                    "request"   => array("id"   => (isset($Item["id"])) ? $Item["id"] : "",
                                                         "type" => "order"),
                                    "icon"      => "mdi-arrow-right-bold-box-outline",
                                    "button"    => $Case["detailbutton"],
                                    "deskgroup" => $More[0],
                                    "usergroup" => $More[1]
                          );
        break;
      }
      case  "p.reserve":
      {
        return  array(
                                    "type"    => "request",
                                    "request" => array("id"   => (isset($Item["id"])) ? $Item["id"] : "",
                                                       "type" => "reserve"),
                                    "icon"    => "mdi-arrow-right-bold-box-outline",
                                    "button"  => $Case["detailbutton"]
                          );
        break;
      }
      case  "p.reservegroups":
      {
        // $this->CS->debug("ALXMore", $More);

        // Allowed: 2 items
        if ( count($More) != 2 ) break;

        return array(
                                    "type"      => "request",
                                    "request"   => array("id"   => (isset($Item["id"])) ? $Item["id"] : "",
                                                         "type" => "reserve"),
                                    "icon"      => "mdi-arrow-right-bold-box-outline",
                                    "button"    => $Case["detailbutton"],
                                    "deskgroup" => $More[0],
                                    "usergroup" => $More[1]
                          );
        break;
      }

      // MARC Block
      case  "m.openaccess":
      {
        // $this->CS->debug("ALXOA", $Item);
        if ( !isset($Item["link"]) || !$Item["link"] )  break;
        return  array(
                                  "type"    => "link",
                                  "link"    => array("url"    => $Item["link"],
                                                    "label"   => isset($Item["text"]) ? $Item["text"] : $this->CS->getHost($Item["link"]),
                                                     "target" => "_blank"),
                                  "icon"    => "mdi-link",
                                  "button"  => $Case["detailbutton"]
                        );

        break;
      }
      case  "m.remote":
      {
        if ( !isset($Item["link"]) || !$Item["link"] )  break;
        return  array(
                                  "type"    => "link",
                                  "link"    => array("url"    => $Item["link"],
                                                    "label"   => isset($Item["text"]) ? $Item["text"] : $this->CS->getHost($Item["link"]),
                                                    "target" => "_blank"),
                                  "icon"    => "mdi-link",
                                  "button"  => $Case["detailbutton"]
                        );

        break;
      }

      // Other Mail Block
      case  "o.mailelse":
      {
        // Frueher ein eigener Aktionstyp mit eigener Vue-Maske; jetzt der
        // generische Dialog "else" aus dialogs/dialogfields, verschickt vom
        // server-eigenen sendDialogMail() (19.08.2026). Der Betreff kommt dort
        // aus getText("mail.subject.else"), also wie zuvor. Die Konfig-Pruefung
        // bleibt: ohne hinterlegte Zieladresse gibt es den Knopf nicht.
        if ( !isset($this->CS->configs["mail.".$this->mode]["else"]) || !$this->CS->configs["mail.".$this->mode]["else"] ) break;
        return  array(
                         "type"    => "dialog",
                         "dialog"  => "else",
                         "icon"    => "mdi-email-outline",
                         "button"  => $Case["detailbutton"]
                       );

        break;
      }
      case  "o.mailnotify":
      {
        // Frueher ein eigener Aktionstyp mit eigener Vue-Maske; jetzt der
        // generische Dialog "notify" aus dialogs/dialogfields, verschickt vom
        // server-eigenen sendDialogMail() (19.08.2026). Der Betreff kommt dort
        // aus getText("mail.subject.notify"), also wie zuvor. Die Konfig-Pruefung
        // bleibt: ohne hinterlegte Zieladresse gibt es den Knopf nicht.
        if ( !isset($this->CS->configs["mail.".$this->mode]["notify"]) || !$this->CS->configs["mail.".$this->mode]["notify"] ) break;
        return  array(
                         "type"    => "dialog",
                         "dialog"  => "notify",
                         "icon"    => "mdi-email-outline",
                         "button"  => $Case["detailbutton"]
                       );

        break;
      }
      case  "o.mailorder":
      {
        // Frueher ein eigener Aktionstyp mit eigener Vue-Maske; jetzt der
        // generische Dialog "order" aus dialogs/dialogfields, verschickt vom
        // server-eigenen sendDialogMail() (19.08.2026). Der Betreff kommt dort
        // aus getText("mail.subject.order"), also wie zuvor. Die Konfig-Pruefung
        // bleibt: ohne hinterlegte Zieladresse gibt es den Knopf nicht.
        if ( !isset($this->CS->configs["mail.".$this->mode]["order"]) || !$this->CS->configs["mail.".$this->mode]["order"] ) break;
        return  array(
                         "type"    => "dialog",
                         "dialog"  => "order",
                         "icon"    => "mdi-email-outline",
                         "button"  => $Case["detailbutton"]
                       );

        break;
      }
      case  "o.mailreserve":
      {
        // Frueher ein eigener Aktionstyp mit eigener Vue-Maske; jetzt der
        // generische Dialog "reserve" aus dialogs/dialogfields, verschickt vom
        // server-eigenen sendDialogMail() (19.08.2026). Der Betreff kommt dort
        // aus getText("mail.subject.reserve"), also wie zuvor. Die Konfig-Pruefung
        // bleibt: ohne hinterlegte Zieladresse gibt es den Knopf nicht.
        if ( !isset($this->CS->configs["mail.".$this->mode]["reserve"]) || !$this->CS->configs["mail.".$this->mode]["reserve"] ) break;
        return  array(
                         "type"    => "dialog",
                         "dialog"  => "reserve",
                         "icon"    => "mdi-email-outline",
                         "button"  => $Case["detailbutton"]
                       );

        break;
      }
      case  "o.mailsuggest":
      {
        // Frueher ein eigener Aktionstyp mit eigener Vue-Maske; jetzt der
        // generische Dialog "suggest" aus dialogs/dialogfields, verschickt vom
        // server-eigenen sendDialogMail() (19.08.2026). Der Betreff kommt dort
        // aus getText("mail.subject.suggest"), also wie zuvor. Die Konfig-Pruefung
        // bleibt: ohne hinterlegte Zieladresse gibt es den Knopf nicht.
        if ( !isset($this->CS->configs["mail.".$this->mode]["suggest"]) || !$this->CS->configs["mail.".$this->mode]["suggest"] ) break;
        return  array(
                         "type"    => "dialog",
                         "dialog"  => "suggest",
                         "icon"    => "mdi-email-outline",
                         "button"  => $Case["detailbutton"]
                       );

        break;
      }

      // Other Block
      case "o.clientmail":
      {
        // $this->CS->debug("ALXMAIL", $More);
        // $this->CS->debug("ALXMAIL", $Item);

        // Allowed: 3 or 4 items
        if ( count($More) < 3 || count($More) > 4  ) break;

        $MailTo = isset($More[0]) ? $More[0] : "";
        if ( $MailTo )  $MailTo = $this->CS->getText($MailTo);

        $MailSubject = isset($More[1]) ? $More[1] : "";
        if ( $MailSubject )  $MailSubject = $this->CS->getText($MailSubject);

        $MessBef = isset($More[2]) ? $More[2] : "";
        if ( $MessBef )
        {
          $MessBef = $this->CS->getText($MessBef);
          if (  stripos($MessBef, "<M001>") !== false )
          {
            $MessBef = str_ireplace("<M001>", $this->id, $MessBef);
          }
          if (  stripos($MessBef, "<M981.r>") !== false )
          {
            $Fill = "";
            $MessBef = str_ireplace("<M981.r>", $Fill, $MessBef);
          }

        }

        $MessAft = isset($More[3]) ? $More[3] : "";
        if ( $MessAft )
        {
          $MessAft = $this->CS->getText($MessAft);
          if (  stripos($MessAft, "<M001>") !== false )
          {
            $MessAft = str_ireplace("<M001>", $this->id, $MessAft);
          }
          if (  stripos($MessAft, "<M981.r>") !== false )
          {
            $Fill = $this->CS->getMarcFieldFirst($this->id, "M981", "r");
            $MessAft = str_ireplace("<M981.r>", $Fill, $MessAft);
          }
        }

        return  array(
                        "type"    => "clientmail",
                        "mail"    => array("to"      => $MailTo,
                                           "subject" => $MailSubject,
                                           "messbef" => $MessBef,
                                           "more"    => $this->getMainData($Item),
                                           "messaft" => $MessAft),
                        "icon"    => $Case["itemicon"],
                        "button"  => $Case["detailbutton"]
                       );

        // $this->CS->debug("ALXMAIL", $Actions);
        break;
      }
      case "o.delivery":
      {
        // Lieferdienst laeuft jetzt ueber den generischen Dialog. GreifswaldUB
        // hat ein erweitertes Formular (Signatur/Band/Seiten/...), das als
        // "delivery2" in dialogfields steht; alle anderen "delivery"
        // (19.08.2026, loest ItemDlgDelivery/Delivery2 des Altmoduls ab).
        return  array(
                      "type"    => "dialog",
                      "dialog"  => $this->CS->client == "GreifswaldUB" ? "delivery2" : "delivery",
                      "icon"    => $Case["itemicon"],
                      "button"  => $Case["detailbutton"]
                     );
        break;
      }
      case "o.dialog":
      {
        return  array(
                      "type"    => "dialog",
                      "dialog"  => isset($More[0]) ? $More[0] : "",
                      "icon"    => $Case["itemicon"],
                      "button"  => $Case["detailbutton"]
                     );
        break;
       }
      case "o.externlibrary":
      {
        $Col = strtolower($this->CS->getCollectionFromPPN($this->id));
        if ( isset($Col, $this->CS->configs["link"]["interlibrary.". $Col]) )
        {
          $FLPPN = ( preg_match("/\d{4,}/", $this->id, $firstdigit) )
                   ? substr($this->id, strpos($this->id, $firstdigit[0]))
                   : $this->id;

          return  array(
                          "type"    => "link",
                          "link"    => array("url"   => $this->CS->configs["link"]["externlibrary"] . "/(DE-627)" . $FLPPN,
                                            "target" => "_blank"),
                          "icon"    => $Case["itemicon"],
                          "button"  => $Case["detailbutton"]
                         );

        }
        break;
      }
      case "o.inactive":
      {
        return  array(
                      "type"    => "inactive",
                      "icon"    => $Case["itemicon"],
                      "button"  => $Case["detailbutton"]
                     );
        break;
      }
      case "o.interlibrary":
      {
        $Col = strtolower($this->CS->getCollectionFromPPN($this->id));
        if ( isset($Col, $this->CS->configs["link"]["interlibrary.". $Col]) )
        {
          $FLPPN = ( preg_match("/\d{4,}/", $this->id, $firstdigit) )
                   ? substr($this->id, strpos($this->id, $firstdigit[0]))
                   : $this->id;
          $Lang  = ( isset($this->CS->lang) && $this->CS->lang == "e" ) ? "EN" : "DU";

          return  array(
                          "type"    => "link",
                          "link"    => array("url"   => $this->CS->configs["link"]["interlibrary.". $Col]
                                                        . "/PPNSET?PPN=" . $FLPPN . "&LNG=" . $Lang,
                                            "target" => "_blank"),
                          "icon"    => $Case["itemicon"],
                          "button"  => $Case["detailbutton"]
                         );
        }
        break;
      }
      case  "m.masterlink":
      {
        if ( !isset($this->one["masterlink"]) || !trim($this->one["masterlink"]) )  break;
        return  array(
                                  "type"    => "link",
                                  "link"    => array("url"    => $this->one["masterlink"],
                                                    "label"   => isset($Item["text"]) ? $Item["text"] : $this->CS->getHost($Item["link"]),
                                                     "target" => "_blank"),
                                  "icon"    => "mdi-link",
                                  "button"  => $Case["detailbutton"]
                        );
        break;
      }
      case  "o.opaclink":
      {
        // iln2bms ist nach ILN UND bmsid verschachtelt - die db-Angabe haengt an der
        // ILN, nicht am einzelnen BMS, deshalb genuegt der erste Eintrag. Der
        // fruehere flache Zugriff iln2bms[iln]["db"] lief seit der
        // Verschachtelung ins Leere und lieferte immer "" (21.08.2026).
        $DB       = $this->ilnDB(isset($Item["2"]["0"]) ? $Item["2"]["0"] : "");

        $OPACLINK = "";
        if ( MODE == "production" 
          && isset($this->CS->bms[$this->CS->bmsid]["options"]["prodopaclink"]) 
          && $this->CS->bms[$this->CS->bmsid]["options"]["prodopaclink"] )
        {
          $OPACLINK = $this->CS->bms[$this->CS->bmsid]["options"]["prodopaclink"];
        }
        elseif ( MODE != "production" 
          && isset($this->CS->bms[$this->CS->bmsid]["options"]["testopaclink"]) 
          && $this->CS->bms[$this->CS->bmsid]["options"]["testopaclink"] )
        {
          $OPACLINK = $this->CS->bms[$this->CS->bmsid]["options"]["testopaclink"];
        }
        if ( $DB && $OPACLINK )
        {
          return  array(
                                    "type"    => "link",
                                    "link"    => array("url"    => $OPACLINK . "/DB=" . $DB . "/XMLPRS=N/PPN?PPN=" . $this->id,
                                                       "target" => "_blank"),
                                    "icon"    => "mdi-link",
                                    "button"  => $Case["detailbutton"]
                        );
        }
        else
        {
          $Col = $this->CS->getCollectionFromPPN($this->id);
          if ( in_array($Col, array("KXP", "OLC", "SWB", "OEVK")) )
          {
            $Links = array("KXP"  => "https://kxp.k10plus.de/DB=2.1",
                           "OLC"  => "https://olc.k10plus.de",
                           "SWB"  => "https://swb.bsz-bw.de",
                           "OEVK" => "https://oevk.k10plus.de");
            $ID    = substr($this->id, $Col == "KXP" ? 0 : strlen($Col));
            return  array(
                                    "type"    => "link",
                                    "link"    => array("url"    => $Links[$Col] . "/PPNSET?PPN=" . $ID,
                                                       "target" => "_blank"),
                                    "icon"    => "mdi-link",
                                    "button"  => $Case["detailbutton"]
                          );
          }
        }
        break;
      }
      case "o.related":
      {
          return  array(
                                    "type"    => "change",
                                    "change"  => "related",
                                    "icon"    => $Case["itemicon"],
                                    "button"  => $Case["itembutton"]
                          );
          break;
      }
      case "o.search":
      {
        if ( !$this->pid ) break;

        return  array(
                                  "type"    => "search",
                                  "search"  => array("query" => "id:" . $this->pid,
                                                     "type"  => "lukida"),
                                  "icon"    => $Case["itemicon"],
                                  "button"  => $Case["itembutton"]
                        );
        break;
      }
      case "o.skip":
      {
        return  array(
                      "type"    => "skip"
                     );
        break;
      }

      // Library Block (Achtung - nur o.Bibliothek als Case und danach mit more abfragen ... )
      case "o.iiif":
      {
        if ( !isset($Item["link"]) || !$Item["link"] )  break;
        return  [
                 "type"    => "link",
                 "link"    => array("url"    => $Item["link"] . "/fragment/content_search={query}",
                                   "label"   => isset($Item["text"]) ? $Item["text"] : $this->CS->getHost($Item["link"]),
                                    "target" => "_blank"),
                 "icon"    => "mdi-link",
                 "button"  => $Case["detailbutton"]
                ];
        break;
      }
      case  "o.flensburgzhb":
      {
        if ( isset($More[0]) && $More[0] == "openaccess" )
        {
          if ( !isset($Item["link"]) || !$Item["link"] || !isset($Item["y"][0]) || !in_array(substr($Item["y"][0],1,1),array("1", "2")) )  break;

          $ZHBLNK = substr($Item["y"][0],1,1) == "1" ? "uni" : "hs";

          return  array(
                                  "type"    => "link",
                                  "link"    => array("url"    => "https://" . $ZHBLNK . "-flensburg.idm.oclc.org/login?url=" . $Item["link"],
                                                    "label"   => isset($Item["text"]) ? $Item["text"] : $this->CS->getHost($Item["link"]),
                                                     "target" => "_blank"),
                                  "icon"    => "mdi-link",
                                  "button"  => $Case["detailbutton"]
                        );
        }
        break;
      }
      case "o.goettingensub":
      {
        if ( isset($More[0]) && $More[0] == "search" )
        {
          // $this->CS->debug("ALXITEM", $More);
          // $this->CS->debug("ALEXIWW", $this->one["rootid"]);

          if ( !isset($this->one["rootid"]) ) break;
          return array(
                                  "type"    => "search",
                                  "search"  => array("query" => "id:" . $this->one["rootid"],
                                                     "type"  => "lukida"),
                                  "icon"    => $Case["itemicon"],
                                  "button"  => $Case["itembutton"]
                      );
          break;
        }
      }
      case  "o.neubrandenburghs":
      {
        if ( isset($More[0]) && $More[0] == "aquisitionrequest" )
        {
          if ( !isset($Item["link"]) || !$Item["link"] )  break;
          return  array(
                                  "type"    => "link",
                                  "link"    => array("url"    => $Item["link"] . "?getppn=" . $this->id,
                                                    "label"   => "",
                                                     "target" => "_blank"),
                                  "icon"    => "mdi-link",
                                  "button"  => $Case["detailbutton"]
                        );
        }
        break;
      }
    }
    return array();
  }

  private function getDisplayElements($Disps, $Item = array())
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $TxtCnt   = 1;
    $Texts    = array("g" => array(), "e" => array());
    // $this->CS->debug("AÖX111a", $Disps);

    foreach ($Disps as $Disp)
    {
      if ( substr($Disp,0,2) == "f." )
      {
        $Tmp = $this->getFOLIOElement($Item, substr($Disp,2));
        if ( !isset($Tmp["g"]) || !isset($Tmp["e"]) ) continue;
        $Texts["g"] = [...$Texts["g"],
                       ...$Tmp["g"]];
        $Texts["e"] = [...$Texts["e"],
                       ...$Tmp["e"]];
      }
      elseif ( substr($Disp,0,2) == "h." )
      {
        $Tmp = $this->CS->getTextBothLang(substr($Disp,2));
        if ( !isset($Tmp["german"]) || !isset($Tmp["english"]) ) continue;
        $Texts["g"][] = array("type" => "head",
                              "text" => $Tmp["german"]);
        $Texts["e"][] = array("type" => "head",
                              "text" => $Tmp["english"]);
      }
      elseif ( substr($Disp,0,2) == "m." )
      {
        $Tmp = $this->getMARCElement($Item, substr($Disp,2));
        if ( !isset($Tmp["g"]) || !isset($Tmp["e"]) ) continue;
        $Texts["g"] = [...$Texts["g"],
                       ...$Tmp["g"]];
        $Texts["e"] = [...$Texts["e"],
                       ...$Tmp["e"]];
      }
      elseif ( substr($Disp,0,2) == "o." )
      {
        $Tmp = $this->getOtherElement($Item, substr($Disp,2));
        if ( !isset($Tmp["g"]) || !isset($Tmp["e"]) ) continue;
        $Texts["g"] = [...$Texts["g"],
                       ...$Tmp["g"]];
        $Texts["e"] = [...$Texts["e"],
                       ...$Tmp["e"]];
      }
      elseif ( substr($Disp,0,2) == "p." )
      {
        $Tmp = $this->getPAIAElement($Item, substr($Disp,2));
        if ( !isset($Tmp["g"]) || !isset($Tmp["e"]) ) continue;
        $Texts["g"] = [...$Texts["g"],
                       ...$Tmp["g"]];
        $Texts["e"] = [...$Texts["e"],
                       ...$Tmp["e"]];
      }

      // Working
      elseif ( substr($Disp,0,2) == "l." )
      {
        // $this->CS-debug("ALEXLI", $this->CS->links);
        $Tmp = (isset($this->CS->links[substr($Disp,2)])) ? $this->CS->links[substr($Disp,2)] : array();
        if ( !isset($Tmp["german"])     || !isset($Tmp["english"]) )     continue;
        if ( !isset($Tmp["germanlink"]) || !isset($Tmp["englishlink"]) ) continue;

        $Tmp["germanlink"]  = $this->repairLink($Tmp["germanlink"]);
        $Tmp["englishlink"] = $this->repairLink($Tmp["englishlink"]);

        if (  stripos($Tmp["germanlink"], "<M980.b>") !== false )
        {
          $Fill = isset($Item["b"][0]) && trim($Item["b"][0]) && $Item["b"][0] != "--%%--" ? $Item["b"][0] : "";
          $Tmp["germanlink"] = str_ireplace("<M980.b>", urlencode($Fill), $Tmp["germanlink"]);
        }
        if (  stripos($Tmp["englishlink"], "<M980.b>") !== false )
        {
          $Fill = isset($Item["b"][0]) && trim($Item["b"][0]) && $Item["b"][0] != "--%%--" ? $Item["b"][0] : "";
          $Tmp["englishlink"] = str_ireplace("<M980.b>", urlencode($Fill), $Tmp["englishlink"]);
        }

        if (  stripos($Tmp["germanlink"], "<M980.d>") !== false )
        {
          $Fill = isset($Item["d"][0]) && trim($Item["d"][0]) && $Item["d"][0] != "--%%--" ? $Item["d"][0] : "";
          $Tmp["germanlink"] = str_ireplace("<M980.d>", urlencode($Fill), $Tmp["germanlink"]);
        }
        if (  stripos($Tmp["englishlink"], "<M980.d>") !== false )
        {
          $Fill = isset($Item["d"][0]) && trim($Item["d"][0]) && $Item["d"][0] != "--%%--" ? $Item["d"][0] : "";
          $Tmp["englishlink"] = str_ireplace("<M980.d>", urlencode($Fill), $Tmp["englishlink"]);
        }

        if (  stripos($Tmp["germanlink"], "<M980.f>") !== false )
        {
          $Fill = isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" ? $Item["f"][0] : "";
          $Tmp["germanlink"] = str_ireplace("<M980.f>", urlencode($Fill), $Tmp["germanlink"]);
        }
        if (  stripos($Tmp["englishlink"], "<M980.f>") !== false )
        {
          $Fill = isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" ? $Item["f"][0] : "";
          $Tmp["englishlink"] = str_ireplace("<M980.f>", urlencode($Fill), $Tmp["englishlink"]);
        }

        $Texts["g"][] = array("type" => "link",
                              "text" => $Tmp["german"],
                              "link" => $Tmp["germanlink"]);
        $Texts["e"][] = array("type" => "link",
                              "text" => $Tmp["english"],
                              "link" => $Tmp["englishlink"]);
      }
      elseif ( substr($Disp,0,2) == "t." )
      {
        $Tmp = $this->CS->getTextBothLang(substr($Disp,2));
        if ( !isset($Tmp["german"]) || !isset($Tmp["english"]) ) continue;
        $Texts["g"][] = array("type" => "text",
                              "text" => $Tmp["german"]);
        $Texts["e"][] = array("type" => "text",
                              "text" => $Tmp["english"]);
      }
      else
      {
        $Texts[] = $Disp;
      }
    }
     // $this->CS->debug("ALX111b", $Texts);
    return $Texts;
  }

  private function getMARCElement($Item, $Element)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Texts = array("g" => array(), "e" => array());
    
    $Tmp     = explode(".", $Element);
    $Element = array_shift($Tmp);
    $Tmp     = implode(".", $Tmp);
    $More    = explode(",", $Tmp);

    switch ($Element)
    {
      // m.hamburghsu.<wert> - Sonderbausteine HamburgHSU (15.09.2026)
      case "hamburghsu":
      {
        // m.hamburghsu.handapparat: EINE Zeile "Handapparat <M980 $f>" mit
        // Link-Symbol auf die Liste der Handapparate. Text aus texts
        // ("handapparat"), Adresse aus links ("hand") - beide pflegt die HSU in
        // ihren Importdateien. Ohne Wert in $f (oder "--%%--") keine Zeile;
        // ohne Link bleibt es beim Text.
        if ( isset($More[0]) && $More[0] == "handapparat" )
        {
          $Nr = ( isset($Item["f"][0]) && trim($Item["f"][0]) !== "" && trim($Item["f"][0]) != "--%%--" ) ? trim($Item["f"][0]) : "";
          if ( $Nr === "" )  break;

          $T = $this->CS->getTextBothLang("handapparat");
          $G = ( isset($T["german"])  && trim($T["german"])  ) ? trim($T["german"])  : "Handapparat";
          $E = ( isset($T["english"]) && trim($T["english"]) ) ? trim($T["english"]) : "Reserve collection";

          $L  = isset($this->CS->links["hand"]) ? $this->CS->links["hand"] : array();
          $LG = isset($L["germanlink"])  ? trim($this->repairLink($L["germanlink"]))  : "";
          $LE = isset($L["englishlink"]) ? trim($this->repairLink($L["englishlink"])) : "";
          if ( $LE === "" )  $LE = $LG;

          $Texts["g"][] = $LG !== "" ? array("type" => "link", "text" => $G . " " . $Nr, "link" => $LG)
                                     : array("type" => "text", "text" => $G . " " . $Nr);
          $Texts["e"][] = $LE !== "" ? array("type" => "link", "text" => $E . " " . $Nr, "link" => $LE)
                                     : array("type" => "text", "text" => $E . " " . $Nr);
        }
        break;
      }

      // m.articlestock
      case "articlestock":
      {
        // $this->CS->debug("ALXI344", $Item);
        // $this->CS->debug("ALXI344", $this->one);
        // $this->CS->debug("ALXI344", $this->one["marc"]["M773"]);
        $Val =  isset($this->one["marc"]["M773"][0]["g"][0]) ? $this->one["marc"]["M773"][0]["g"][0] : "";

        if ( $Val )
        {
          $this->addArr2Arr($this->stock, array($Val));
          $T   = $this->CS->getTextBothLang("items.stock");
          $Texts["g"][] = array("type" => "text",
                                "text" => $T["german"]  . ": " . $Val);
          $Texts["e"][] = array("type" => "text",
                                "text" => $T["english"]  . ": " . $Val);
        }
        break;
      }

      // m.comment
      case "comment":
      {
        // $this->CS->debug("ALX4353", $Item);
        $Black = explode("|", $this->CS->configs["item"]["commentblacklist"]);
        // $this->CS->debug("ALX4353", $Black);
        $Vals  = array();
        if ( isset($Item["k"]) && is_array($Item["k"]) )
        {
          foreach ($Item["k"] as $Key => $Val)
          {
           if (in_array($Val, $Black)) continue;
           // $this->CS->debug("ALXCOM", $Val);
           $Vals[] = $Val;
          }
        }

        if ( isset($Item["text"]) && !in_array($Item["text"], $Black) )
        {
          $Vals[] = $Item["text"];
        }

        if ( isset($Item["comment"]) && is_array($Item["comment"]) )
        {
          $Vals = [...$Vals,
                   ...array_diff($Item["comment"], $Black)];
        }

        if ( is_array($Vals) && count($Vals) )
        {
          $this->addArr2Arr($this->comment, $Vals);

          $Vals = array_filter(array_values(array_unique($Vals)));

          foreach ( $Vals as $Val )
          {
            $Texts["g"][] = array("type" => "text",
                                  "text" => strip_tags($Val));
            $Texts["e"][] = array("type" => "text",
                                  "text" => strip_tags($Val));
          }
        }
        break;
      }

      // m.commentstrict
      case "commentstrict":
      {
        // $this->CS->debug("ALXCS", $Item);

        $Gesehen = array();

        if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) && count($this->one["marc"]["M980"])
          && isset($Item["iln"]) && trim($Item["iln"]) && isset($Item["enr"]) && trim($Item["enr"]) )
        foreach ( $this->one["marc"]["M980"] as $Rec )
        {
          // $this->CS->debug("ALXCS1", "22");
          // Wie bei m.stockstrict zaehlt die EPN "00" als "gilt fuer ALLE
          // Exemplare dieser ILN" (03.09.2026). Ein M981 mit $1 = 00 - der
          // EZB-Link bei IlmenauTU 534054390 - ist genau so ein Eintrag; ohne
          // diese Regel traf der Baustein dort auf keine der M980 (02, 03) und
          // trug nichts bei, waehrend m.stockstrict den Bestand lieferte.
          if ( isset($Rec["2"][0]) && $Rec["2"][0] == $Item["iln"]
            && ( (isset($Rec["1"][0]) && $Rec["1"][0] == $Item["enr"]) || $Item["enr"] == "00" )
            && isset($Rec["k"]) && is_array($Rec["k"]) && count($Rec["k"]) )
          {
            foreach ($Rec["k"] as $Val )
            {
              if ( !trim($Val) )  continue;
              // Mit der 00-Regel sieht ein Eintrag ALLE M980 dieser ILN - und
              // derselbe Kommentar steht dort oft an jedem Exemplar ("Campus-
              // lizenz TU Ilmenau" an M980 02 UND 03). Gleiche Texte deshalb
              // nur einmal (03.09.2026).
              if ( in_array(strip_tags($Val), $Gesehen) )  continue;
              $Gesehen[] = strip_tags($Val);
              $Texts["g"][] = array("type" => "text",
                                    "text" => strip_tags($Val));
              $Texts["e"][] = array("type" => "text",
                                    "text" => strip_tags($Val));
            }
          }
        }

        if ( isset($Item["text"]) && trim($Item["text"]) )
        {
          $Texts["g"][] = array("type" => "text",
                                "text" => $Item["text"]);
           $Texts["e"][] = array("type" => "text",
                                 "text" => $Item["text"]);
        }
        break;
      }

      // m.count
      case "count":
      {
        $Val = isset($Item["a"][0]) ? intval($Item["a"][0]) : "";

        if ( $Val && $Val >= 1 )
        {
          $T   = $this->CS->getTextBothLang("items.count" . ($Val >= 2 ? "s": ""));
          $Texts["g"][] = array("type" => "text",
                                "text" => $Val . " " . $T["german"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $Val . " " . $T["english"]);
        }
        break;
      }
      
      // m.direct
      case "direct":
      {
        if ( is_array($More) && count($More) >= 4 )
        {
          $Mama = "";
          $Kind = "";
          // $this->CS->debug("ALX40", $this->CS->hierarchy);      
          if ( array_key_exists($this->id,$this->CS->hierarchy["childs"]) ) {
            $Kind = $this->id;
            $Mama = $this->CS->hierarchy["childs"][$this->id];
          } elseif ( array_key_exists($this->id,$this->CS->hierarchy["parents"]) ) {
            $Kind = $this->CS->hierarchy["parents"][$this->id][0];
            $Mama = $this->id;
          } else {
            $Kind = $this->id;
            $Mama = "";
          }
          
          // $this->CS->debug("ALX40", $Mama);

          $Tmp = [];
          $Type = substr(strtolower($More[0]),0,1) == "m" ? "M" : "P";
          
          if ( trim($Kind) && $Type == "M" && isset($this->CS->works["marc"]) && is_array($this->CS->works["marc"]) && count($this->CS->works["marc"]) )
          {
            foreach ($this->CS->works["marc"] as $OneM)
            {
              if ( isset($OneM["marc"]) && is_array($OneM["marc"]) && count($OneM["marc"]) )
              {
                if ( isset($OneM["marc"]["M001"]) && $OneM["marc"]["M001"] == $Kind )
                {
                  // $this->CS->debug("ALX40", $Kind . " Kind M Found");
                  $Tmp = $OneM["marc"];
                  break;
                }
              }
            }
          }
          if ( trim($Mama) && $Type == "P" && isset($this->CS->works["add"]) && is_array($this->CS->works["add"]) && count($this->CS->works["add"]) )
          {
            foreach ($this->CS->works["add"] as $OneM)
            {
              if ( isset($OneM["marc"]) && is_array($OneM["marc"]) && count($OneM["marc"]) )
              {
                if ( isset($OneM["marc"]["PM001"]) && $OneM["marc"]["PM001"] == $Mama)
                {
                  // $this->CS->debug("ALX40", $Mama . " Add Mama PM Found");
                  $Tmp = $OneM["marc"];
                  break;
                }
              }
            }
          }
          
          if ( is_array($Tmp) && count($Tmp) )
          {
            if ( isset($Tmp[strtoupper($More[0])][$More[1]][$More[2]][$More[3]]) )
            {
              $Text = $Tmp[strtoupper($More[0])][$More[1]][$More[2]][$More[3]];
              
              $Bef = array("german" => "", "english" => "");
              $Aft = array("german" => "", "english" => "");
              if ( isset($More[4]) )  $Bef = $this->CS->getTextBothLang($More[4]);
              if ( isset($More[5]) )  $Aft = $this->CS->getTextBothLang($More[5]);
              
              $Texts["g"][] = array("type" => "text",
                                    "text" => implode(" ", [$Bef["german"],
                                                            $Text,
                                                            $Aft["german"]]));
              $Texts["e"][] = array("type" => "text",
                                    "text" => implode(" ", [$Bef["english"],
                                                            $Text,
                                                            $Aft["english"]]));
            }
          }
        }
        break;
      }

      // m.host
      case "host":
      {
        // $this->CS->debug("ALX3433", $Item);
        if ( isset($Item["host"]) && trim($Item["host"]) )
        {
          $Texts["g"][] = array("type" => "text",
                                "text" => $Item["host"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $Item["host"]);
        }
        break;
      }

      // m.location
      case "location":
      {
        // $this->CS->debug("ALX4353", $Item);
        if ( isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" )
        {
          $Texts["g"][] = array("type" => "text",
                                "text" => trim($Item["f"][0]));
          $Texts["e"][] = array("type" => "text",
                                "text" => trim($Item["f"][0]));
        }
        break;
      }

      // m.stock
      case "stock":
      {
        $Vals      = array();
        if ( isset($Item["g"]) && is_array($Item["g"]) )
        {
          foreach ($Item["g"] as $Key => $Val)
          {
            $Vals[] = substr($Val,0,8) == "Bestand:" ? trim(substr($Val, 8)) : $Val;
          }
        }

        // $this->CS->debug("AlexIe2", $Vals);

        if ( isset($Item["stock"]) && is_array($Item["stock"]) )
        {
          $Vals = [...$Vals,
                   ...$Item["stock"]];
        }

        // $this->CS->debug("AlexIe3", $Vals);

        if ( is_array($Vals) && count($Vals) )
        {
          $this->addArr2Arr($this->stock, $Vals);

          $T   = $this->CS->getTextBothLang("items.stock");
          $Texts["g"][] = array("type" => "text",
                                "text" => $T["german"]  . ": " . implode(", ", $Vals));
           $Texts["e"][] = array("type" => "text",
                                 "text" => $T["english"]  . ": " . implode(", ", $Vals));
        }
        break;
      }

      // m.stockstrict
      case "stockstrict":
      {
        if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) && count($this->one["marc"]["M980"])
          && isset($Item["iln"]) && trim($Item["iln"]) && isset($Item["enr"]) && trim($Item["enr"]) )
        foreach ( $this->one["marc"]["M980"] as $Rec )
        {
          if ( isset($Rec["2"][0]) && $Rec["2"][0] == $Item["iln"]
            && ( (isset($Rec["1"][0]) && $Rec["1"][0] == $Item["enr"]) || $Item["enr"] == "00" )
            && isset($Rec["g"]) && is_array($Rec["g"]) && count($Rec["g"]) )
          {
            foreach ($Rec["g"] as $Val )
            {
              $Texts["g"][] = array("type" => "text",
                                    "text" => strip_tags($Val));
              $Texts["e"][] = array("type" => "text",
                                    "text" => strip_tags($Val));
            }
          }
        }
        break;
      }

      // m.signature
      case "signature":
      {
        $Vals      = array();
        if ( isset($Item["d"]) && is_array($Item["d"]) )
        {
          $Return = $this->modifySignatureLocal($Item);
          if ( isset($Return["status"]) && $Return["status"] ) return $Return["values"];
        }
        break;
      }

      // m.locationsignature
      case "locationsignature":
      {
        // Wie m.signature, nur jeder Signatur den Standort aus m980 f
        // vorangestellt: "Signatur: Lesesaal:GC 5927" (02.09.2026).
        //
        // Fehlt der Standort, bleibt es bei der reinen Signatur - der Baustein
        // soll die Signatur nicht verschlucken, nur weil m980 f leer ist.
        $Vals      = array();
        $Loc       = isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--"
                     ? trim($Item["f"][0]) : "";
        if ( isset($Item["d"]) && is_array($Item["d"]) )
        {
          $Return = $this->modifySignatureLocal($Item, $Loc);
          if ( isset($Return["status"]) && $Return["status"] ) return $Return["values"];
        }
        break;
      }

      // m.prov
      case "prov":
      {
        // $this->CS->debug("ALX3433", $Item);
        // $this->CS->debug("ALX3433", $this->one);

        if ( isset($this->one["marc"]["M982"]) && is_array($this->one["marc"]["M982"]) )
        {
          $Vals      = array();
          foreach ( $this->one["marc"]["M982"] as $Rec )
          {
            // $this->CS->debug("ALX3433", $Rec);
            if ( isset($Rec["1"][0]) && isset($Item["1"]) && $Rec["1"][0] == $Item["1"]
              && isset($Rec["2"][0]) && isset($Item["2"]) && $Rec["2"][0] == $Item["2"]
              && isset($Rec["a"][0]) && substr($Rec["a"][0],0,11) == "Provenienz:")  $Vals[] = $Rec["a"][0];
          }
          if ( count($Vals) )
          {
            $Texts["g"][] = array("type" => "text",
                                  "text" => implode(", ", $Vals));
             $Texts["e"][] = array("type" => "text",
                                   "text" => implode(", ", $Vals));
          }
        }
        break;
      }

      // m.usagelink
      case "usagelink":
      {
        // $this->CS->debug("ALXITM", $Item);
        if ( isset($Item["usagelinks"]) && is_array($Item["usagelinks"]) && count($Item["usagelinks"]) )
        {
          foreach ( $Item["usagelinks"] as $UL )
          {
            if ( isset($UL["usagetext"]) && isset($UL["usagelink"]) )
            {
              $Texts["g"][] = array("type" => "link",
                                    "text" => $UL["usagetext"],
                                    "link" => $UL["usagelink"]);
              $Texts["e"][] = array("type" => "link",
                                    "text" => $UL["usagetext"],
                                    "link" => $UL["usagelink"]);
            }
            if ( isset($UL["usagetext"]) && !isset($UL["usagelink"]) )
            {
              $Texts["g"][] = array("type" => "text",
                                    "text" => $UL["usagetext"]);
              $Texts["e"][] = array("type" => "text",
                                    "text" => $UL["usagetext"]);
            }
            if ( !isset($UL["usagetext"]) && isset($UL["usagelink"]) )
            {
              $Texts["g"][] = array("type" => "link",
                                    "text" => "",
                                    "link" => $UL["usagelink"]);
              $Texts["e"][] = array("type" => "link",
                                    "text" => "",
                                    "link" => $Item["usagelink"]);
            }
          }
        }
        break;
      }
    }
    return $Texts;
  }

  private function getOtherElement($Item, $Element)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Texts = array("g" => array(), "e" => array());

    $Tmp     = explode(".", $Element);
    $Element = array_shift($Tmp);
    $Tmp     = implode(".", $Tmp);
    $More    = explode(",", $Tmp);

    switch ($Element)
    {
      // o.opaclink
      case "opaclink":
      {
        // iln2bms ist nach ILN UND bmsid verschachtelt - die db-Angabe haengt an der
        // ILN, nicht am einzelnen BMS, deshalb genuegt der erste Eintrag. Der
        // fruehere flache Zugriff iln2bms[iln]["db"] lief seit der
        // Verschachtelung ins Leere und lieferte immer "" (21.08.2026).
        $DB       = $this->ilnDB(isset($Item["2"]["0"]) ? $Item["2"]["0"] : "");
        $OPACLINK = "";
        if ( MODE == "production" && isset($this->CS->bms[$this->CS->bmsid]["options"]["prodopaclink"]) && $this->CS->bms[$this->CS->bmsid]["options"]["prodopaclink"] )
        {
          $OPACLINK = $this->CS->bms[$this->CS->bmsid]["options"]["prodopaclink"];
        }
        elseif ( MODE != "production" && isset($this->CS->bms[$this->CS->bmsid]["options"]["testopaclink"]) && $this->CS->bms[$this->CS->bmsid]["options"]["testopaclink"] )
        {
          $OPACLINK = $this->CS->bms[$this->CS->bmsid]["options"]["testopaclink"];
        }
        if ( $DB && $OPACLINK )
        {
          $Texts["g"][] = array("type" => "head",
                                "text" => "");
          $Texts["g"][] = array("type" => "link",
                                "text" => "Zum OPAC ",
                                "link" => $OPACLINK . "/DB=" . $DB . "/XMLPRS=N/PPN?PPN=" . $this->id);
          $Texts["e"][] = array("type" => "head",
                                "text" => "");
          $Texts["e"][] = array("type" => "link",
                                "text" => "To the OPAC ",
                                "link" => $OPACLINK . "/DB=" . $DB . "/XMLPRS=N/PPN?PPN=" . $this->id);
        }
        return $Texts;
      }

      // o.signatures
      case "signatures":
      {
        return $this->getSignatureStockComment(true, false, false);
      }

      // o.signaturestocks
      case "signaturestocks":
      {
        return $this->getSignatureStockComment(true, true, false);
      }

      // o.signaturecomments
      case "signaturecomments":
      {
        return $this->getSignatureStockComment(true, false, true);
      }

      // o.signaturestockcomments
      case "signaturestockcomments":
      {
        return $this->getSignatureStockComment(true, true, true);
      }

      // o.stocks
      case "stocks":
      {
        return $this->getSignatureStockComment(false, true, false);
      }

      // o.stockcomments
      case "stockcomments":
      {
        return $this->getSignatureStockComment(false, true, true);

      }

      // o.comments
      case "comments":
      {
        return $this->getSignatureStockComment(false, false, true);
      }

      // o.usagecode
      case "usagecode":
      {
        $Rec = $this->getMarcSubFieldArray("M980", "u");
        foreach ( $Rec as $UC )
        {
          if ( !trim($UC) )  continue;

          $L   = $this->CS->getTextBothLang("usagecode.".strtolower(trim($UC)));
          if ( !is_array($L) || !count($L) )  return array();

          if (substr($L["german"],0,4) == "http" )
          {
            $T   = $this->CS->getTextBothLang("campusnetwork");
            if ( !is_array($T) || !count($T) )  return array();
            $Texts["g"][] = array("type" => "text",
                                  "text" => $T["german"]);
            $Texts["e"][] = array("type" => "text",
                                  "text" => $T["english"]);

            $T   = $this->CS->getTextBothLang("usagecode");
            if ( !is_array($T) || !count($T) )  return array();
            $Texts["g"][] = array("type" => "link",
                                  "text" => $T["german"],
                                  "link" => $L["german"]);
            $Texts["e"][] = array("type" => "link",
                                  "text" => $T["english"],
                                  "link" => $L["english"]);
          }
          else
          {
            $Texts["g"][] = array("type" => "text",
                                  "text" => $L["german"]);
            $Texts["e"][] = array("type" => "text",
                                  "text" => $L["english"]);
          }
        }
        return $Texts;
      }

      // o.vscout
      case "vscout":
      {
        // $this->CS->debug("ALX", $this->CS->configs["other"]);
        $VSLink  = ( isset($this->CS->configs["other"]["vscout.link"]) && $this->CS->configs["other"]["vscout.link"] )
                   ? $this->CS->configs["other"]["vscout.link"]
                   : "";
        $VSWhite = ( isset($this->CS->configs["other"]["vscout.whitelist"]) && $this->CS->configs["other"]["vscout.whitelist"] )
                   ? (array) json_decode($this->CS->configs["other"]["vscout.whitelist"], true)
                   : array();
        $VSBlack = ( isset($this->CS->configs["other"]["vscout.blacklist"]) && $this->CS->configs["other"]["vscout.blacklist"] )
                   ? (array) json_decode($this->CS->configs["other"]["vscout.blacklist"], true)
                   : array();


        // $this->CS->debug("ALX", $VSLink);
        // $this->CS->debug("ALX", $VSWhite);
        // $this->CS->debug("ALX", $VSBlack);
        // $this->CS->debug("ALX", $Item);

        $ItemILNLoc = ( isset($Item["2"][0]) && $Item["2"][0]
                     && isset($Item["f"][0]) && $Item["f"][0] ) ? trim($Item["2"][0]) . ":" . trim($Item["f"][0]) : "";
        $ItemSig    = ( isset($Item["d"][0]) && $Item["d"][0] ) ? trim($Item["d"][0])                             : "";


        $Found = false;

        // Whitelist to turn "true"
        foreach ($VSWhite as $I => $Block)
        {
          $FoundKeys = 0;
          foreach ($Block as $Key => $Val)
          {
            // $this->CS->debug("ALX", $Key);
            // $this->CS->debug("ALX", $Val);
            // $this->CS->debug("ALX", $Item[$Key]);
            if ( isset($Item[$Key]) )
            {
              if ( substr($Val, -1) == "*" )
              {
                $Pos = strpos($Val, "*");
                $Mul = false;
                foreach ( $Item[$Key] as $IV )
                {
                  if ( $Mul ) continue;
                  if ( substr($Val, 0, $Pos) == substr($IV,0, $Pos) )
                  {
                    $FoundKeys++;
                    $Mul = true;
                  }
                }
              }
              else
              {
                if ( isset($Item[$Key]) && in_array($Val, $Item[$Key] ) ) $FoundKeys++;
              }
            }
          }
          if ( count($Block) == $FoundKeys )
          {
            $Found = true;
            break;
          }
        }
        // $this->CS->debug("ALX", $FoundKeys);

        if ( !$Found ) return $Texts;


        // Blacklist to turn "false"
        foreach ($VSBlack as $I => $Block)
        {
          $FoundKeys = 0;
          foreach ($Block as $Key => $Val)
          {
            if ( isset($Item[$Key]) )
            {
              if ( substr($Val, -1) == "*" )
              {
                $Pos = strpos($Val, "*");
                foreach ( $Item[$Key] as $IV )
                {
                  if ( substr($Val, 0, $Pos) == substr($IV,0, $Pos) ) $FoundKeys++;
                }
              }
              else
              {
                if ( isset($Item[$Key]) && in_array($Val, $Item[$Key] ) ) $FoundKeys++;
              }
            }
          }
          if ( count($Block) == $FoundKeys )
          {
            $Found = false;
            break;
          }
        }
        if ( !$Found ) return $Texts;


        // Achtung Elemente trimmen!!

        if ( $VSLink && $ItemSig )
        {
          $Texts["g"][] = array("type" => "head",
                                "text" => "");
          $Texts["g"][] = array("type" => "link",
                                "text" => "Ihr Weg zum Medium ",
                                "link" => str_replace("{sig}", $ItemSig, $VSLink));
          $Texts["e"][] = array("type" => "head",
                                "text" => "");
          $Texts["e"][] = array("type" => "link",
                                "text" => "Your path to the medium ",
                                "link" => str_replace("{sig}", $ItemSig, $VSLink));
        }
        return $Texts;
      }

      case "rostockub":
      {
        if ( isset($More[0]) && $More[0] == "cdrom" )
        {
          if ( isset($Item["remoteitems"][0]["href"]) && trim($Item["remoteitems"][0]["href"]) )
          {
            $T   = $this->CS->getTextBothLang("cdrom");
            $Texts["g"][] = array("type" => "link",
                                  "text" => $T["german"],
                                  "link" => trim($Item["remoteitems"][0]["href"]));
            $Texts["e"][] = array("type" => "link",
                                  "text" => $T["english"],
                                  "link" => trim($Item["remoteitems"][0]["href"]));
          }
          // $this->CS->debug("ALX", $Item);
        }
      }      
      return $Texts;
    }
  }

  private function getFOLIOElement($Item, $Text)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // $this->CS->debug("AUEW254", $Item);
    // $this->CS->debug("AUEW255", $Text);

    $Texts = array("g" => array(), "e" => array());

    switch ( $Text )
    {
      // f.status
      case 'status':
      {
        if ( isset($Item["status"]) && $Item["status"] )
        {
          $T   = $this->CS->getTextBothLang("foliostatus." . $Item["status"]);
          if ( !is_array($T) || !count($T) )  return array();
          $Texts["g"][] = array("type" => "text",
                                "text" => "FOLIO-Status: " . $T["german"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => "FOLIO-State: ". $T["english"]);
        }
        break;
      }
    }
    return $Texts;
  }

  private function getPAIAElement($Item, $Text)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // $this->CS->debug("AUEW254", $Item);
    // $this->CS->debug("AUEW255", $Text);

    $Texts = array("g" => array(), "e" => array());

    switch ($Text)
    {
      // p.department
      case 'department':
      {
        if ( isset($Item["department"]) && $Item["department"] )
        {
          $Texts["g"][] = array("type" => "text",
                                "text" => strip_tags($Item["department"]));
          $Texts["e"][] = array("type" => "text",
                                "text" => strip_tags($Item["department"]));
        }
        break;
      }
      // p.label
      case 'label':
      {
        if ( isset($Item["label"]) && $Item["label"] )
        {
          $Texts["g"][] = array("type" => "text",
                                "text" => $Item["label"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $Item["label"]);
        }
        break;
      }
      // p.signature
      case 'signature':
      {
        if ( isset($Item["label"]) )
        {
          $Div = $this->CS->client == "GoettingenSUB" ? "::" : ":";

          $Tmp = array_filter(explode($Div, $Item["label"]));

          if ( count($Tmp) > 1)  array_shift($Tmp);
          $Sig = implode($Div, $Tmp);
          $this->addArr2Arr($this->signature, array($Sig));

          $Array  = array("d" => array($Sig));
          $Return = $this->modifySignatureLocal($Array);
          if ( isset($Return["status"]) && $Return["status"] ) return $Return["values"];
        }
        break;
      }
      // p.storage
      case 'storage':
      {
        if ( isset($Item["storage"]) && $Item["storage"] )
        {
          $Texts["g"][] = array("type" => "text",
                                "text" => $Item["storage"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $Item["storage"]);
        }
        break;
      }
      // p.storagelink
      case 'storagelink':
      {
        if ( isset($Item["storage"]) && $Item["storage"]
          && isset($Item["storagelink"]) && $Item["storagelink"] )
        {
          // $this->CS->debug("ALX7373", $Item);
          $Texts["g"][] = array("type" => "link",
                                "text" => $Item["storage"] . " ",
                                "link" => $Item["storagelink"]);
          $Texts["e"][] = array("type" => "link",
                                "text" => $Item["storage"] . " ",
                                "link" => $Item["storagelink"]);
        }
        break;
      }
      // p.about
      case 'about':
      {
        if ( isset($Item["about"]) && $Item["about"] )
        {
          $Black = explode("|", $this->CS->configs["item"]["commentblacklist"]);
          if (in_array($Item["about"], $Black)) return array();
          $this->addArr2Arr($this->comment, array($Item["about"]));
          $Texts["g"][] = array("type" => "text",
                                "text" => $Item["about"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $Item["about"]);
        }
        break;
      }
      // p.aboutfirstline
      case 'aboutfirstline':
      {
        if ( isset($Item["about"]) && $Item["about"] )
        {
          $Tmp = preg_split("/\r\n|\n|\r/", $Item["about"]);
          $Tmp = $Tmp[0];

          $Black = explode("|", $this->CS->configs["item"]["commentblacklist"]);
          if (in_array($Tmp, $Black)) return array();
          $this->addArr2Arr($this->comment, array($Tmp));

          $Texts["g"][] = array("type" => "text",
                                "text" => $Tmp);
          $Texts["e"][] = array("type" => "text",
                                "text" => $Tmp);
        }
        break;
      }
      // p.queue
      case 'queue':
      {
        $Tmp = array();
        if ( isset($Item["loanitems"][0]["queue"])         && $Item["loanitems"][0]["queue"]         != "-" ) $Tmp[] =  $Item["loanitems"][0]["queue"];
        if ( isset($Item["presentationitems"][0]["queue"]) && $Item["presentationitems"][0]["queue"] != "-" ) $Tmp[] =  $Item["presentationitems"][0]["queue"];
        // $Tmp = array_filter($Tmp);  // Otherwise zero will be filtered and not shown
        if ( count($Tmp) )
        {
          $T   = $this->CS->getTextBothLang("items.queue");
          if ( !is_array($T) || !count($T) )  return array();
          $Texts["g"][] = array("type" => "text",
                                "text" => $T["german"] . ": " . $Tmp[0]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $T["english"] . ": " . $Tmp[0]);
        }
        break;
      }
      // p.expected
      case 'expected':
      {
        $Tmp = array();
        if ( isset($Item["loanitems"][0]["expected"])         && $Item["loanitems"][0]["expected"]         != "-" ) $Tmp[] =  $Item["loanitems"][0]["expected"];
        if ( isset($Item["presentationitems"][0]["expected"]) && $Item["presentationitems"][0]["expected"] != "-" ) $Tmp[] =  $Item["presentationitems"][0]["expected"];
        $Tmp = array_filter($Tmp);
        if ( count($Tmp) )
        {
          $T   = $this->CS->getTextBothLang("items.expected");
          if ( !is_array($T) || !count($T) )  return array();
          $Texts["g"][] = array("type" => "text",
                                "text" => $T["german"] . ": " . $Tmp[0]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $T["english"] . ": " . $Tmp[0]);
        }
        break;
      }
      // p.chronology
      case "chronology":
      {
        if ( isset($Item["chronology"]) && $Item["chronology"] )
        {
          $T   = $this->CS->getTextBothLang("items.stock");
          if ( !is_array($T) || !count($T) )  return array();
          $this->addArr2Arr($this->stock, array($Item["chronology"]));

          $Texts["g"][] = array("type" => "text",
                                "text" => $T["german"] . ": " . $Item["chronology"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $T["english"] . ": " . $Item["chronology"]);
        }
        break;
      }
      // p.epn
      case "epn":
      {
        if ( isset($Item["epn"]) && $Item["epn"] )
        {
          $T   = $this->CS->getTextBothLang("items.epn");
          if ( !is_array($T) || !count($T) )  return array();
          $Texts["g"][] = array("type" => "text",
                                "text" => $T["german"] . ": " . $Item["epn"]);
          $Texts["e"][] = array("type" => "text",
                                "text" => $T["english"] . ": " . $Item["epn"]);
        }
        break;
      }
    }
    return $Texts;
  }

  // ************* M E T A *******************

  // Thekenwahl
  private function addDeskUsers($Loc, $Item, &$Exemplar = array())
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // $this->CS->debug("ALX73", $Exemplar);

    $Desk  = "";
    $User  = "";
    $Mode  = ( isset($Exemplar["action"]["request"]["type"]) ) ? $Exemplar["action"]["request"]["type"] : "";

    if ( isset($Exemplar["action"]["deskgroup"]) && $Exemplar["action"]["deskgroup"]
      && isset($Exemplar["action"]["usergroup"]) && $Exemplar["action"]["usergroup"] )
    {
      $Desk = $Exemplar["action"]["deskgroup"];
      $User = $Exemplar["action"]["usergroup"];
      unset($Exemplar["action"]["deskgroup"]);
      unset($Exemplar["action"]["usergroup"]);
    }
    elseif ( $Mode && isset($this->CS->locations[$Mode]) && count($this->CS->locations[$Mode]) )
    {
      if ( isset($Item["storageid"]) && $Item["storageid"] != "-" )
      {
        $StorID = strtolower($Item["storageid"]);
        $AUI    = isset($Item["e"][0]) ? $Item["e"][0] : "";

        // Use direct Loc
        if ( isset($this->CS->locations[$Mode][$Loc][$StorID]) )
        {
          if ( $AUI && isset($this->CS->locations[$Mode][$Loc][$StorID][$AUI]["deskgroup"]) &&
                       isset($this->CS->locations[$Mode][$Loc][$StorID][$AUI]["usergroup"]) )
          {
            $Desk = $this->CS->locations[$Mode][$Loc][$StorID][$AUI]["deskgroup"];
            $User = $this->CS->locations[$Mode][$Loc][$StorID][$AUI]["usergroup"];
          }
          elseif ( isset($this->CS->locations[$Mode][$Loc][$StorID]["deskgroup"]) &&
                   isset($this->CS->locations[$Mode][$Loc][$StorID]["usergroup"]) )
          {
            $Desk = $this->CS->locations[$Mode][$Loc][$StorID]["deskgroup"];
            $User = $this->CS->locations[$Mode][$Loc][$StorID]["usergroup"];
          }
        }
        else
        {
          // Use group loc
          if ( isset($this->CS->locations["loc2group"][$Loc]) )
          {
            $Loc2 = $this->CS->locations["loc2group"][$Loc];
            if ( isset($this->CS->locations[$Mode][$Loc2][$StorID]) )
            {
              if ( $AUI && isset($this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["deskgroup"])
                        && isset($this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["usergroup"]) )
              {
                $Desk = $this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["deskgroup"];
                $User = $this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["usergroup"];
              }
              elseif ( isset($this->CS->locations[$Mode][$Loc2][$StorID]["deskgroup"]) &&
                       isset($this->CS->locations[$Mode][$Loc2][$StorID]["usergroup"]) )
              {
                $Desk = $this->CS->locations[$Mode][$Loc2][$StorID]["deskgroup"];
                $User = $this->CS->locations[$Mode][$Loc2][$StorID]["usergroup"];
              }
            }
          }
          else
          {
            // Use child loc
            if ( isset($this->CS->locations["group2loc"][$Loc]) )
            {
              foreach ($this->CS->locations["group2loc"][$Loc] as $Loc2)
              {
                if ( isset($this->CS->locations[$Mode][$Loc2][$StorID]) )
                {
                  if ( $AUI && isset($this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["deskgroup"])
                            && isset($this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["usergroup"]) )
                  {
                    $Desk = $this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["deskgroup"];
                    $User = $this->CS->locations[$Mode][$Loc2][$StorID][$AUI]["usergroup"];
                  }
                  elseif ( isset($this->CS->locations[$Mode][$Loc2][$StorID]["deskgroup"]) &&
                           isset($this->CS->locations[$Mode][$Loc2][$StorID]["usergroup"]) )
                  {
                    $Desk = $this->CS->locations[$Mode][$Loc2][$StorID]["deskgroup"];
                    $User = $this->CS->locations[$Mode][$Loc2][$StorID]["usergroup"];
                  }
                  break;
                }
              }
            }
          }
        }
      }
    }

    $Desks = ( $Desk && isset($this->CS->locations["deskgroups"][$Desk]) ) ? $this->CS->locations["deskgroups"][$Desk] : array();
    $Users = ( $User && isset($this->CS->locations["usergroups"][$User]) ) ? $this->CS->locations["usergroups"][$User] : array();

    return array("desks" => $Desks,  "users" => $Users);
  }

  // Signaturen, Bestände & Kommentare
  private function getSignatureStockComment($Sig, $Sto, $Com)
  {
    // Must be taken from childid
    // Not working:
    // $Recs = $this->getMarcSubFieldsArray("M773", array("g", "q"));
    // $this->CS->results["marc"][$this->id]["M773"]

    $SSCs = array();

    // $this->CS->debug("ALX", $Sto);
    // $this->CS->debug("ALX", $this->one["marc"]);

    if ( $Sto )
    {
      if ( isset($this->one["marc"]["M773"]) && is_array($this->one["marc"]["M773"]) )
      {
        $Recs   = $this->one["marc"]["M773"];
        foreach ( $Recs as $Rec )
        {
          if ( !isset($Rec["7"][0]) || !in_array($Rec["7"][0], array("nnnm", "nnns")) ) continue;

          $SC     = array("signature" => array(), "stock" => array(), "comment" => array());
          if ( $Sto && isset($Rec["g"]) && is_array($Rec["g"]) && count($Rec["g"]) )
          {
            $SC["stock"] = $this->getMARCElement($Rec, "stock");
          }
          if ( count($SC["stock"]) ) $SSCs[] = $SC;
        }
      }
    }

    if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
    {
      $Recs   = $this->one["marc"]["M980"];
      foreach ( $Recs as $Rec )
      {
        $SC        = array("signature" => array(), "stock" => array(), "comment" => array());

        if ( $Sig && isset($Rec["d"]) && is_array($Rec["d"]) && count($Rec["d"]) )
        {
          $SC["signature"] = $this->getMARCElement($Rec, "signature");
        }
        if ( $Sto && isset($Rec["g"]) && is_array($Rec["g"]) && count($Rec["g"]) )
        {
          $SC["stock"] = $this->getMARCElement($Rec, "stock");
        }
        if ( $Com && isset($Rec["k"]) && is_array($Rec["k"]) && count($Rec["k"]) )
        {
          $SC["comment"]   = $this->getMARCElement($Rec, "comment");
        }
        if ( count($SC["signature"]) || count($SC["stock"]) || count($SC["comment"]) ) $SSCs[] = $SC;
      }
    }

    if ( $this->useParent() )
    {
      if ( isset($this->CS->works["add"][$this->pid]["marc"]["PM980"]) && is_array($this->CS->works["add"][$this->pid]["marc"]["PM980"]) )
      {
        $Recs   = $this->CS->works["add"][$this->pid]["marc"]["PM980"];
        foreach ( $Recs as $Rec )
        {
          $SC        = array("signature" => array(), "stock" => array(), "comment" => array());

          if ( $Sig && isset($Rec["d"]) && is_array($Rec["d"]) && count($Rec["d"]) )
          {
            $SC["signature"] = $this->getMARCElement($Rec, "signature");
          }
          if ( $Sto && isset($Rec["g"]) && is_array($Rec["g"]) && count($Rec["g"]) )
          {
            $SC["stock"] = $this->getMARCElement($Rec, "stock");
          }
          if ( $Com && isset($Rec["k"]) && is_array($Rec["k"]) && count($Rec["k"]) )
          {
            $SC["comment"]   = $this->getMARCElement($Rec, "comment");
          }
          if ( count($SC["signature"]) || count($SC["stock"]) || count($SC["comment"]) ) $SSCs[] = $SC;
        }
      }
    }

    // $this->CS->debug("ALX2S", $SSCs);

    // Make multidimensional array unique
    // $SSCs  = $this->getUniqueMultArray($SSCs);
    // $this->CS->debug("ALX22", $SSCs);
    $Texts = array("g" => array(), "e" => array());
    if ( is_array($SSCs) && count($SSCs) )
    {
      // $this->CS->debug("ALX2", $SSCs);
      foreach ($SSCs as $SSC)
      {
        if ( isset($SSC["signature"]) && is_array($SSC["signature"]) && count($SSC["signature"]) )
        {
          if ( !isset($SSC["signature"]["g"]) || !isset($SSC["signature"]["e"]) ) continue;
          $Texts["g"] = [...$Texts["g"],
                         ...$SSC["signature"]["g"]];
          $Texts["e"] = [...$Texts["e"],
                         ...$SSC["signature"]["e"]];
        }
        if ( isset($SSC["stock"]) && is_array($SSC["stock"]) && count($SSC["stock"]) )
        {
          if ( !isset($SSC["stock"]["g"]) || !isset($SSC["stock"]["e"]) ) continue;
          $Texts["g"] = [...$Texts["g"],
                         ...$SSC["stock"]["g"]];
          $Texts["e"] = [...$Texts["e"],
                         ...$SSC["stock"]["e"]];
        }
        if ( isset($SSC["comment"]) && is_array($SSC["comment"]) && count($SSC["comment"]) )
        {
          if ( !isset($SSC["comment"]["g"]) || !isset($SSC["comment"]["e"]) ) continue;
          $Texts["g"] = [...$Texts["g"],
                         ...$SSC["comment"]["g"]];
          $Texts["e"] = [...$Texts["e"],
                         ...$SSC["comment"]["e"]];
        }
        $Texts["g"][] = array("type" => "space");
        $Texts["e"][] = array("type" => "space");
      }
    }

    // $this->CS->debug("ALX3E", $Texts);
    return $Texts;
  }

  // $Location wird nur vom Baustein m.locationsignature gesetzt. Ist er leer,
  // arbeitet die Funktion unveraendert wie bisher (m.signature).
  private function modifySignatureLocal(&$Item, $Location = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $File                    = dirname(__FILE__) . DIRECTORY_SEPARATOR . "clients"
                               . DIRECTORY_SEPARATOR . $this->CS->client
                               . DIRECTORY_SEPARATOR . "signature.php";
    $Status                  = true;
    $Vs                      = array();
    if ( file_exists($File) )
    {
      // Library rules
      include $File;
    }
    else
    {
      // Common rules
      $Vals = array();
      foreach ($Item["d"] as $Key => $Val)
      {
        if ( $Key > 0 )         continue;
        if ( $Val == "--%%--" ) continue;
        $Vals[] = $Val;
      }
      if ( is_array($Vals) && count($Vals) )
      {
        $this->addArr2Arr($this->signature, $Vals);
        $T  = $this->CS->getTextBothLang("items.signature");
        $Vs["g"][] = array("type" => "text",
                           "text" => $T["german"] . ": " .  implode(", ", $Vals));
        $Vs["e"][] = array("type" => "text",
                           "text" => $T["english"] . ": " .  implode(", ", $Vals));
        // $this->CS->debug("ALXITEM", $Vs);
      }
      else
      {
        $Status = false;
      }
    }

    // Standortvariante (m.locationsignature, 02.09.2026).
    //
    // Sie setzt die Ausgabe aus DENSELBEN Werten neu zusammen und haengt an
    // jede Signatur vorne "<Standort>:". Damit gelten die Regeln der Bibliothek aus
    // clients/<Client>/signature.php auch hier - sie hinterlassen ihre
    // gefilterte Liste in $Vals, und genau daran wird weitergearbeitet.
    if ( $Location !== "" )
    {
      if ( isset($Vals) && is_array($Vals) && count($Vals) )
      {
        $Vs   = array();
        $Locs = array();
        foreach ( $Vals as $Val )  $Locs[] = $Location . ":" . $Val;
        $T    = $this->CS->getTextBothLang("items.signature");
        $Vs["g"][] = array("type" => "text",
                           "text" => $T["german"]  . ": " . implode(", ", $Locs));
        $Vs["e"][] = array("type" => "text",
                           "text" => $T["english"] . ": " . implode(", ", $Locs));
      }
      else
      {
        $Status = false;
      }
    }

    // Signaturwert fett - HIER und nicht in den einzelnen Regeln oben:
    // durch diese Stelle laeuft sowohl die allgemeine Regel als auch jede
    // bibliothekseigene aus clients/<Client>/signature.php, damit sieht die
    // Ausgabe ueberall gleich aus (2026-08-25 gewuenscht).
    //
    // Ausgezeichnet wird der Teil HINTER dem Etikett ("Signatur: **GC 5927**").
    // Fehlt das Etikett - eine eigene Regel muss es nicht setzen -, wird der
    // ganze Text fett.
    $Labels = $this->CS->getTextBothLang("items.signature");
    foreach ( array("g" => "german", "e" => "english") as $Lang => $Key )
    {
      if ( !isset($Vs[$Lang]) || !is_array($Vs[$Lang]) )  continue;
      foreach ( $Vs[$Lang] as $Idx => $Val )
      {
        if ( !isset($Val["type"]) || $Val["type"] != "text" )  continue;
        if ( !isset($Val["text"]) )                            continue;
        $Vs[$Lang][$Idx]["text"] = $this->CS->markValueBold($Val["text"],
                                                            isset($Labels[$Key]) ? $Labels[$Key] : "");
      }
    }

    // $this->CS->debug("ALXu3z", array("status" => $Status, "values" => $Vs));
    return array("status" => $Status, "values" => $Vs);
  }

  private function getItemDataLocal(&$Item, &$Exemplar)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Library rules

    // - Functions
    $File                    = dirname(__FILE__) . DIRECTORY_SEPARATOR . "clients"
                               . DIRECTORY_SEPARATOR . $this->CS->client
                               . DIRECTORY_SEPARATOR . "itemfunctions.php";
    if ( file_exists($File) )
    {
      include_once($File);
    }

    // - Item Modifications
    $File                    = dirname(__FILE__) . DIRECTORY_SEPARATOR . "clients"
                               . DIRECTORY_SEPARATOR . $this->CS->client
                               . DIRECTORY_SEPARATOR . "item.php";
    if ( file_exists($File) )
    {
      include($File);
    }
  }

  private function getGroupComment($ILN)
  {
    $Comments = array();
    if ( isset($this->one["marc"]["M986"]) && is_array($this->one["marc"]["M986"]) )
    {
      foreach ( $this->one["marc"]["M986"] as $Rec )
      {
        if ( isset($Rec["2"][0]) && $Rec["2"][0] == $ILN && isset($Rec["b"][0]) )  $Comments[] = $Rec["b"][0];
      }
    }
    return $Comments;
  }

  // ************* M A I N *******************

  // Gruppen bestimmen
  private function setCombs()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Combs = array();
    $Locs  = array();
    $Loc   = "";
    $EPN   = 0;
    $Skip  = false;

    // Configs
    $MLocs = ( isset($this->CS->configs["item"]["multiplelocations"])   && $this->CS->configs["item"]["multiplelocations"]  == "y" )
             ? true : false;
    $MKeys = ( isset($this->one["items"]) && count($this->one["items"]) )
             ? array_keys($this->one["items"]) : array();

    // $this->CS->debug("ALXCMB2", $this->pid);
    // $this->CS->debug("ALXCMB2", $this->awop);
    // $this->CS->debug("ALXCMB2", $MKeys);
    // $this->CS->debug("ALXCMB2", $this->CS->works);

    if ( $this->pid && !$this->awop && !count($MKeys) && isset($this->CS->works["add"][$this->pid]) )
    {
      // Bibliotheken mit Artikel-Parentshift bekommen keinen Shift durch einen
      // anderen Mechanismus (17.08.2026).
      if ( $this->aps )
      {
        // Nur der Shift selbst zieht die Exemplare des Elternteils heran, und
        // nur fuer haptische Artikel. Alles andere behaelt seine eigenen.
        if ( $this->isHapticArticle() )
        {
          $this->one["items"] = $this->CS->works["add"][$this->pid]["items"];
          $MKeys     = ( isset($this->one["items"]) && count($this->one["items"]) )
                       ? array_keys($this->one["items"]) : array();
        }
      }
      else
      {
        // Alte Mechanik, unveraendert: ohne eigene Exemplare die des
        // Elternteils erben (Baende mehrbaendiger Werke).
        $MKeys     = ( isset($this->CS->works["add"][$this->pid]["items"]) && count($this->CS->works["add"][$this->pid]["items"]) )
                   ? array_keys($this->CS->works["add"][$this->pid]["items"])
                   : array();
      }
    }

    $PKeys = ( isset($this->CS->paias[$this->id])
            && count($this->CS->paias[$this->id]) )  ? array_keys($this->CS->paias[$this->id]) : array();

    // $this->CS->debug("ALXCMB2", $PKeys);

    // Merge items info comb
    $AllItems   = array_unique(array_merge($MKeys, $PKeys));
    // $this->CS->debug("ALX32", $AllItems);
    foreach ($AllItems as $LukidaID )
    {
      // Get Paia
      $Combs[$LukidaID] = (isset($this->CS->paias[$this->id][$LukidaID])) ? $this->CS->paias[$this->id][$LukidaID] : array();
      if ( !isset($Combs[$LukidaID]["about"]) ) $Combs[$LukidaID]["about"] = "";      // Ensure about in PAIA answer

      // Get bandlist-safe EPN
      $EPN = (isset($Combs[$LukidaID]["epn"])) ? $Combs[$LukidaID]["epn"] : 0;  // Must: Numeric for incrementing

      // Add Marc
      if ( $EPN != 0 )
      {
        if (isset($this->one["items"][$EPN])) $Combs[$LukidaID] += $this->one["items"][$EPN];
      }
      else
      {
        if ( isset($this->one["items"][$LukidaID]) )
        {
          $Combs[$LukidaID] += $this->one["items"][$LukidaID];
        }
        else
        {
          if ( $this->useParent() && isset($this->CS->works["add"][$this->pid]["items"][$LukidaID]) )
          {
            $Combs[$LukidaID] += $this->CS->works["add"][$this->pid]["items"][$LukidaID];
          }
        }
      }
      // $this->CS->debug("ALX33", $Combs[$LukidaID]);
    }

    // $this->CS->debug("ALX33", $Combs);

    // Determine location on multiple locations
    if ( count($Combs) )
    {
      foreach ($Combs as $ExpID => $Item)
      {
        // Global online item
        if ( isset($Item["loc"]) && $Item["loc"] == "onlineglobal" )
        {
          $Item["loc"] = "online";
          $Loc = $Item["loc"];
        }
        elseif ( !$MLocs && isset($Item["loc"]) && $Item["loc"] == "onlinelocal" )
        {
          // Local online item (single location)
          $Item["loc"] = "online";
          $Loc = $Item["loc"];
        }
        elseif ( $MLocs  )
        {
          // Local online item (multiple location)
          if ( isset($Item["loc"]) && $Item["loc"] == "onlinelocal"
            && isset($this->CS->locations["onlineids"]) && is_array($this->CS->locations["onlineids"]) && count($this->CS->locations["onlineids"]) > 0
            && isset($Item["iln"]) && isset($this->CS->locations["onlineids"][$Item["iln"]]) )
          {
            $Loc = $this->CS->locations["onlineids"][$Item["iln"]];
          }
          // Local online item
          elseif ( isset($Item["loc"]) && $Item["loc"] == "onlinelocal" )
          {
            $Item["loc"] = "online";
            $Loc = $Item["loc"];
          }
          // Physical local items
          elseif ( isset($this->CS->locations["imp"]) && is_array($this->CS->locations["imp"]) && count($this->CS->locations["imp"]) > 0 )
          {
            $ItemILNLoc  = "";
            $ItemILNSig  = "";
            $ItemILNMan  = "";
            $ItemILNAUI  = "";
            if ( isset($Item["2"][0]) ) $ItemILNLoc  = $Item["2"][0];
            if ( isset($Item["2"][0]) ) $ItemILNSig  = $Item["2"][0];
            if ( isset($Item["2"][0]) ) $ItemILNMan  = $Item["2"][0];
            if ( isset($Item["2"][0]) ) $ItemILNAUI  = $Item["2"][0];
            $ItemILNLoc  .= ":";
            $ItemILNSig  .= ":";
            $ItemILNMan  .= "@";
            $ItemILNAUI  .= ":";
            if ( isset($Item["f"][0]) ) $ItemILNLoc .= $Item["f"][0];
            if ( isset($Item["d"][0]) ) $ItemILNSig .= $Item["d"][0];
            if ( isset($Item["y"][0]) ) $ItemILNMan .= substr($Item["y"][0],2);
            if ( isset($Item["e"][0]) ) $ItemILNAUI .= $Item["e"][0];

            $Loc = "";
            // Skip Locations
            if ( isset($this->CS->locations["skip"]["loc"]) && is_array($this->CS->locations["skip"]["loc"])
              && in_array($ItemILNLoc, $this->CS->locations["skip"]["loc"]) )
            {
              $Skips[] = $ExpID;
              continue;
            }
            // Skip Signatures
            elseif ( isset($this->CS->locations["skip"]["sig"]) && is_array($this->CS->locations["skip"]["sig"])
              && in_array($ItemILNSig, $this->CS->locations["skip"]["sig"]) )
            {
              $Skips[] = $ExpID;
              continue;
            }
            else
            {
              // $this->CS->debug("ALX23233", $this->CS->locations["exp"]);
              foreach ( $this->CS->locations["exp"] as $IL => $Conds )
              {
                // $this->CS->debug("ALX23233", $IL);
                // $this->CS->debug("ALX24", $Conds);

                $Found = array("loc" => false, "sig" => false, "man" => false, "aui" => false);
                // Part Location
                if ( isset($Conds["loc"]) && count($Conds["loc"]) )
                {
                  // Fester Wert, Wildcard (T*) oder regulaerer Ausdruck (^...) - base/_librarys/LocRule.php (15.09.2026)
                  foreach ( $Conds["loc"] as $O )
                  {
                    if ( LocRule::matches($O, $ItemILNLoc, ":") )  {$Found["loc"] = true; break;}
                  }
                  if ( !$Found["loc"] ) continue;
                }
                else
                {
                  $Found["loc"] = true;
                }
                // Part Signature
                if ( isset($Conds["sig"]) && count($Conds["sig"]) )
                {
                  // Fester Wert, Wildcard (T*) oder regulaerer Ausdruck (^...) - base/_librarys/LocRule.php (15.09.2026)
                  foreach ( $Conds["sig"] as $O )
                  {
                    if ( LocRule::matches($O, $ItemILNSig, ":") )  {$Found["sig"] = true; break;}
                  }
                  if ( !$Found["sig"] ) continue;
                }
                else
                {
                  $Found["sig"] = true;
                }
                // Part Mandant
                if ( isset($Conds["man"]) && count($Conds["man"]) )
                {
                  // Fester Wert, Wildcard (T*) oder regulaerer Ausdruck (^...) - base/_librarys/LocRule.php (15.09.2026)
                  foreach ( $Conds["man"] as $O )
                  {
                    if ( LocRule::matches($O, $ItemILNMan, "@") )  {$Found["man"] = true; break;}
                  }
                  if ( !$Found["man"] ) continue;
                }
                else
                {
                  $Found["man"] = true;
                }
                // $this->CS->debug("ALX25", $Found);

                // Part AUI
                if ( isset($Conds["aui"]) && count($Conds["aui"]) )
                {
                  // Fester Wert, Wildcard (T*) oder regulaerer Ausdruck (^...) - base/_librarys/LocRule.php (15.09.2026)
                  foreach ( $Conds["aui"] as $O )
                  {
                    if ( LocRule::matches($O, $ItemILNAUI, ":") )  {$Found["aui"] = true; break;}
                  }
                  if ( !$Found["aui"] ) continue;
                }
                else
                {
                  $Found["aui"] = true;
                }
                // Final Check
                if ($Found["loc"] && $Found["sig"] && $Found["man"]&& $Found["aui"])
                {
                  $Loc = $IL;
                  break;
                }
              }
              if ( $Loc == "" ) $Loc = "default";
            }
          }
          else
          {
            $Loc = "default";
          }
        }
        else
        {
          $Loc = "default";
        }

        // $this->CS->debug("ALXLOC", $ExpID . " -> " . $Loc);

        if ( $this->CS->client == "PotsdamUB" && isset($Item["label"]) && strlen(trim($Item["label"])) == 4 )
        {
          // PAIA Antwort soll Vorrang haben aufgrund internen Verlegungen (nur innerhalb OUS-Verleihmodul)
          // $this->CS->debug("ALX344", $Item);
          $Switch = array("1080" => "11frh",
                          "1180" => "09frh",
                          "2080" => "golm",
                          "3080" => "bbb",
                          "4080" => "10frh");
          if ( array_key_exists($Item["label"], $Switch) )  $Loc = $Switch[$Item["label"]];
          if ( !isset($Item["about"]) ) $Item["about"] = "";  // Important for sorting
        }

        $Combs[$ExpID]["loc"] = $Loc;
        if ( !isset($Combs[$ExpID]["about"]) ) $Combs[$ExpID]["about"] = "";
        $Locs[] = $Loc;
      }
      // $this->CS->debug("ALX36", $Combs);

      if ( (isset($this->CS->configs["item"]["sortabout"]) && $this->CS->configs["item"]["sortabout"] == "y") || $this->epn)
      {
        uasort($Combs, function ($a, $b) { return $b['about'] <=> $a['about']; });
      }
      $Locs   = array_values(array_unique($Locs));
    }

    // $this->CS->debug("ALX33", $this->id);
    // $this->CS->debug("ALX341", $Locs);
    // $this->CS->debug("ALX342", $Combs);

    // $this->CS->debug("ALX342", array_column($Combs, "b"));
    // $this->CS->debug("ALX342", array_column($Combs, "1"));
    // $this->CS->debug("ALX342", array_column($Combs, "about"));

    // $this->CS->debug("ALX343",  $this->CS->locations["hiersort"]);

    // Sort groups based on location interface sorts
    if ( isset($this->CS->locations["hiersort"]) && is_array($this->CS->locations["hiersort"]) && count($this->CS->locations["hiersort"])
      && isset($Locs)                            && is_array($Locs)                            && count($Locs)
      && isset($Combs)                           && is_array($Combs)                           && count($Combs) )
    {
      $TmpC = array();
      $TmpL = array();
      foreach ( $this->CS->locations["hiersort"] as $Sort )
      {
        if (isset($Sort["id"]) && in_array($Sort["id"], $Locs) )
        {
          // $Key = array_search($Sort["id"], array_column($Combs, 'loc'));

          foreach ( $Combs as $EPN => $C )
          {
            if ( isset($C["loc"]) && $C["loc"] == $Sort["id"] )
            {
              $TmpC[$EPN] = $C;
              if ( !in_array($C["loc"], $TmpL) )  $TmpL[] = $C["loc"];
            }
          }

          // $this->CS->debug("ALX56", $Sort["id"]);
          // $this->CS->debug("ALX57", $Tmpa);
        }
      }

      $MissingLocs = array_diff($Locs, $TmpL);
      if ( count($MissingLocs) )
      {
        foreach ( $MissingLocs as $Miss )
        {
          foreach ( $Combs as $EPN => $C )
          {
            if ( isset($C["loc"]) && $C["loc"] == $Miss )
            {
              $TmpC[$EPN] = $C;
              if ( !in_array($C["loc"], $TmpL) )  $TmpL[] = $C["loc"];
            }
          }

          // $this->CS->debug("ALX56", $Sort["id"]);
          // $this->CS->debug("ALX57", $Tmpa);
        }
      }


      // $this->CS->debug("ALX57", $MissingLocs);
      // $this->CS->debug("ALX57", $TmpC);
      // $this->CS->debug("ALX57", $TmpL);
      $Combs = $TmpC;
      $Locs  = $TmpL;
    }

    if ( !is_array($this->one) ) $this->one = array();
    $this->one["locs"]  = $Locs;
    $this->one["combs"] = $Combs;
    if ( $this->CS->display["comb"] ) $this->CS->results["comb"][$this->id] = $Combs;
    // $this->CS->debug("ALX35", $this->one);
  }

  // Pro Gruppe: Emeplare bestimmen
  private function setitems()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // $this->CS->debug("ALXLocss", $this->one["locs"]);
    // $this->CS->debug("ALXCombs", $this->one["combs"]);

    // Init vars
    $this->one["logs"]  = array();
    $Items  = array();
    $GIdx   = 0;

    foreach ( $this->one["locs"] as $Loc )
    {
      $Exps       = array();
      $this->best = "red";

      foreach ($this->one["combs"] as $Item)
      {
        // Skip wrong group items
        if ( !isset($Item["loc"]) || $Item["loc"] != $Loc ) continue;

        if ( isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" )   $this->location[] = $Item["f"][0];

        if ( isset($Item["link"]) )
        {
          $this->setLog("Link", "New " . $this->CS->getHost($Item["link"]));
        }
        else
        {
          $this->setLog("Item", "New EPN " . $Item["epn"]);
        }

        $ILN = isset($Item["2"][0]) ? $Item["2"][0] : "";
        // $this->CS->debug("ALX35", $Loc);
        // $this->CS->debug("ALX35", $Item["b"][0]);

        // $this->CS->debug("ALX55", $Item);
        $this->getExps($Exps, $this->id, $this->CS->cases, $Item);
      }

      if (count($Exps))
      {
        $Tmp = $this->CS->getTextBothLang("location." . $Loc);
        $Items["group_".++$GIdx] = array("code"       => $Loc,
                                         "label"      => $Loc,   // Can be removed May 2026
                                         "text"       => array("g" => array(array("type" => "text", "text" => $Tmp["german"])),
                                                               "e" => array(array("type" => "text", "text" => $Tmp["english"]))),
                                         "before"     => $this->getGroupComment($ILN),
                                         "groupitems" => $Exps,
                                         "light"      => $this->best,
                                         "after"      => array());
      }
    }

    // Für Wiko bei gedrehter Reihenfolge der Gruppen (alle anderen siehe unten)
    // Add more groups which are available from marc
    if ( !$this->epn &&  isset($this->CS->configs["other"]["groupsreverse"]) && $this->CS->configs["other"]["groupsreverse"] == 'y' )
    {
      $this->AddMoregroups($Items, $GIdx);
    }

    if ( !$this->epn )
    {
      // $this->CS->debug("ALX52", $this->one["itemcount"]);
      $this->setLog("Media", "New PPN " . $this->id);
      $Exps       = array();
      $Empty      = array();
      $this->best = "red";
      $this->getExps($Exps, $this->id, $this->CS->casesmedia, $Empty);

      // $this->CS->debug("ALX58", $Hits);
      // $this->CS->debug("ALX", $Exps);
      // $this->CS->debug("ALX", $this->logs);
      // Finalize first loop: location
      if (count($Exps))
      {
        $Tmp = $this->CS->getTextBothLang("location.media");
        $Items["group_".++$GIdx] = array("code"       => "media",
                                         "label"      => "media",   // Can be removed May 2026
                                         "text"       => array("g" => array(array("type" => "text", "text" => $Tmp["german"])),
                                                               "e" => array(array("type" => "text", "text" => $Tmp["english"]))),
                                         "before"     => array(),
                                         "groupitems" => $Exps,
                                         "light"      => $this->best,
                                         "after"      => array());
      }
    }

    // $this->CS->debug("AlX221", $Config);
    // $this->CS->debug("AlX222", $Data);
    // $this->CS->debug("ALX3", $Items);

    // return everything
    if ( $this->CS->display["logs"] ) $this->CS->results["logs"][$this->id] = $this->one["logs"];

    // Normale Reihenfolge der Gruppen (Wiko siehe oben)
    // Add more groups which are available from marc
    if ( !$this->epn &&  isset($this->CS->configs["other"]["groupsreverse"]) && $this->CS->configs["other"]["groupsreverse"] == 'n' )
    {
      $this->AddMoregroups($Items, $GIdx);
    }

    if ( isset($this->CS->configs["other"]["groupsreverse"]) && $this->CS->configs["other"]["groupsreverse"] == 'y' )
    {
      $Keys  = array_keys($Items);
      $Vals  = array_reverse(array_values($Items));
      $Items = array_combine($Keys, $Vals);
    }

    return $Items;
  }

  private function AddMoregroups(&$Items, &$GIdx)
  {
    $MGs = $this->setMoreGroups();
    // <m.980.b>$this->CS->debug("AlX222", $MGs);
    if ( isset($MGs["searches"]) && is_array($MGs["searches"]) && count($MGs["searches"]) )
    {
      $Items["group_".++$GIdx] = array("label"      => "searches",
                                       "before"     => array(),
                                       "groupitems" => $MGs["searches"],
                                       "light"      => "blue",
                                       "after"      => array());
    }
    if ( isset($MGs["links"]) && is_array($MGs["links"]) && count($MGs["links"]) )
    {
      $Items["group_".++$GIdx] = array("label"      => "links",
                                       "before"     => array(),
                                       "groupitems" => $MGs["links"],
                                       "light"      => "blue",
                                       "after"      => array());
    }
  }

  // Pro Gruppe: Zeilen aus ET
  private function getExps(&$Exps, &$ID, &$ScopeCases, &$Item)
  {
    $OldSortLevel  = "";
    $Hits          = array();

    $DAIA = (isset($Item["id"]) && trim($Item["id"]) != "" )                    ? true                                      : false;
    $LOAN = ($DAIA  && isset($Item["loan"]))                                    ? $Item["loan"]                             : false;
    $PRES = ($DAIA  && isset($Item["presentation"]))                            ? $Item["presentation"]                     : false;
    $OPEN = ($DAIA  && isset($Item["openaccess"]))                              ? $Item["openaccess"]                       : false;
    $REMO = ($DAIA  && isset($Item["remote"]))                                  ? $Item["remote"]                           : false;
    $INTR = ($DAIA  && isset($Item["interloan"]))                               ? $Item["interloan"]                        : false;
    $EXPD = ($DAIA  && isset($Item["loanitems"][0]["expected"])
                          && $Item["loanitems"][0]["expected"] != "-" )         ? $Item["loanitems"][0]["expected"]         : "";
    if ( !$EXPD ) $EXPD = ($DAIA  && isset($Item["presentationitems"][0]["expected"])
                                        && $Item["presentationitems"][0]["expected"] != "-" ) ? $Item["presentationitems"][0]["expected"] : "";
    $QUEU = ($DAIA  && isset($Item["loanitems"][0]["queue"])
                          && $Item["loanitems"][0]["queue"]    != "-" )         ? $Item["loanitems"][0]["queue"]            : "";
    if ( !$QUEU ) $QUEU = ($DAIA  && isset($Item["presentationitems"][0]["queue"])
                                        && $Item["presentationitems"][0]["queue"] != "-" ) ? $Item["presentationitems"][0]["queue"] : "";
    $ACTN = ($DAIA  && isset($Item["loanitems"][0]["action"])
                          && $Item["loanitems"][0]["action"]   != "-" )         ? $Item["loanitems"][0]["action"]           : "";
    if ( !$ACTN ) $ACTN = ($DAIA  && isset($Item["presentationitems"][0]["action"])
                                        && $Item["presentationitems"][0]["action"] != "-" ) ? $Item["presentationitems"][0]["action"] : "";
    $Loc  = isset($Item["loc"]) ? $Item["loc"] : "";

    // $this->CS->debug("ALXItem", $this->one);
    // $this->CS->debug("ALXItem", $Item);

    foreach ( $ScopeCases as $SortLevel => $Cases )
    {
      if ( $OldSortLevel != "" && $OldSortLevel != $SortLevel ) continue;
      foreach ( $Cases as $CI => $Case )
      {
        // if ( !isset($Case["filters"]) || !is_array($Case["filters"]) || !count($Case["filters"]) )  continue;

        // $this->CS->debug("ALX Case ".$SortLevel, $Case);
        if ( !isset($Case["active"]) || !$Case["active"] )  continue;

        $this->setLog("Case", "Check " . $SortLevel . "." . $Case["name"]);

        $Match     = true;
        foreach ( $Case["filters"] as $Filter )
        {
          // Logik: Alle Filter sind VORAB TRUE und müssen durch Prüfung wie folgt beendet werden:
          //
          // Filter trifft zu:        break;
          // Filter trifft nicht zu:  $Match = false; break;
          //
          // Hinweis: break nur in Schleifen / Switch - Statements

          if ( !$Match )  continue;
          $this->setLog("Filter", "Check " . $Filter);

          // Complex FOLIO filter f.status & f.nostatus
          if ( substr($Filter,0,9) == "f.status." )
          {
            $StatiFound = false;
            if ( !isset($Item["status"]) )
            {
              $Match = false;
            }
            else
            {
              $Statis     = explode(",", substr($Filter,9));
              foreach ( $Statis as $Stati )
              {
                if ( substr($Stati,-1) == "*" )
                {
                  $Pos = strpos($Stati, "*");
                  if ( substr($Stati,0,$Pos) == substr($Item["status"],0,$Pos) ) $StatiFound = true;
                }
                else
                {
                  if ( $Stati == $Item["status"] )   $StatiFound = true;
                }
                if ( $StatiFound ) continue;
              }
              if ( !$StatiFound )  $Match = false;
            }
            continue;
          }
          elseif ( substr($Filter,0,11) == "f.nostatus." )
          {
            if ( isset($Item["status"]) )
            {
              $Statis     = explode(",", substr($Filter,11));
              $StatiFound = false;
              foreach ( $Statis as $Stati )
              {
                if ( substr($Stati,-1) == "*" )
                {
                  $Pos = strpos($Stati, "*");
                  if ( substr($Stati,0,$Pos) == substr($Item["status"],0,$Pos) ) $StatiFound = true;
                }
                else
                {
                  if ( $Stati == $Item["status"] )   $StatiFound = true;
                }
                if ( $StatiFound )
                {
                  $Match = false;
                }
              }
              // if ( !$StorageIDFound )  $Match = false;
            }
            continue;
          }

          // Complex MARC filter m.article
          if ( substr($Filter,0,10) == "m.article." )
          {
            $M7737s = explode(",",substr($Filter,10));

            // $this->CS->debug("ALX1", $M7737s);
            // $this->CS->debug("ALX2", $Item);

            if (  !isset($this->one["artcode"]) || !in_array($this->one["artcode"], $M7737s) )  $Match = false;
            continue;
          }

          // Complex MARC filter m.ali
          elseif ( substr($Filter,0,6) == "m.ali." )
          {
            $Tmp = substr($Filter,6);
            if ( stripos($Tmp, ",") !== false )
            {
              $M980Es = explode(",", $Tmp);
              $Tmp    = "";
            }
            elseif ( stripos($Tmp, "--%%--") !== false )
            {
              $Tmp      = str_replace("--%%--", "", $Tmp);
              $M980Es   = str_split($Tmp);
              $M980Es[] = "--%%--";
            }
            else
            {
              $M980Es = str_split($Tmp);
            }
            $ALI    = isset($Item["e"][0]) ? $Item["e"][0] : "";

            if ( !$ALI || !in_array($ALI, $M980Es) )   $Match = false;
          }

          // Complex MARC filter m.noali - exaktes Gegenteil von m.ali:
          // trifft zu, solange der ALI des Exemplars NICHT in der Liste steht
          // (ein leerer ALI steht nicht in der Liste, trifft also zu).
          // Listenformen wie bei m.ali: "a,b" (Komma), "--%%--" als eigener
          // Eintrag, sonst zeichenweise. (20.08.2026)
          elseif ( substr($Filter,0,8) == "m.noali." )
          {
            $Tmp = substr($Filter,8);
            if ( stripos($Tmp, ",") !== false )
            {
              $M980Es = explode(",", $Tmp);
              $Tmp    = "";
            }
            elseif ( stripos($Tmp, "--%%--") !== false )
            {
              $Tmp      = str_replace("--%%--", "", $Tmp);
              $M980Es   = str_split($Tmp);
              $M980Es[] = "--%%--";
            }
            else
            {
              $M980Es = str_split($Tmp);
            }
            $ALI    = isset($Item["e"][0]) ? $Item["e"][0] : "";

            if ( $ALI && in_array($ALI, $M980Es) )   $Match = false;
          }

          // Complex MARC filter m.host
          elseif ( substr($Filter,0,7) == "m.host." )
          {
            // $this->CS->debug("ALXI1", $Item);
            if ( !isset($Item["host"]) )  {$Match = false;  continue; }

            if ( !$this->checkStrInWildArr($Item["host"], explode(",", substr($Filter,7))) ) $Match = false;
            continue;
          }

          // Complex MARC filter m.comment
          elseif ( substr($Filter,0,10) == "m.comment." )
          {
            // $this->CS->debug("ALXI1", $Item);
            if ( !isset($Item["k"][0]) )  {$Match = false;  continue; }

            if ( !$this->checkStrInWildArr($Item["k"][0], explode(",", substr($Filter,10))) ) $Match = false;
            continue;
          }

          // Complex MARC filter m.textlink
          elseif ( substr($Filter,0,11) == "m.textlink." )
          {
            // $this->CS->debug("ALXI1", $Item);
            if ( !isset($Item["text"]) )  {$Match = false;  continue; }

            if ( !$this->checkArrInWildArr(explode(", ", $Item["text"]), explode(",", substr($Filter,11))) ) $Match = false;
            continue;
          }

          // Complex MARC filters m.yearolderthan and m.yearyoungerthan
          elseif ( substr($Filter,0,16) == "m.yearolderthan." )
          {
            $Year    = (int) substr($Filter,16);
            if ( $Year < 750 || $Year > intval(date("Y")+1) || $Year >= $this->one["year"])  $Match = false;

            continue;
          }
          elseif ( substr($Filter,0,18) == "m.yearyoungerthan." )
          {
            $Year    = (int) substr($Filter,18);
            if ( $Year < 750 || $Year > intval(date("Y")+1) || $Year <= $this->one["year"])  $Match = false;

            continue;
          }

          // Complex MARC filter m.format & m.noformat
          elseif ( substr($Filter,0,9) == "m.format." )
          {
            $Forms    = explode(",", strtolower(substr($Filter,9)));
            if ( isset($this->one["formatcode"]) && !in_array(strtolower($this->one["formatcode"]), $Forms) )  $Match = false;
            continue;
          }
          elseif ( substr($Filter,0,11) == "m.noformat." )
          {
            $Forms    = explode(",", strtolower(substr($Filter,11)));
            if ( !isset($this->one["formatcode"]) || in_array(strtolower($this->one["formatcode"]), $Forms) )  $Match = false;
            continue;
          }

          // Complex MARC filter m.locallinkcomment
          elseif ( substr($Filter,0,19) == "m.locallinkcomment." )
          {
            $LinkCommentFound = false;
            if ( !isset($Item["text"]) )
            {
              $Match = false;
            }
            else
            {
              $LinkComments     = explode(",", substr($Filter,19));
              foreach ($LinkComments as $LinkComment)
              {
                if ( substr($LinkComment, -1) == "*" )
                {
                  $Pos = strpos($LinkComment, "*");
                  if ( substr($LinkComment, 0, $Pos) == substr($Item["text"],0, $Pos) ) $LinkCommentFound = true;
                }
                else
                {
                  if ( $LinkComment == $Item["text"])   $LinkCommentFound = true;
                }
                if ( $LinkCommentFound) continue;
              }
              if ( !$LinkCommentFound )  $Match = false;
            }
            continue;
          }

          // Complex MARC filter m.man & m.noman
          elseif ( substr($Filter,0,6) == "m.man." )
          {
            $M980Ys = explode(",",substr($Filter,6));

            // $this->CS->debug("ALXItem1", $M980Ys);
            // $this->CS->debug("ALXItem2", $Item);
            // $this->CS->debug("ALXItem3", $this->one);

            if ( count($Item) )
            {
              // Exemplar
              $Man    = isset($Item["y"][0]) ? substr($Item["y"][0],1) : "";
              if ( !$Man || !in_array($Man, $M980Ys) )   $Match = false;
            }
            else
            {
              // Media
              $Mans = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["y"][0]) )  $Mans[] = substr($Rec["y"][0],1);
                }
              }
              if ( !$this->checkArrInWildArr($Mans, $M980Ys) )  $Match = false;
            }
            continue;
          }
          elseif ( substr($Filter,0,8) == "m.noman." )
          {
            $M980Ys = explode(",",substr($Filter,8));

            if ( count($Item) )
            {
              // Exemplar
              $Man    = isset($Item["y"][0]) ? substr($Item["y"][0],1) : "";
              if ( $this->checkStrInWildArr($Man, $M980Ys) )  $Match = false;
            }
            else
            {
              // Media
              $Mans = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["y"][0]) )  $Mans[] =  substr($Rec["y"][0],1);
                }
              }
              if ( $this->checkArrInWildArr($Mans, $M980Ys) )  $Match = false;
            }
            continue;
          }

          // Complex MARC filter m.iln & m.noiln
          elseif ( substr($Filter,0,6) == "m.iln." )
          {
            $M9802s = explode(",",substr($Filter,6));

            // $this->CS->debug("ALX1", $M9802s);
            // $this->CS->debug("ALX2", $Item);

            if ( count($Item) )
            {
              // Exemplar
              $ILN    = isset($Item["iln"]) ? $Item["iln"] : "";
              if ( !$ILN && isset($Item["2"]["0"]) )  $ILN = $Item["2"]["0"];
              if ( !$ILN || !in_array($ILN, $M9802s) )   $Match = false;
            }
            else
            {
              // Media
              $ILNs = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["2"][0]) )  $ILNs[] = $Rec["2"][0];
                }
              }
              if ( !$this->checkArrInWildArr($ILNs, $M9802s) )  $Match = false;
            }
            continue;
          }
          elseif ( substr($Filter,0,8) == "m.noiln." )
          {
            $M9802s = explode(",",substr($Filter,8));

            // $this->CS->debug("ALX", $M9802s);
            // $this->CS->debug("ALX", $ILN);

            if ( count($Item) )
            {
              // Exemplar
              $ILN    = isset($Item["iln"]) ? $Item["iln"] : "";
              if ( !$ILN && isset($Item["2"]["0"]) )  $ILN = $Item["2"]["0"];
              if ( $ILN && in_array($ILN, $M9802s) )   $Match = false;
            }
            else
            {
              // Media
              $ILNs = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["2"][0]) )  $ILNs[] =  $Rec["2"][0];
                }
              }
              if ( $this->checkArrInWildArr($ILNs, $M9802s) )  $Match = false;
            }
            continue;
          }

          // Complex MARC filter m.eln & m.noeln
          elseif ( substr($Filter,0,6) == "m.eln." )
          {
            $M980Xs = explode(",",substr($Filter,6));
            if ( count($Item) )
            {
              // Exemplar
              $ELN    = isset($Item["x"][0]) ? $Item["x"][0] : "";
              if ( !$ELN || !in_array($ELN, $M980Xs) )   $Match = false;
            }
            else
            {
              // Media
              $ELNs = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["x"][0]) )  $ELNs[] = $Rec["x"][0];
                }
              }
              if ( !$this->checkArrInWildArr($ELNs, $M980Xs) )  $Match = false;
            }
            continue;
          }
          elseif ( substr($Filter,0,8) == "m.noeln." )
          {
            $M980Xs = explode(",",substr($Filter,8));
            if ( count($Item) )
            {
              // Exemplar
              $ELN    = isset($Item["x"][0]) ? $Item["x"][0] : "";
              if ( $ELN && in_array($ELN, $M980Xs) )   $Match = false;
            }
            else
            {
              // Media
              $ELNs = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["x"][0]) )  $ELNs[] =  $Rec["x"][0];
                }
              }
              if ( $this->checkArrInWildArr($ELNs, $M980Xs) )  $Match = false;
            }
            continue;
          }


          // Complex MARC filter m.manfull & m.nomanfull
          elseif ( substr($Filter,0,10) == "m.manfull." )
          {
            $M980Ys = explode(",",substr($Filter,10));

            // $this->CS->debug("ALX", $M980Ys);
            // $this->CS->debug("ALX", $Man);
            if ( count($Item) )
            {
              // Exemplar
              $Man    = isset($Item["y"][0]) ? $Item["y"][0] : "";
              if ( !$Man || !in_array($Man, $M980Ys) )   $Match = false;
            }
            else
            {
              // Media
              $Mans = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["y"][0]) )  $Mans[] = $Rec["y"][0];
                }
              }
              if ( !$this->checkArrInWildArr($Mans, $M980Ys) )  $Match = false;
            }
             continue;
          }
          elseif ( substr($Filter,0,12) == "m.nomanfull." )
          {
            $M980Ys = explode(",",substr($Filter,12));
            if ( count($Item) )
            {
              // Exemplar
              $Man    = isset($Item["y"][0]) ? $Item["y"][0] : "";
              if ( $Man && in_array($Man, $M980Ys) )   $Match = false;
            }
            else
            {
              // Media
              $Mans = array();
               if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
              {
                foreach ( $this->one["marc"]["M980"] as $Rec )
                {
                  if ( isset($Rec["y"][0]) )  $Mans[] = $Rec["y"][0];
                }
              }
              if ( $this->checkArrInWildArr($Mans, $M980Ys) )  $Match = false;
            }
             continue;
          }

          // Complex OTHER filter o.sig & o.nosig
          elseif ( substr($Filter,0,6) == "o.sig." )
          {
            // $this->CS->debug("ALXI1", $Item);
            // $this->CS->debug("ALXI2", $Case);
            // $this->CS->debug("ALXI3", $this->one["locs"]);

            if ( !isset($Item["d"][0]) )  {$Match = false;  continue; }

            if ( !$this->checkStrInWildArr($Item["d"][0], explode(",", substr($Filter,6))) ) $Match = false;
            continue;
          }
          elseif ( substr($Filter,0,8) == "o.nosig." )
          {
            if ( !isset($Item["d"][0]) )  {$Match = false;  continue; }

            if ( $this->checkStrInWildArr($Item["d"][0], explode(",", substr($Filter,8))) ) $Match = false;
            continue;
          }

          // Complex OTHER filter o.col
          elseif ( substr($Filter,0,6) == "o.col." )
          {
            // Kind
            $OneCols  = ( isset($this->one["collections"]) && is_array($this->one["collections"]) ) ? $this->one["collections"] : array();
            $Cols     = explode(",", substr($Filter,6));
            $ColFound = false;
            foreach ($Cols as $Col)
            {
              if ( in_array(strtolower($Col), $OneCols) )   $ColFound = true;
              if ( $ColFound) continue;
            }
            
            // Mama
            if ( $this->aps && $this->isHapticArticle() )
            {
              $Mama = "";
              // $this->CS->debug("ALX40", $this->CS->hierarchy);
              if ( array_key_exists($this->id,$this->CS->hierarchy["childs"]) ) {
                $Mama = $this->CS->hierarchy["childs"][$this->id];
                $OneCols  = ( isset($this->CS->works["add"][$Mama]["collections"]) && is_array($this->CS->works["add"][$Mama]["collections"]) ) ? $this->CS->works["add"][$Mama]["collections"] : array();
                $Cols     = explode(",", substr($Filter,6));
                foreach ($Cols as $Col)
                {
                  if ( in_array(strtolower($Col), $OneCols) )   $ColFound = true;
                  if ( $ColFound) continue;
                }
              }
            }

            // Final
            if ( !$ColFound )  $Match = false;
            continue;
          }

          // Complex OTHER filter o.loc & o.noloc
          elseif ( substr($Filter,0,6) == "o.loc." )
          {
            if ( !isset($Item["f"][0]) )  {$Match = false;  continue; }

            // $this->CS->debug("ALXI1", $Item["f"][0]);
            // $this->CS->debug("ALXI2", explode(",", substr($Filter,6)));
            // $this->CS->debug("ALXI3", !$this->checkStrInWildArr($Item["f"][0], explode(",", substr($Filter,6))) );

            if ( !$this->checkStrInWildArr($Item["f"][0], explode(",", substr($Filter,6))) ) $Match = false;
            continue;
          }
          elseif ( substr($Filter,0,8) == "o.noloc." )
          {
            if ( !isset($Item["f"][0]) )  {$Match = false;  continue; }

            if ( $this->checkStrInWildArr($Item["f"][0], explode(",", substr($Filter,8))) ) $Match = false;
            continue;
          }

          // Complex OTHER filter o.locid & o.nolocid
          elseif ( substr($Filter,0,8) == "o.locid." )
          {
            // $this->CS->debug("ALXI1", $Loc);
            // $this->CS->debug("ALXI2", $Case);
            // $this->CS->debug("ALXI3", $this->one["locs"]);

            if ( !$Loc )  {$Match = false;  continue; }

            if ( !$this->checkStrInWildArr($Loc, explode(",", substr($Filter,8))) ) $Match = false;
            continue;
          }
          elseif ( substr($Filter,0,10) == "o.nolocid." )
          {
            if ( !$Loc )  { continue; }

            if ( $this->checkStrInWildArr($Loc, explode(",", substr($Filter,10))) ) $Match = false;
            continue;
          }

          // Complex OTHER filter o.allloc & o.allnoloc
          elseif ( substr($Filter,0,9) == "o.allloc." )
          {
            // $this->CS->debug("ALXI1", $this->one);
            // $this->CS->debug("ALXI2", explode(",", substr($Filter,11)));
            // $this->CS->debug("ALXALLLOC", $Locs);

            $Locs = array();
            if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
            {
              foreach ( $this->one["marc"]["M980"] as $Rec )
              {
                if ( isset($Rec["f"][0]) )  $Locs[] = $Rec["f"][0];
              }
            }
            if ( !$this->awop && !$Locs && isset($this->CS->works["marc"][$ID]["marc"]["M980"])
            && is_array($this->CS->works["marc"][$ID]["marc"]["M980"]) && count($this->CS->works["marc"][$ID]["marc"]["M980"]) )
            {
              // $this->CS->debug("PITem",  $this->CS->works["marc"][$ID] );
              foreach ( $this->CS->works["marc"][$ID]["marc"]["M980"] as $Rec )
              {
                if ( isset($Rec["f"][0]) )  $Locs[] = $Rec["f"][0];
              }
            }

            if ( !is_array($Locs) || !count($Locs) )  { $Match = false;  continue; }

            if ( !$this->checkArrInWildArr($Locs, explode(",", substr($Filter,9))) ) $Match = false;
            continue;
          }
          elseif ( substr($Filter,0,11) == "o.allnoloc." )
          {
            $Locs = array();
            if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
            {
              foreach ( $this->one["marc"]["M980"] as $Rec )
              {
                if ( isset($Rec["f"][0]) )  $Locs[] = $Rec["f"][0];
              }
            }

            // $this->CS->debug("ALXI1", $Locs);
            // $this->CS->debug("ALXI2", array_map(array($this,"replacekeywords"), explode(",", substr($Filter,11))));

            if ( !count($Locs) )  { continue; }


            if ( $this->checkArrInWildArr($Locs, array_map(array($this,"replacekeywords"), explode(",", substr($Filter,11)))) ) $Match = false;
            continue;
          }

          // Complex OTHER filter o.allotherloc
          elseif ( substr($Filter,0,14) == "o.allotherloc." )
          {
            $Locs = array();
            if ( isset($this->one["marc"]["M980"]) && is_array($this->one["marc"]["M980"]) )
            {
              foreach ( $this->one["marc"]["M980"] as $Rec )
              {
                if ( isset($Rec["f"][0]) )  $Locs[] = $Rec["f"][0];
              }
            }

            $Other = explode(",", substr($Filter,14));
            if ( count($Other) && count($Locs) )
            {
              $Rest = $this->getArrReducedWildArr($Other, $Locs);
              if ( !count($Rest) )  $Match = false;
            }
            continue;
          }

          // Complex OTHER filter o.alllocid & o.allnolocid
          elseif ( substr($Filter,0,11) == "o.alllocid." )
          {
            // $this->CS->debug("ALXI1", $this->one["locs"]);
            // $this->CS->debug("ALXI2", explode(",", substr($Filter,11)));

            if ( !is_array($this->one["locs"]) || !count($this->one["locs"]) )  { $Match = false;  continue; }

            if ( !$this->checkArrInWildArr($this->one["locs"], explode(",", substr($Filter,11))) ) $Match = false;
            continue;
          }
          elseif ( substr($Filter,0,13) == "o.allnolocid." )
          {
            if ( !is_array($this->one["locs"])  || !count($this->one["locs"]) )  { continue; }

            if ( $this->checkArrInWildArr($this->one["locs"], explode(",", substr($Filter,13))) ) $Match = false;
            continue;
          }

          // Complex OTHER filter o.otherlocid
          elseif ( substr($Filter,0,13) == "o.otherlocid." )
          {
            $Other = explode(",", substr($Filter,13));
            if ( count($Other) && count($this->one["locs"]) )
            {
              $Rest = $this->getArrReducedWildArr($Other, $this->one["locs"]);
              if ( !count($Rest) )  $Match = false;
            }
            continue;
          }

          // Complex OTHER filter o.noetnr
          elseif ( substr($Filter,0,9) == "o.noetnr." )
          {
            if ( !is_array($this->casesetnr) || !count($this->casesetnr) )   { continue; }

            $Nos = explode(",", substr($Filter,9));
            if ( count($Nos) )
            {
              if ( $this->checkArrInWildArr(array_keys($this->casesetnr), $Nos) ) $Match = false;
            }
            continue;
          }

          // Complex OTHER filter o.otheretnr
          elseif ( substr($Filter,0,12) == "o.otheretnr." )
          {
            if ( !is_array($this->casesetnr) || !count($this->casesetnr) )   { continue; }

            $Others = explode(",", substr($Filter,12));
            // $this->CS->debug("ALXl33", $Others);
            // $this->CS->debug("ALXl34", $this->casesetnr);
            if ( count($Others) )
            {
              $Rest = $this->getArrReducedWildArr($Others, array_keys($this->casesetnr));
              // $this->CS->debug("ALXl35", $Rest);
              if ( !count($Rest) )  $Match = false;
            }
            continue;
          }

          // Complex OTHER filter o.level
          elseif ( substr($Filter,0,8) == "o.level." )
          {
            $Level = (isset($this->CS->posted["search"]["userlevel"]) && trim($this->CS->posted["search"]["userlevel"]))
                     ? trim($this->CS->posted["search"]["userlevel"])
                     : "";
            // $this->CS->debug("ALXl33", $Level);

            if ( !$this->checkArrInWildArr(array($Level), explode(",", substr($Filter,8))) ) $Match = false;
            continue;
          }

          // Complex OTHER filter o.ownorg
          elseif ( substr($Filter,0,9) == "o.ownorg." )
          {
            $OwnOrg = ( isset($this->one["ownorg"]) && trim($this->one["ownorg"]) ) ? trim($this->one["ownorg"]) : "";
            $M535As     = explode(",", substr($Filter,9));
            $M535AFound = false;
            foreach ($M535As as $M535A)
            {
              if ( substr($M535A, -1) == "*" )
              {
                $Pos = strpos($M535A, "*");
                if ( substr($M535A, 0, $Pos) == substr($OwnOrg,0, $Pos) ) $M535AFound = true;
              }
              else
              {
                if ( $M535A == $OwnOrg )   $M535AFound = true;
              }
              if ( $M535AFound) continue;
            }
            if ( !$M535AFound )  $Match = false;
            continue;
          }
          // Complex OTHER filter o.ipspecial
          elseif ( substr($Filter,0,12) == "o.ipspecial.")
          {
            $IPs = explode(",", substr($Filter,12));
            $this->setLog("IP", "Check Client IP " . $this->CS->clientip);
            $Hit = false;
            foreach ($IPs as $OneRange)
            {
              if ( !trim($OneRange) ) continue;
              $Range = explode("-",$OneRange);
              if ( count($Range) != 2 ) continue;
               if ( ip2long($this->CS->clientip) >= ip2long($Range[0]) && ip2long($this->CS->clientip) <= ip2long($Range[1]) )
              {
                $this->setLog("IP", "Found ip in range " . implode("-", $Range));
                $Hit = true;
              }
            }
            if ( !$Hit )  $Match = false;
            break;
          }

          // Complex PAIA filter p.storage & p.nostorage
          elseif ( substr($Filter,0,10) == "p.storage." )
          {
            if ( !isset($Item["storage"]) )
            {
              $Match = false;
            }
            else
            {
              $Storages     = explode(",", substr($Filter,10));
              $StorageFound = false;
              foreach ($Storages as $Storage)
              {
                if ( substr($Storage, -1) == "*" )
                {
                  $Pos = strpos($Storage, "*");
                  if ( substr($Storage, 0, $Pos) == substr($Item["storage"],0, $Pos) ) $StorageFound = true;
                }
                else
                {
                  if ( $Storage == $Item["storage"])   $StorageFound = true;
                }
                if ( $StorageFound) continue;
              }
              if ( !$StorageFound )  $Match = false;
            }
            continue;
          }
          elseif ( substr($Filter,0,12) == "p.nostorage." )
          {
            if ( isset($Item["storage"]) )
            {
              $Storages     = explode(",", substr($Filter,12));
              $StorageFound = false;
              foreach ($Storages as $Storage)
              {
                if ( substr($Storage, -1) == "*" )
                {
                  $Pos = strpos($Storage, "*");
                  if ( substr($Storage, 0, $Pos) == substr($Item["storage"],0, $Pos) ) $StorageFound = true;
                }
                else
                {
                  if ( $Storage == $Item["storage"])   $StorageFound = true;
                }
                if ( $StorageFound)
                {
                  $Match = false;
                  continue;
                }
              }
              // if ( !$StorageFound )  $Match = false;
            }
            continue;
          }

          // Complex PAIA filter p.storageid & p.nostorageid
          elseif ( substr($Filter,0,12) == "p.storageid." )
          {
            $StorageIDFound = false;
            if ( !isset($Item["storageid"]) )
            {
              $Match = false;
            }
            else
            {
              $StorageIDs     = explode(",", substr($Filter,12));
              foreach ($StorageIDs as $StorageID)
              {
                if ( substr($StorageID, -1) == "*" )
                {
                  $Pos = strpos($StorageID, "*");
                  if ( substr($StorageID, 0, $Pos) == substr($Item["storageid"],0, $Pos) ) $StorageIDFound = true;
                }
                else
                {
                  if ( $StorageID == $Item["storageid"])   $StorageIDFound = true;
                }
                if ( $StorageIDFound) continue;
              }
              if ( !$StorageIDFound )  $Match = false;
            }
            continue;
          }
          // Complex filter
          elseif ( substr($Filter,0,14) == "p.nostorageid." )
          {
            if ( isset($Item["storageid"]) )
            {
              $StorageIDs     = explode(",", substr($Filter,14));
              $StorageIDFound = false;
              foreach ($StorageIDs as $StorageID)
              {
                if ( substr($StorageID, -1) == "*" )
                {
                  $Pos = strpos($StorageID, "*");
                  if ( substr($StorageID, 0, $Pos) == substr($Item["storageid"],0, $Pos) ) $StorageIDFound = true;
                }
                else
                {
                  if ( $StorageID == $Item["storageid"])   $StorageIDFound = true;
                }
                if ( $StorageIDFound)
                {
                  $Match = false;
                }
              }
              // if ( !$StorageIDFound )  $Match = false;
            }
            continue;
          }

          // Complex PAIA filter p.departmentid & p.nodepartmentid
          elseif ( substr($Filter,0,15) == "p.departmentid." )
          {
            $DepartmentIDFound = false;
            if ( !isset($Item["departmentid"]) )
            {
              $Match = false;
            }
            else
            {
              $DepartmentIDs     = explode(",", substr($Filter,15));
              foreach ($DepartmentIDs as $DepartmentID)
              {
                if ( substr($DepartmentID, -1) == "*" )
                {
                  $Pos = strpos($DepartmentID, "*");
                  if ( substr($DepartmentID, 0, $Pos) == substr($Item["departmentid"],0, $Pos) ) $DepartmentIDFound = true;
                }
                else
                {
                  if ( $DepartmentID == $Item["departmentid"])   $DepartmentIDFound = true;
                }
                if ( $DepartmentIDFound) continue;
              }
              if ( !$DepartmentIDFound )  $Match = false;
            }
            continue;
          }
          // Complex filter
          elseif ( substr($Filter,0,17) == "p.nodepartmentid." )
          {
            if ( isset($Item["departmentid"]) )
            {
              $DepartmentIDs     = explode(",", substr($Filter,17));
              $DepartmentIDFound = false;
              foreach ($DepartmentIDs as $DepartmentID)
              {
                if ( substr($DepartmentID, -1) == "*" )
                {
                  $Pos = strpos($DepartmentID, "*");
                  if ( substr($DepartmentID, 0, $Pos) == substr($Item["departmentid"],0, $Pos) ) $DepartmentIDFound = true;
                }
                else
                {
                  if ( $DepartmentID == $Item["departmentid"])   $DepartmentIDFound = true;
                }
                if ( $DepartmentIDFound)
                {
                  $Match = false;
                }
              }
              // if ( !$StorageIDFound )  $Match = false;
            }
            continue;
          }

          // Simple filters
          else
          {
            switch ( $Filter )
            {
              // MARC filters
              case "m.articles":
              {
                if ( !isset($this->one["artcode"]) || $this->one["artcode"] != "nnam" )  $Match = false;
                break;
              }
              case "m.articlez":
              {
                if ( !isset($this->one["artcode"]) || $this->one["artcode"] != "nnas" )  $Match = false;
                break;
              }
              case "m.band":
              {
                 if ( !isset($this->one["marc"]["Lead"]) || !in_array(substr($this->one["marc"]["Lead"],18,1), array("b","c")) )    $Match = false;
                 break;
              }
              case "m.hasparent":
              {
                if ( !isset($this->one["rootid"]) || !$this->one["rootid"] ) $Match = false;
                break;
              }
              case "m.blsecondlevel":
              case "m.epn":
              {
                if ( !$this->epn )    $Match = false;
                break;
              }
              case "m.haptic":
              {
                if ( isset($this->one["online"]) && $this->one["online"] )  $Match = false;
                break;
              }
              case "m.nothaptic":
              {
                if ( isset($this->one["online"]) && !$this->one["online"] )  $Match = false;
                break;
              }
              case "m.noarticle":
              {
                if ( isset($this->one["formatcode"]) && in_array($this->one["formatcode"], array("article")) )  $Match = false;
                break;
              }
              case "m.nojournal":
              {
                if ( isset($this->one["formatcode"]) && in_array($this->one["formatcode"], array("journal")) )  $Match = false;
                break;
              }
              case "m.noonlineitem":
              {
                if ( isset($this->one["itemcount"]["online"]) && $this->one["itemcount"]["online"] )  $Match = false;
                break;
              }
              case "m.nophysicalitem":
              {
                if ( isset($this->one["itemcount"]["physical"]) && $this->one["itemcount"]["physical"] )  $Match = false;
                break;
              }
              case "m.noseries":
              {
                if ( isset($this->one["series"]) && $this->one["series"] )  $Match = false;
                break;
              }
              case "m.notowner":
              {
                // Prueft NUR den eigenen (Kind-)Besitz. Der Elternteil wird
                // bewusst NICHT herangezogen: bei aktivem articleparentshift
                // darf der Zugriff auf die Eltern nicht ueber notowner zurueck-
                // kommen (sonst faellt der Satz faelschlich auf 900.Ende statt
                // z.B. 800.5a Fernleihe). ErfurtUB, 20.08.2026.
                $NotOwner    = true;
                if ( isset($this->one["owner"]) && $this->one["owner"] )  $NotOwner = false;

                // Final
                if ( !$NotOwner )  $Match = false;
                break;
              }
              case "m.online":
              {
                if ( !isset($Item["link"]) || !trim($Item["link"]) ) $Match = false;
                break;
              }
              case "m.owncollection":
              {
                // $this->CS->debug("ALXCOLL", $this->CS->configs["mycolls"]);
                // $this->CS->debug("ALXCOLL", $this->one);
                if ( !isset($this->one["owncollection"]) || !$this->one["owncollection"] )  $Match = false;
                break ;
              }
              case "m.clausthaltu.openaccess":
              case "m.potsdamub.openaccess":
              case "m.openaccess":
              {
                if ( !isset($Item["oa"])  || !$Item["oa"] )   $Match = false;
                break;
              }
              case "m.owner":
              {
                // Kind
                $Owner    = true;
                if ( !isset($this->one["owner"]) || !$this->one["owner"] )  $Owner = false;

                if ( $this->aps && $this->isHapticArticle() )
                {
                  // Mama
                  $Mama = "";
                  if ( array_key_exists($this->id,$this->CS->hierarchy["childs"]) ) {
                    $Mama = $this->CS->hierarchy["childs"][$this->id];
                    if ( !isset($this->CS->works["add"][$Mama]["owner"]) || !$this->CS->works["add"][$Mama]["owner"] )  $Owner = false;
                  }
                }
                
                // Final
                if ( !$Owner )  $Match = false;
                break;
              }
              case "m.master":
              {
                 if (!((isset($this->one["marc"]["Lead"]) && substr($this->one["marc"]["Lead"],7,1) == "s"
                     && isset($this->one["marc"]["M008"]) && in_array(substr($this->one["marc"]["M008"],21,1), array("n","p","|","#"," ")) )
                   || ( isset($this->one["marc"]["Lead"]) && substr($this->one["marc"]["Lead"], 7,1) == "m"
                                                          && substr($this->one["marc"]["Lead"],19,1) == "a" )
                   || ( isset($this->one["marc"]["Lead"]) && substr($this->one["marc"]["Lead"], 7,1) == "s"
                     && isset($this->one["marc"]["M008"]) && substr($this->one["marc"]["M008"],21,1) == "m" )))    $Match = false;
                 break;
              }
              case "m.masterlink":
              {
                 if ( !isset($this->one["masterlink"]) || !trim($this->one["masterlink"]) ) $Match = false;
                 break;
              }
              case "m.blnosecondlevel":
              case "m.noepn":
              {
                if ( $this->epn )    $Match = false;
                break;
              }
              case "m.physical": // M980 (NoLink)
              {
                if ( isset($Item["link"]) && trim($Item["link"]) ) $Match = false;
                break;
              }
              case "m.remote":  // Only M856 (Link, No ILN)
              case "m.globallink":
              {
                if ( !isset($Item["link"])
                  || (isset($Item["link"]) && !$Item["link"])
                  || (isset($Item["iln"])  &&  $Item["iln"]) )        $Match = false;
                break;
              }
              case "m.locallink":
              {
                if ( !isset($Item["link"])
                  || (isset($Item["link"]) && !$Item["link"])
                  || !isset($Item["iln"])
                  || !$Item["iln"] )        $Match = false;
                break;
              }
              case "m.nolocallink":
              {
                // Gegenteil von m.locallink: stellt sicher, dass das M981
                // KEINEN (lokalen) Link trägt - trifft nicht zu, sobald es
                // einen Link mit ILN gibt (19.08.2026).
                if ( isset($Item["link"]) && $Item["link"]
                  && isset($Item["iln"])  && $Item["iln"] )        $Match = false;
                break;
              }
              case "m.series":
              {
                if ( !isset($this->one["series"]) || !$this->one["series"] )  $Match = false;
                break;
              }
              case "m.special.journal":
              {
                 if ( !isset($this->one["marc"]["Lead"]) || substr($this->one["marc"]["Lead"],7,1) != "s"
                   || !isset($this->one["marc"]["M008"]) || !in_array(substr($this->one["marc"]["M008"],21,1), array("n","p","|","#"," ")) )    $Match = false;
                 break;
              }
              case "m.special.monographicmaster":
              {
                 if ( !isset($this->one["marc"]["Lead"]) || substr($this->one["marc"]["Lead"], 7,1) != "m"
                                                         || substr($this->one["marc"]["Lead"],19,1) != "a" )    $Match = false;
                 break;
              }
              case "m.special.monographicseries":
              {
                 if ( !isset($this->one["marc"]["Lead"]) || substr($this->one["marc"]["Lead"], 7,1) != "s"
                   || !isset($this->one["marc"]["M008"]) || substr($this->one["marc"]["M008"],21,1) != "m" )    $Match = false;
                 break;
              }

              // PAIA filters
              case "p.action":
              {
                if ( ( !isset($Item["loanitems"][0])         || !count($Item["loanitems"][0])         || $Item["loanitems"][0]["action"]         == "-" )
                  && ( !isset($Item["presentationitems"][0]) || !count($Item["presentationitems"][0]) || $Item["presentationitems"][0]["action"] == "-" ) ) $Match = false;
                break;
              }
              case "p.actionorder":
              {
                if ( ( !isset($Item["loanitems"][0])         || !count($Item["loanitems"][0])         || $Item["loanitems"][0]["action"]         != "order" )
                  && ( !isset($Item["presentationitems"][0]) || !count($Item["presentationitems"][0]) || $Item["presentationitems"][0]["action"] != "order" ) ) $Match = false;
                break;
              }
              case "p.actionreserve":
              {
                if ( ( !isset($Item["loanitems"][0])         || !count($Item["loanitems"][0])         || $Item["loanitems"][0]["action"]         != "reserve" )
                  && ( !isset($Item["presentationitems"][0]) || !count($Item["presentationitems"][0]) || $Item["presentationitems"][0]["action"] != "reserve" ) ) $Match = false;
                break;
              }
              case "p.approval":
              {
                if ( !$this->isLimitation($Item, "ApprovalRequired") ) $Match = false;
                break;
              }
              case "p.bandlist":
              {
                if ( count($Item) )
                {
                  if ( !isset($Item["bandlist"]) || !$Item["bandlist"] )  $Match = false;
                }
                else
                 {
                   if ( isset($this->CS->paias[$this->id]) && is_array($this->CS->paias[$this->id]) && count($this->CS->paias[$this->id]) )
                   {
                     foreach ( $this->CS->paias[$this->id] as $PI )
                     {
                       if ( !isset($PI["bandlist"]) || !$PI["bandlist"] )
                       {
                         $Match = false;
                         break;
                       }
                     }
                   }
                   // $this->CS->debug("ALXBLY", $this->CS->paias[$this->id]);
                }
                break;
              }
              case "p.expected":
              {
                if ( !$EXPD )  $Match = false;
                break;
              }
              case "p.firstband":
              {
                if ( isset($Item["count"]) && $Item["count"] > 0 )  $Match = false;
                break;
              }
              case "p.loan":
              {
                if ( !$LOAN )  $Match = false;
                break;
              }
              case "p.noaction":
              {
                if ( ( isset($Item["loanitems"][0])         && count($Item["loanitems"][0])         && $Item["loanitems"][0]["action"]         != "-" )
                  || ( isset($Item["presentationitems"][0]) && count($Item["presentationitems"][0]) && $Item["presentationitems"][0]["action"] != "-" ) )       $Match = false;
                break;
              }
              case "p.noapproval":
              {
                if ( $this->isLimitation($Item, "ApprovalRequired") ) $Match = false;
                break;
              }
              case "p.nobandlist":
              {
                if ( count($Item) )
                {
                  if ( isset($Item["bandlist"]) && $Item["bandlist"] )  $Match = false;
                }
                else
                 {
                   // At least one item should habe a bandlist
                   if ( isset($this->CS->paias[$this->id]) && is_array($this->CS->paias[$this->id]) && count($this->CS->paias[$this->id]) )
                   {
                     $Tmp = true;
                     foreach ( $this->CS->paias[$this->id] as $PI )
                     {
                       if ( isset($PI["bandlist"]) && !$PI["bandlist"] )
                       {
                         $Tmp = false;
                         break;
                       }
                     }
                    if ( $Tmp )  $Match = false;
                   }
                }
                break;
              }
              case "p.nofirstband":
              {
                if ( !isset($Item["count"]) || $Item["count"] == 0 )  $Match = false;
                break;
              }
              case "p.noloan":
              {
                if ( $LOAN )  $Match = false;
                break;
              }
              case "p.nopaia":
              {
                // Siehe hasPaia(): auf der Medienebene gibt es kein Exemplar
                // und damit kein $DAIA - dort entscheidet der Satz.
                if ( $this->hasPaia($Item) )  $Match = false;
                break;
              }
              case "p.noshortloan":
              {
                if ( $this->isLimitation($Item, "ShortLoan") ) $Match = false;
                break;
              }
              case "p.nostationary":
              {
                if ( $this->isLimitation($Item, "Stationary") ) $Match = false;
                break;
              }
              case "p.paia":
              {
                // Siehe hasPaia(): auf der Medienebene gibt es kein Exemplar
                // und damit kein $DAIA - dort entscheidet der Satz.
                if ( !$this->hasPaia($Item) )  $Match = false;
                break;
              }
              case "p.pres":
              {
                if ( !$PRES )  $Match = false;
                break;
              }
              case "p.nopres":
              {
                if ( $PRES )  $Match = false;
                break;
              }
              case "p.queuefull":
              {
                if ( !isset($this->CS->configs["bms"]["maxqueue"]) || $QUEU == "-" || $QUEU < $this->CS->configs["bms"]["maxqueue"] )   $Match = false;
                break;
              }
              case "p.queuenullnoexpected":
              {
                if ( $QUEU != "0" || $EXPD ) $Match = false;
                break;
              }
              case "p.shortloan":
              {
                if ( !$this->isLimitation($Item, "ShortLoan") ) $Match = false;
                break;
              }
              case "p.stationary":
              {
                if ( !$this->isLimitation($Item, "Stationary") ) $Match = false;
                break;
              }

              // Other filters
              case "o.ip":
              {
                if ( !isset($this->CS->configs["init"]["ipranges"]) || !trim($this->CS->configs["init"]["ipranges"]) )    $Match = false;
                $IPs = explode(PHP_EOL, $this->CS->configs["init"]["ipranges"]);
                $this->setLog("IP", "Check Client IP " . $this->CS->clientip);
                $Hit = false;
                foreach ($IPs as $OneRange)
                {
                  if ( !trim($OneRange) ) continue;
                  $Range = explode("-",$OneRange);
                  if ( count($Range) != 2 ) continue;
                   if ( ip2long($this->CS->clientip) >= ip2long($Range[0]) && ip2long($this->CS->clientip) <= ip2long($Range[1]) )
                  {
                    $this->setLog("IP", "Found ip in range " . implode("-", $Range));
                    $Hit = true;
                  }
                }
                if ( !$Hit )  $Match = false;
                break;
              }
              case "o.hannovergwlb.about.lost":
              {
                if ( !isset($Item["about"]) || strripos($Item["about"], "vermisst") === false )    $Match = false;
                break;
              }
              case "m.hallelit.mirador":
              {
                if ( !isset($Item["text"]) || $Item["text"]  != "Mirador" )   $Match = false;
                break;
              }
              case "o.rostockub.emailcld":
              {
                // $this->CS->debug("ALXROS1", $Item);

                // Determine params
                $Params = array("iln"     => "62",
                                "format"  => "json");
                if ( isset($Item["d"][0]) && trim($Item["d"][0]) )  $Params["sgn"] = trim($Item["d"][0]);
                if ( isset($Item["e"][0]) && trim($Item["e"][0]) )  $Params["aui"] = trim($Item["e"][0]);
                if ( isset($Item["f"][0]) && trim($Item["f"][0]) )  $Params["sst"] = trim($Item["f"][0]);

                // $this->CS->debug("ALXROS2", $Params);

                // Item sstmaps
                $File = $this->CS->prepareCachePath(array("sstmap"), preg_replace("/[^a-zA-Z0-9]+/", "", implode("", $Params)));
                if ( file_exists($File) )
                {
                  $AddInfos = json_decode(file_get_contents($File),true);
                  // if ( isset($AddInfos["item"]["emailcld"]) ) $this->CS->debug("ALXROS3", $AddInfos["item"]);
                }
                if ( !file_exists($File) || !isset($AddInfos["item"]["emailcld"]) || !trim($AddInfos["item"]["emailcld"]) ) $Match = false;
                break;
              }
              case "o.rostockub.emailscd":
              {
                // $this->CS->debug("ALXROS1", $Item);

                // Determine params  (Sequence is IMPORTANT for caching)
                $Params = array("iln"     => "62",
                                "format"  => "json");
                if ( isset($Item["f"][0]) && trim($Item["f"][0]) )  $Params["sst"] = trim($Item["f"][0]);
                if ( isset($Item["d"][0]) && trim($Item["d"][0]) )  $Params["sgn"] = trim($Item["d"][0]);
                if ( isset($Item["e"][0]) && trim($Item["e"][0]) )  $Params["aui"] = trim($Item["e"][0]);

                // $this->CS->debug("ALXROS2", $Params);

                // Item sstmaps
                $File = $this->CS->prepareCachePath(array("sstmap"), preg_replace("/[^a-zA-Z0-9]+/", "", implode("", $Params)));
                if ( file_exists($File) )
                {
                  $AddInfos = json_decode(file_get_contents($File),true);
                  // if ( isset($AddInfos["item"]) ) $this->CS->debug("ALXROS3", $AddInfos["item"]);
                }
                if ( !file_exists($File) || !isset($AddInfos["item"]["emailscd"]) || !trim($AddInfos["item"]["emailscd"]) ) $Match = false;
                break;
              }

              default:
              {
                $this->setLog("ERROR", "Filter " . $Filter . " not found !");
                $Match = false;
              }
            }
          }
        }
        if ( $Match )
        {
          $Hits[] = array("SL" => $SortLevel,
                          "CI" => $CI);
          $this->setLog("Case", "Found " . $SortLevel . "." . $Case["name"]);
          if ( isset($this->casesetnr[$SortLevel]) )
          {
            $this->casesetnr[$SortLevel]++;
          }
          else
          {
            $this->casesetnr[$SortLevel] = 1;
          }
          $OldSortLevel = $SortLevel;
        }
      }
    }

    while ( count($Hits) > 0 )
    {
      // Process every ET line
      $Exemplar = array();
      $Hit      = array_shift($Hits);
      $Case     = (isset($ScopeCases[$Hit["SL"]][$Hit["CI"]])) ? $ScopeCases[$Hit["SL"]][$Hit["CI"]] : array();

      // Action
      $Exemplar["action"] = $this->getActionElements($Case, $Item);

      if ( isset($Exemplar["action"]["type"]) && $Exemplar["action"]["type"] == "skip" )  continue;

      // Serverseitige Textaufloesung (19.08.2026): der Knopf-Text ("button")
      // ist ein Textschluessel; ihn hier zur fertigen Beschriftung der
      // angeforderten Sprache aufloesen, damit der Client nicht mehr selbst
      // ueber config.t() gehen muss (Ziel: Texte nur noch auf dem Server).
      if ( isset($Exemplar["action"]["button"]) && trim($Exemplar["action"]["button"]) !== "" )
        $Exemplar["action"]["label"] = $this->CS->getText($Exemplar["action"]["button"]);

      // $Exemplar += $this->setMeta($Item, $Hit, $Case, $Loc);       VORBEREITUNG !!!!

       // Short
      if ( isset($Case["itembutton"]) )       $Exemplar["short"]  = array("code"  => $Case["itembutton"],
                                                                          "label" => $this->CS->getText($Case["itembutton"]),
                                                                          "icon"  => $Case["itemicon"],
                                                                          "color" => $Case["light"]);


      // Comments
      if ( isset($Case["itemcomments"]) && is_array($Case["itemcomments"]) && count($Case["itemcomments"]) )
      {
        $Exemplar["comments"] = $this->getDisplayElements($Case["itemcomments"], $Item);
        // $this->CS->debug("AÖX111", $Exemplar["comments"]);
      }
      else
      {
        // Wichtig damit nicht über unteren Teil (Loc) die Standort-Schnittstellen Daten laufend über mehrere Exemplare ergänzt werden
        $Exemplar["comments"] = array("g" => array(),
                                      "e" => array());
      }
      if ( isset($Exemplar["action"][0]["link"]["label"]) )
      {
        $Exemplar["comments"]["g"] = [...$Exemplar["comments"]["g"],
                                      ...[["type" => "text", "text" => $Exemplar["action"][0]["link"]["label"]]]];
        $Exemplar["comments"]["e"] = [...$Exemplar["comments"]["e"],
                                      ...[["type" => "text", "text" => $Exemplar["action"][0]["link"]["label"]]]];
      }
      // $this->CS->debug("AÖX222", $Exemplar["comments"]);

      // Detailinfos
      if ( isset($Case["detailinfos"]) && is_array($Case["detailinfos"]) && count($Case["detailinfos"]) )
      {
        if ( !$this->epn )
        {
          $Exemplar["view"] = $this->getDisplayElements(array_merge(array("h.item.item"), $Case["detailinfos"]), $Item);
         }
        else
        {
          $Exemplar["view"] = $this->getDisplayElements(array_merge(array("h.bibline.volume"), $Case["detailinfos"]), $Item);
        }
      }

      // Meta
      $Exemplar["meta"]              = array("case"       => $Hit["SL"] . "." .  $Case["name"],
                                             "window"     => $Case["detailwindow"] == 'y' ? true : false,
                                             "locid"      => $Loc,
                                             "author"     => $this->CS->getMarcText($ID, "bib_04"),
                                             "comment"    => $this->CS->getUniqueMultArray($this->comment),
                                             "location"   => $this->CS->getUniqueMultArray($this->location),
                                             "pagefromto" => $this->getPage(),
                                             "signature"  => $this->CS->getUniqueMultArray($this->signature),
                                             "stock"      => $this->CS->getUniqueMultArray($this->stock),
                                             "title"      => $this->CS->getMarcText($ID, "bib_01"),
                                             "volume"     => $this->getVolume());

      if ( isset($this->CS->configs["other"]["delivery.type"]) && $this->CS->configs["other"]["delivery.type"] == "mail" )
      {
        // ALEX ROSTOCK
         // $this->CS->debug("ALEXLOCIF", $Loc);
         // meta oberhalb mit to - Mailadresse und - falls nicht - vorhanden - UserTypen ergänzen.
      }


      if ( isset($Exemplar["short"]["code"]) && $Exemplar["short"]["code"] == "itembutton.bandlist" )
      {
        $Exemplar["meta"]["dialog"] = "bandlist";
        $Exemplar["meta"]["epn"]    = isset($Item["b"][0]) ? $Item["b"][0] : "";
      }
      elseif ( isset($Exemplar["action"]["type"]) && $Exemplar["action"]["type"] == "delivery" )
      {
        $Exemplar["meta"]["dialog"] = $this->CS->client == "GreifswaldUB" ? "delivery2" : "delivery";
      }
      elseif ( isset($Exemplar["action"]["type"]) && $Exemplar["action"]["type"] == "dialog" )
      {
        $Exemplar["meta"]["dialog"] = "dialog";
      }
      elseif ( isset($Exemplar["action"]["type"]) && substr($Exemplar["action"]["type"],0,4) == "mail" )
      {
        $Exemplar["meta"]["dialog"] = "library";
      }
      else
      {
        $Exemplar["meta"]["dialog"] = "default";
      }

      // Preparation
      if ( !isset($Exemplar["view"]) )  $Exemplar["view"] = array("g" => array(),
                                                                  "e" => array());
      if ( !isset($Exemplar["view"]["g"]) ) $Exemplar["view"]["g"] = array();
      if ( !isset($Exemplar["view"]["e"]) ) $Exemplar["view"]["e"] = array();


      // Add comments from location interface
      if ( count($Item) && isset($this->CS->locations["comment"][$Loc]) )
      {
        if ( isset($this->CS->locations["comment"][$Loc]["g"]) && is_array($this->CS->locations["comment"][$Loc]["g"])
          && count($this->CS->locations["comment"][$Loc]["g"]) )
        {
          $Exemplar["comments"]["g"] = [...$Exemplar["comments"]["g"],
                                        ...$this->CS->locations["comment"][$Loc]["g"]];
        }
        if ( isset($this->CS->locations["comment"][$Loc]["e"]) && is_array($this->CS->locations["comment"][$Loc]["e"])
          && count($this->CS->locations["comment"][$Loc]["e"]) )
        {
          $Exemplar["comments"]["e"] = [...$Exemplar["comments"]["e"],
                                        ...$this->CS->locations["comment"][$Loc]["e"]];
        }

        // $this->CS->debug("AÖX444", $Exemplar["comments"]);

        foreach ($Exemplar["comments"]["g"] as &$v)
        {
          if ( isset($v["type"]) && $v["type"] == "link" && isset($v["link"]) && $v["link"] != "" )
          {
            $v["link"] = $this->repairLink($v["link"], $Item);
          }
        }
        foreach ($Exemplar["comments"]["e"] as &$v)
        {
          if ( isset($v["type"]) && $v["type"] == "link" && isset($v["link"]) && $v["link"] != "" )
          {
            $v["link"] = $this->repairLink($v["link"], $Item);
          }
        }
        // $this->CS->debug("ALX25",$Exemplar["view"]["g"]);
      }
      // $this->CS->debug("AÖX555", $Exemplar["comments"]);

      // Add detailsinfos from location interface
      if ( count($Item) && isset($this->CS->locations["view"][$Loc]) )
      {
        if ( !isset($this->CS->locations["view"][$Loc]["g"]) )  $this->CS->locations["view"][$Loc]["g"] = array();
        if ( !isset($this->CS->locations["view"][$Loc]["e"]) )  $this->CS->locations["view"][$Loc]["e"] = array();
        $Exemplar["view"]["g"] = (isset($Exemplar["view"]["g"])) ? [...$Exemplar["view"]["g"], ...$this->CS->locations["view"][$Loc]["g"]]
                                                                 : $this->CS->locations["view"][$Loc]["g"];
        $Exemplar["view"]["e"] = (isset($Exemplar["view"]["e"])) ? [...$Exemplar["view"]["e"], ...$this->CS->locations["view"][$Loc]["e"]]
                                                                 : $this->CS->locations["view"][$Loc]["e"];
        foreach ($Exemplar["view"]["g"] as &$v)
        {
          if ( isset($v["type"]) && $v["type"] == "link" && isset($v["link"]) && $v["link"] != "" )
          {
            if (  stripos($v["link"], "<M980.d>") !== false )
            {
              $Fill = isset($Item["d"][0]) && trim($Item["d"][0]) && $Item["d"][0] != "--%%--" ? $Item["d"][0] : "";
              if ( $this->CS->client == "PotsdamUB" )
              {
                $Switch = array("1080" => "11frh",
                                "1180" => "09frh",
                                "2080" => "golm",
                                "3080" => "bbb",
                                "4080" => "10frh");
                if ( array_key_exists($Fill, $Switch) )  $Fill = $Switch[$Fill];
                // $this->CS->debug("ALXJE", $Fill);
              }
              $v["link"] = str_ireplace("<M980.d>", urlencode($Fill), $v["link"]);
            }
            if (  stripos($v["link"], "<M980.f>") !== false )
            {
              $Fill = isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" ? $Item["f"][0] : "";
              $v["link"] = str_ireplace("<M980.f>", urlencode($Fill), $v["link"]);
            }
          }
        }
        foreach ($Exemplar["view"]["e"] as &$v)
        {
          if ( isset($v["type"]) && $v["type"] == "link" && isset($v["link"]) && $v["link"] != "" )
          {
            if (  stripos($v["link"], "<M980.d>") !== false )
            {
              $Fill = isset($Item["d"][0]) && trim($Item["d"][0]) && $Item["d"][0] != "--%%--" ? $Item["d"][0] : "";
              if ( $this->CS->client == "PotsdamUB" )
              {
                $Switch = array("1080" => "11frh",
                                "1180" => "09frh",
                                "2080" => "golm",
                                "3080" => "bbb",
                                "4080" => "10frh");
                if ( array_key_exists($Fill, $Switch) )  $Fill = $Switch[$Fill];
              }
              $v["link"] = str_ireplace("<M980.d>", urlencode($Fill), $v["link"]);
            }
            if (  stripos($v["link"], "<M980.f>") !== false )
            {
              $Fill = isset($Item["f"][0]) && trim($Item["f"][0]) && $Item["f"][0] != "--%%--" ? $Item["f"][0] : "";
              $v["link"] = str_ireplace("<M980.f>", urlencode($Fill), $v["link"]);
            }
          }
        }
      }

      // Desk & Users
      // $this->CS->debug("ALX32", $Item);

      // Get users based on m980 (location, signature,...)
      if ( count($Item) )
      {
        $DesksUsers = $this->addDeskUsers($Loc, $Item, $Exemplar);

        // Thekenwahl: die Theken-IDs kommen jetzt schon als {value,label} in
        // der angeforderten Sprache zurueck (19.08.2026). Im Altmodul loeste
        // der Client "location.desk.<id>" selbst ueber getText() auf - der
        // neue Grundsatz ist "Texte nur auf dem Server". Der zugehoerige
        // Aufforderungstext ("Bitte waehlen Sie eine Abholtheke") reist als
        // "deskprompt" mit, damit auch er nicht mehr im Client stehen muss.
        $RawDesks = isset($DesksUsers["desks"]) ? $DesksUsers["desks"] : array();
        $Exemplar["desks"] = array();
        foreach ( $RawDesks as $DID )
        {
          $Lbl = $this->CS->getText("location.desk." . $DID);
          $Exemplar["desks"][] = array("value" => $DID, "label" => ($Lbl !== "" ? $Lbl : $DID));
        }
        $Exemplar["users"] = isset($DesksUsers["users"]) ? $DesksUsers["users"] : array();
        if ( count($Exemplar["desks"]) )
        {
          $Exemplar["deskprompt"] = $this->CS->getText("selectdesk");
          $Exemplar["desklabel"]  = $this->CS->getText("desk");
        }
      }

      // Get users from default
      //
      // Der Lieferdienst heisst in API 5 nicht mehr "delivery": er laeuft seit
      // dem 19.08.2026 ueber den generischen Dialog ("type" => "dialog",
      // "dialog" => "delivery" bzw. "delivery2" bei GreifswaldUB). Die Abfrage
      // stand aber weiter auf dem ALTEN Namen und traf damit nie zu - "users"
      // blieb leer, und mit ihm fiel die Beschraenkung auf bestimmte
      // Benutzergruppen (item.delivery.usertypes) still weg (09.09.2026,
      // gemeldet an WeimarBU 1880554291). Beide Schreibweisen werden erkannt,
      // damit der alte Name nicht extra abgeloest werden muss.
      $ActType = isset($Exemplar["action"]["type"])   ? $Exemplar["action"]["type"]   : "";
      $ActDlg  = isset($Exemplar["action"]["dialog"]) ? $Exemplar["action"]["dialog"] : "";
      if ( ( !isset($Exemplar["users"]) || !count($Exemplar["users"]) )
        && isset($this->CS->configs["item"]["delivery.usertypes"]) && trim($this->CS->configs["item"]["delivery.usertypes"])
        && ( $ActType == "delivery"
          || ( $ActType == "dialog" && in_array($ActDlg, array("delivery", "delivery2")) ) ) )
      {
       $Exemplar["users"] = explode(",", $this->CS->configs["item"]["delivery.usertypes"]);
      }

      // Local Infos
      $this->getItemDataLocal($Item, $Exemplar);

      // Light
      $Exemplar["light"] = (isset($Case["light"]) && $Case["light"]) ? $Case["light"] : "red";
      $Blue2Green        = (isset($this->CS->configs["item"]["statusblueasgreen"]) && $this->CS->configs["item"]["statusblueasgreen"] == "y") ? true : false;
      if ( in_array($this->best, array("red"))                   && in_array($this->normLight($Exemplar["light"]), $this->lightsblue) )   $this->best = $Blue2Green ? "green" : "blue";
      if ( in_array($this->best, array("red", "blue"))           && in_array($this->normLight($Exemplar["light"]), $this->lightsyellow) ) $this->best = "orange";
      if ( in_array($this->best, array("red", "blue", "orange")) && in_array($this->normLight($Exemplar["light"]), $this->lightsgreen) )  $this->best = "green";
      $Exps[] = $Exemplar;
    }
  }

  private function setMoreGroups()
  {
    $RTRN = array();

    if ( isset($this->CS->works["marc"][$this->id]["searches"]) && count($this->CS->works["marc"][$this->id]["searches"]) )
    {
      $Color = isset($this->CS->configs["item"]["search.color"]) && trim($this->CS->configs["item"]["search.color"]) ? $this->CS->configs["item"]["search.color"] : "green";
      $S     = array();
      foreach ( $this->CS->works["marc"][$this->id]["searches"] as $SID => $Search )
      {
        $S[] = array("short"    => array("code"  => "itembutton.openedition",
                                         "label" => $this->CS->getText("itembutton.openedition"),
                                         "icon"  => "mdi-magnify",
                                         "color" => $Color),
                     "comments" => array("g" => array(array("type" => "text", "text" => $Search["text"])),
                                         "e" => array(array("type" => "text", "text" => $Search["text"]))),
                     "light"    => "blue",
                     "action"   => array("type"   => "search",
                                         "search" => array("query" => $Search["search"],
                                                           "type"  => "lukida")),
                     "view"     => array(),
                     "users"    => array(),
                     "desks"    => array(),
                     "meta"     => array("case"   => "Search brothers and sisters",
                                         "locid"  => "search." . $SID,
                                         "window" => false));
      }

      if ( count($S) )  $RTRN["searches"] = $S;
    }

    if ( isset($this->CS->works["marc"][$this->id]["links"]) && count($this->CS->works["marc"][$this->id]["links"]) )
    {
      $Color = isset($this->CS->configs["item"]["link.color"]) && trim($this->CS->configs["item"]["link.color"]) ? $this->CS->configs["item"]["link.color"] : "green";
      $L     = array();
      foreach ( $this->CS->works["marc"][$this->id]["links"] as $SID => $Link )
      {
        $L[] = array("short"    => array("code"  => "itembutton.openedition",
                                         "label" => $this->CS->getText("itembutton.openedition"),
                                         "icon"  => "mdi-link",
                                         "color" => $Color),
                     "comments" => array("g" => array(array("type" => "text", "text" => $Link["text"])),
                                         "e" => array(array("type" => "text", "text" => $Link["text"]))),
                     "light"    => "blue",
                     "action"   => array("type"   => "link",
                                         "link"   => array("url"    => $Link["link"],
                                                           "label"  => "",
                                                           "target" => "_blank")),
                     "view"     => array(),
                     "users"    => array(),
                     "desks"    => array(),
                     "meta"     => array("case"   => "Link to brothers and sisters",
                                         "locid"  => "link." . $SID,
                                         "window" => false));
      }

      if ( count($L) )  $RTRN["links"] = $L;
    }
    return $RTRN;
  }

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************


  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function getItems($ID, $EPN = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // $this->CS->debug("ALXITEM", $ID);
    // $this->CS->debug("ALXITEM", $this->CS->configs);
    // $this->CS->debug("ALXITEM", $this->CS->works["marc"][$ID]);

    $this->id        = $ID;
    $this->epn       = $EPN;
    $this->one       = &$this->CS->works["marc"][$ID];
    $this->pid       = isset($this->one["rootid"]) ? $this->one["rootid"] : "";
    $this->casesetnr = array();
    $this->comment   = array();
    $this->location  = array();
    $this->signature = array();
    $this->stock     = array();

    // Searches and Links from marc
    // if ( !$this->epn ) $this->setMoreGroups();

    // Groups
    $this->setCombs();

    // Items
    return $this->setItems();
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}

?>