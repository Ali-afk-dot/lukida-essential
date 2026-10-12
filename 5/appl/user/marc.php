<?php

class marc
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  private $add;
  private $ArtCode;
  private $ArticleLink;
  private $Client;
  private $ColDetails;
  private $Collection;
  private $Comb;
  private $Convert;
  private $Counts;
  private $CS;
  private $Display;
  private $Fields;
  private $Format;
  private $ID;
  private $ILNGroups;
  private $Items;        // Struktur: Group EPN usw.
  private $lang;
  private $Links;
  private $MasterLink;
  private $SolrOne;
  private $MItems;       // Struktur: EPN usw.
  private $MultiItems;   // Struktur: Group EPN usw.
  private $Online;
  private $OriginalYear;
  private $OwnCollection;
  private $Owner;
  private $OwnOrg;
  private $parent;
  private $Parents;
  private $Prefix;
  private $Searches;
  private $Series;
  private $Year;

  public function __construct($CS)
  {
    $this->CS     = $CS;

    $this->add    = false;
    $this->Client = $CS->client;
    $this->lang   = $CS->lang;
    $this->parent = false;

    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);
  }

  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************
  private function addOriginalCharacters(&$Fields)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Intl = $this->getCompleteArray(array($this->Prefix . "880" => array(" 0","4","6","a","b","c","e","n","v")));

    foreach($Intl as $Rec)
    {
      $Simple = array();
      foreach ( $Rec as $Key => $Values )
      {
        foreach ( $Values as $Value )
        {
          if ( !isset($Simple[$Key]) ) $Simple[$Key] = $Value;
        }
      }

      // 245 Title
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "245" )
      {
        $Tmp = "";
        if ( isset($Simple["a"]) && $Simple["a"] != "" && isset($Simple["b"]) && $Simple["b"] != "" )       $Tmp  = $Simple["a"] . " : " . $Simple["b"];
        if ( isset($Simple["a"]) && $Simple["a"] != "" && (!isset($Simple["b"]) || $Simple["b"] == "" ) )   $Tmp  = $Simple["a"];
        if ( ( !isset($Simple["a"]) || $Simple["a"] == "" ) && isset($Simple["b"]) && $Simple["b"] != "" )  $Tmp  = $Simple["b"];
        if ( isset($Simple["c"]) && $Simple["c"] != "" )                                                    $Tmp .= " / " . $Simple["c"];
        $Pos  = (isset($Fields["bib_01"]["items"]) && is_array($Fields["bib_01"]["items"])) ? count($Fields["bib_01"]["items"]) : 0;
        if ( !isset($Fields["bib_01"]["items"]) ) $Fields["bib_01"]["items"] = array();
        $Fields["bib_01"]["items"]["item_".++$Pos] = array("display" => "newline");
        $Fields["bib_01"]["items"]["item_".++$Pos] = array("text" => $Tmp, "action" => array());


        if ( isset($Simple["n"]) && $Simple["n"] != "" )
        {
           $Tmp2 = $Simple["n"];
          $Pos2  = (isset($Fields["bib_03"]["items"]) && is_array($Fields["bib_03"]["items"])) ? count($Fields["bib_03"]["items"]) : 0;
          if ( !isset($Fields["bib_03"]["items"]) ) $Fields["bib_03"]["items"] = array();
          $Fields["bib_03"]["items"]["item_".++$Pos2] = array("text" => $Tmp2, "action" => array());
         // $this->CS->debug("AÖX", $Fields["bib_03"]["items"]);
        }
      }

      // 246 AddTitle
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "246" )
      {
        $Tmp = "";
        if ( isset($Simple["a"]) && $Simple["a"] != "" && isset($Simple["b"]) && $Simple["b"] != "" )       $Tmp  = $Simple["a"] . " : " . $Simple["b"];
        if ( isset($Simple["a"]) && $Simple["a"] != "" && (!isset($Simple["b"]) || $Simple["b"] == "" ) )   $Tmp  = $Simple["a"];
        if ( ( !isset($Simple["a"]) || $Simple["a"] == "" ) && isset($Simple["b"]) && $Simple["b"] != "" )  $Tmp  = $Simple["b"];
        if ( isset($Simple["c"]) && $Simple["c"] != "" )                                                    $Tmp .= " " . $Simple["c"];
        $Pos  = (isset($Fields["bib_42"]["items"]) && is_array($Fields["bib_42"]["items"])) ? count($Fields["bib_42"]["items"]) : 0;
        if ( !isset($Fields["bib_42"]["items"]) ) $Fields["bib_42"]["items"] = array();
        $Fields["bib_42"]["items"]["item_".++$Pos] = array("text" => $Tmp, "action" => array());
      }

      /*
      //710 Corporation
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "710" )
      {
        $Tmp = "";
        if ( isset($Simple["a"]) && $Simple["a"] != "" && isset($Simple["b"]) && $Simple["b"] != "" )       $Tmp  = $Simple["a"] . " : " . $Simple["b"];
        if ( isset($Simple["a"]) && $Simple["a"] != "" && (!isset($Simple["b"]) || $Simple["b"] == "" ) )   $Tmp  = $Simple["a"];
        if ( ( !isset($Simple["a"]) || $Simple["a"] == "" ) && isset($Simple["b"]) && $Simple["b"] != "" )  $Tmp  = $Simple["b"];
        if ( isset($Simple["c"]) && $Simple["c"] != "" )                                                    $Tmp .= " " . $Simple["c"];
        if ( isset($Simple["e"]) && $Simple["e"] != "" )                                                    $Tmp .= " (" . $Simple["e"] . ")";
        $Pos  = (isset($Fields["bib_07"]["items"]) && is_array($Fields["bib_07"]["items"])) ? count($Fields["bib_07"]["items"]) : 0;
        if ( !isset($Fields["bib_07"]["items"]) ) $Fields["bib_07"]["items"] = array();
        $Fields["bib_07"]["items"]["item_".++$Pos] = array("text" => $Tmp, "action" => array());
      }
      */

      /*
      // 100 People
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "100" )
      {
        $Name  = $this->formatPersonName($Rec);
        $Norm  = $this->getNorm($Rec);
        $Typ   = "A";
        $Pos   = (isset($Fields["bib_04"]["items"]) && is_array($Fields["bib_04"]["items"])) ? count($Fields["bib_04"]["items"]) : 0;
        $Roles = $this->getPersonRoles($Rec, $Typ == "A");
        $Roles = array_filter($Roles);
        if ( $Typ == "A" )  $Roles = array_diff($Roles, array("aut"));

        foreach ( $Roles as &$Role )
        {
          $Role = $this->CS->getText("role.".$Role);
        }
        $Roles = array_filter($Roles);
        $Text = $Name;
        if ( count($Roles) ) $Text .= " (" . implode(", ", $Roles) . ")";

        $Fields["bib_04"]["items"]["item_".++$Pos]   = array("text"   => $Text,
                                                             "action" => array(
                                                                               "type"   => "search",
                                                                               "search" => array(
                                                                                                 "query" => ( $Norm )
                                                                                                            ? "norm(" . $Norm . ")"
                                                                                                            : "author(" . $this->clear($Name) . ")",
                                                                                                 "type"  => "lukida"
                                                                                                )
                                                                            ),
                                                             "more"   => array());
      }
      // 700 People
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "700" )
      {
        $Name  = $this->formatPersonName($Rec);
        $Norm  = $this->getNorm($Rec);
        $Typ   = "B";
        $RecNr = explode("/", substr($Simple["6"],4))[0];
        // $this->CS->debug("ALX83", $RecNr);
        $Pos   = (isset($Fields["bib_05"]["items"]) && is_array($Fields["bib_05"]["items"])) ? count($Fields["bib_05"]["items"]) : 0;
        $Roles = $this->getPersonRoles($Rec, $Typ == "A");
        $Roles = array_filter($Roles);
        if ( $Typ == "A" )  $Roles = array_diff($Roles, array("aut"));

        foreach ( $Roles as &$Role )
        {
          $Role = $this->CS->getText("role.".$Role);
        }
        $Roles = array_filter($Roles);
        $Text = $Name;
        if ( count($Roles) ) $Text .= " (" . implode(", ", $Roles) . ")";

        $Fields["bib_05"]["items"]["item_".++$Pos]   = array("text"   => $Text,
                                                             "action" => array(
                                                                               "type"   => "search",
                                                                               "search" => array(
                                                                                                 "query" => ( $Norm )
                                                                                                            ? "norm(" . $Norm . ")"
                                                                                                            : "author(" . $this->clear($Name) . ")",
                                                                                                 "type"  => "lukida"
                                                                                                )
                                                                            ),
                                                             "more"   => array());

      }
      */

      // 264 Published
      /*
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "264" )
      {
        $Pos = (isset($Fields["bib_12"]["items"]) && is_array($Fields["bib_12"]["items"])) ? count($Fields["bib_12"]["items"]) : 0;
        if ( isset($Simple["a"]) && $Simple["a"] != "" )
        {
          if ( !isset($Fields["bib_12"]["items"]) ) $Fields["bib_12"]["items"] = array();
          $Fields["bib_12"]["items"]["item_".++$Pos] =  array("text"   => $Simple["a"],
                                                              "action" => array());
        }
        if ( isset($Simple["b"]) && $Simple["b"] != "" )
        {
          if ( !isset($Fields["bib_12"]["items"]) ) $Fields["bib_12"]["items"] = array();
          $Fields["bib_12"]["items"]["item_".++$Pos] =  array("text"   => $Simple["b"],
                                                              "action" => array("type"   => "search",
                                                                                "search" => array(
                                                                                                  "query" => "publisher(" . $Simple["b"] . ")",
                                                                                                  "type"  => "lukida"
                                                                                                 )));
        }
        if ( isset($Simple["c"]) && $Simple["c"] != "" )
        {
          if ( !isset($Fields["bib_12"]["items"]) ) $Fields["bib_12"]["items"] = array();
          $Fields["bib_12"]["items"]["item_".++$Pos] =  array("text"   => $Simple["c"],
                                                              "action" => array("type"   => "search",
                                                                                "search" => array(
                                                                                                  "query" => "year(" . $Simple["c"] .")",
                                                                                                  "type"  => "lukida"
                                                                                                 )));
        }
      }
      */

      // 490 Part
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "490" )
      {
        $Tmp = "";
        if ( isset($Simple["a"]) && $Simple["a"] != "" )  $Tmp .= $Simple["a"];
        $Pos  = (isset($Fields["bib_08"]["items"]) && is_array($Fields["bib_08"]["items"])) ? count($Fields["bib_08"]["items"]) : 0;
        if ( !isset($Fields["bib_08"]["items"]) ) $Fields["bib_08"]["items"] = array();
        $Fields["bib_08"]["items"]["item_".++$Pos] = array("display" => "newline");
        $Fields["bib_08"]["items"]["item_".++$Pos] = array("text" => $Tmp,
                                                           "action" => array("type"   => "search",
                                                                             "search" => array(
                                                                                               "query" => "series(" . $Simple["a"] . ")",
                                                                                               "type"  => "lukida"
                                                                                              )));
        $Tmp = "";
        if ( isset($Simple["v"]) && $Simple["v"] != "" )  $Tmp .= $Simple["v"];
        $Fields["bib_08"]["items"]["item_".++$Pos] = array("text"   => $Tmp,
                                                           "action" => array());
      }

      // 250 Edition
      if ( isset($Simple["6"]) && substr($Simple["6"],0,3) == "250" && isset($Simple["a"]) )
      {
        $Pos  = (isset($Fields["bib_10"]["items"]) && is_array($Fields["bib_10"]["items"])) ? count($Fields["bib_10"]["items"]) : 0;
        if ( !isset($Fields["bib_10"]["items"]) ) $Fields["bib_10"]["items"] = array();
        $Fields["bib_10"]["items"]["item_".++$Pos] = array("text" => $Simple["a"], "action" => array());
      }
    }
    return $Fields;
  }

  // Bereitet einen Wert fuer einen Klammerbefehl auf ("series(...)"), indem er
  // in Anfuehrungszeichen gesetzt wird.
  //
  // Bis zum 14.08.2026 wurden hier zusaetzlich "(" und ")" durch die
  // ZEICHENFOLGEN "%28"/"%29" ersetzt. Das ging schief: der Wert wird spaeter
  // beim Bau der Adresse noch einmal kodiert, aus "%28" wurde "%2528", und nach
  // dem einen Dekodieren des Browsers stand im Suchtext woertlich "%28LVermGeo%29"
  // statt "(LVermGeo)". Die Suche lief ins Leere - zurueckverwandelt wurde das
  // nirgends. Noetig war die Ersetzung ohnehin nie: die Zerlegung der Befehle in
  // solr.php behandelt Anfuehrungszeichen als eigene Alternative, Klammern
  // INNERHALB der Anfuehrungszeichen beenden den Befehl also nicht.
  // Beispiel: id:1688778462, Reihe "Topographische Karte 1:25 000 ... (LVermGeo)".
  private function clear($Str)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    return "\"" . trim($Str ?? "") . "\"";
  }

  private function formatAddInfo($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Retn = array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "856" => array("3","t","u","x","y","z")));
    // Newline
    foreach ( $Recs as $Rec )
    {
      $Link  = $this->getSFTexts($Rec, "u");
      if ( !$Link ) continue;

      if ( $Item & count($Recs) ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Add  = array();
      $Add[] = $this->getSFTexts($Rec, "3");
      $Add[] = $this->getSFTexts($Rec, "t");
      $Add[] = $this->getSFTexts($Rec, "x");
      $Add[] = $this->getSFTexts($Rec, "y");
      $Add[] = $this->getSFTexts($Rec, "z");
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]    = array("text"   => (count($Add) ? implode(", ", $Add) : "Online") . " (" . $this->CS->getHost($Link) . ")",
                                        "action" => array(
                                                          "type"   => "link",
                                                          "link"    => array(
                                                                             "url"    => $Link,
                                                                             "target" => "_blank"
                                                                            )
                                                         ),
                                        "more"   => array()
                                       );
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatCits($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Retn = array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "510" => array("a")));
    // NO Newline
    $Add  = array();
    foreach ( $Recs as $Rec )
    {
      $Add[] = $this->getSFTexts($Rec, "a");
    }

    $Add = array_filter($Add);
    if ( !count( $Add) ) return array();

    $Retn["item_".++$Item] = array("text"   => implode(" | ", $Add),
                                   "action" => array(),
                                   "more"   => array());

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatAddTitl($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Retn = array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "246" => array("I1","a","b","i")));

    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["I1"]["0"]) || $Rec["I1"]["0"] != "1" ) continue;
      if ( !isset($Rec["a"]["0"]) ) continue;

      if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Add  = array();
      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "b", ": ");
      $Add[] = $this->getSFTexts($Rec, "i", " (", ")");
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]  = array("text"   => implode("", $Add),
                                      "action" => array(),
                                      "more"   => array()
                                     );
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatFormat($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Retn["item_1"] = array("text"   => $this->CS->getText("format." . str_replace(" ", "", strtolower($this->Format))),
                            "action" => array(),
                            "more"   => array());

    $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    return $Returns;
  }

  private function formatArray($Seq, $Label, $Field, $Subfield)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getSubfield($this->Prefix . $Field, $Subfield);
    if ( empty($Recs) )  return array();

    $Array = array();
    $Item  = 0;
    foreach ( $Recs as $Rec )
    {
      $Array["item_".++$Item]   = array("text"   => $Rec,
                                        "action" => array(),
                                        "more"   => array()
                                       );
    }

    return array(("bib_".$Seq) => array(
                                "label"  => $this->CS->getText("bibline.".$Label),
                                "code"   => $Label,
                                "items"  => $Array
                               )
                );
  }

  private function formatCDBClass($Recs)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Class = array();

    if ( isset($Recs["name"])  && count($Recs["name"]) )
    {
      foreach ( $Recs["name"] as $Ind => $Name )
      {
        $Class["item_".($Ind+1)] = array("text" => trim($Name));
        if ( isset($Recs["ppn"][$Ind]) )
        {
          $Class["item_".($Ind+1)]["action"] = array(
                                                    "type"   => "search",
                                                    "search" => array(
                                                                      "query" => "norm(" . $Recs["ppn"][$Ind] . ")",
                                                                      "type"  => "lukida"
                                                                     )
                                                  );
        }
      }
    }
    return $Class;
  }

  private function formatClass($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Achtung: Globale Klassifikation hier | Lokale Klassifikation mit "L" unterhalb

    $Recs = $this->getCompleteArray(array($this->Prefix . "084" => array("2","a")));
    $Item = 0;
    $Clas = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) || !isset($Rec["2"]["0"]) ) continue;

      $CSys = strtoupper($Rec["2"]["0"]);   // Shortcut of system
      foreach ( $Rec["a"] as $Code )        // Code
      {
        $CRec = $this->CS->getCDBClass($CSys, $Code);
        if ( isset($CRec["link"]) && trim($CRec["link"]) )
        {
          $CRec["link"] = str_replace("{class}", $Code, $CRec["link"]);
        }

        if ( isset($CRec["description"]) )
        {
          $Clas["item_".++$Item]  = array("text"   => $CRec["description"],
                                          "code"   => $CSys . " " . $Code,
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                              "query" => "topic(" . $CSys . " " . $Code . ")",
                                                                              "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"   => $CRec
                                         );
        }
        else
        {
          $Clas["item_".++$Item]  = array("text"   => $CSys . " " . $Code,
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                              "query" => "topic(" . $CSys . " " . $Code . ")",
                                                                              "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"    => array()
                                         );
        }
      }
    }

    $Classes = array();
    if ( !empty($Clas) )
    {
      $Classes["bib_".$Seq] = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Clas
                                   );
    }
    return $Classes;
  }

  private function formatClassL($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Achtung: Lokale Klassifikation hier mit "L"| Globale Klassifikation ohne "L" oberhalb

    $Recs = $this->getCompleteArray(array($this->Prefix . "983" => array("a","b")));
    $Item = 0;
    $Clas = array();

    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;
      $Text = isset($Rec["b"]["0"]) ? $Rec["b"]["0"] : $Rec["a"]["0"];
      $Code = $Rec["a"]["0"];
      // Woher der Text der lokalen Systematik kommt - Schalter statt Clientname
      // (03.09.2026, Stufe 2 Teil A):
      //
      //   text   ueber classlocal.<Text> nachschlagen        (Vorgabe)
      //   none   gar nicht nachschlagen                      (bisher VechtaUB)
      //   code2  ueber die ersten zwei Teile des CODES       (bisher MagdeburgOVGU)
      //
      // Der frueher fuer GoettingenSUB angehaengte Zusatz " (GOK <Code>)" faellt
      // ersatzlos weg: dafuer gibt es bibliographic.classwithcode, und die
      // Oberflaeche haengt den Code ohnehin an (BiblioLine.vue::classLabel).
      // Auf dem Schirm stand dadurch "RD (GOK RD)RD" - derselbe Wert dreimal
      // (nachgesehen an id 029692539).
      $ClassText = ( isset($this->CS->configs["bibliographic"]["classlocaltext"])
                  && trim($this->CS->configs["bibliographic"]["classlocaltext"]) )
                   ? strtolower(trim($this->CS->configs["bibliographic"]["classlocaltext"])) : "text";

      if ( $ClassText == "text" )
      {
         $Tmp  =  $this->CS->getText("classlocal.". strtolower(str_replace(" ", "", $Text)));
         if ( $Tmp )  $Text = $Tmp;
      }
       if ( $ClassText == "code2" )
       {
         // Lokale Systematik der OVGU (17.08.2026). Der Systematikpunkt steht
         // in M983a, dort aber nur in den ersten beiden durch Leerzeichen
         // getrennten Teilen; alles dahinter ist Beiwerk und gehoert nicht zum
         // Schluessel:
         //   "B. 206"                      -> Punkt "B. 206",  classlocal.b.206
         //   "F/E 910 <Hegel, ...> Theu"    -> Punkt "F/E 910", classlocal.f/e910
         // Angezeigt wird "<Punkt> <Text>". Der Code kommt damit schon im Text
         // vor und wird geleert, damit die Oberflaeche ihn nicht ein zweites
         // Mal in Klammern anhaengt. Ohne passenden Text bleibt alles, wie es
         // aus dem Datensatz kam.
         $Teile = preg_split('/\s+/', trim($Code));
         $Punkt = implode(" ", array_slice($Teile, 0, 2));
         $Tmp   = $this->CS->getText("classlocal." . strtolower(str_replace(" ", "", $Punkt)));
         if ( $Tmp )
         {
           $Text = $Punkt . " " . $Tmp;
           $Code = "";
         }
       }

      $Clas["item_".++$Item]  = array("text"   => $Text,
                                      "code"   => $Code,
                                      "action" => array(
                                                        "type"   => "search",
                                                        "search" => array(
                                                                           "query" => "classlocal(" . $this->clear($Rec["a"]["0"]) . ")",
                                                                           "type"  => "lukida"
                                                                         )
                                                       ),
                                      "more"   => array()
                                     );
    }

    $Classes = array();
    if ( !empty($Clas) )
    {
      $Classes["bib_".$Seq] = array(
                                   "label"  => $this->CS->getText("bibline.".$Label),
                                   "code"   => $Label,
                                   "items"  => $Clas
                                  );
    }
    return $Classes;
  }

  private function formatCollec($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "935" => array("a")));
    $Item = 0;
    $Col  = array();

    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;
 
      $Text =  $this->CS->getText("collection.". strtolower(str_replace(" ", "", $Rec["a"]["0"])));
      if ( !$Text ) continue;
 
      $Col["item_".++$Item]   = array("text"   => $Text,
                                      "action" => array(
                                                        "type"   => "search",
                                                        "search" => array(
                                                                           "query" => "package(BIBCOLL_" . $Rec["a"]["0"] . ")",
                                                                           "type"  => "lukida"
                                                                         )
                                                       ),
                                      "more"   => array()
                                     );
    }

    $Cols = array();
    if ( !empty($Col) )
    {
      $Cols["bib_".$Seq] = array(
                                   "label"  => $this->CS->getText("bibline.".$Label),
                                   "code"   => $Label,
                                   "items"  => $Col
                                  );
    }
    return $Cols;
  }

  private function formatSize($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "300" => array("a","b","c")));
    $Item = 0;
    $Col  = array();

    foreach ( $Recs as $Rec )
    {
      // if ( !isset($Rec["a"]["0"]) ) continue;
 
      $Add  = array();
      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "b", " : ");
      $Add[] = $this->getSFTexts($Rec, "c", " ; ");
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]  = array("text"   => implode("", $Add),
                                      "action" => array(),
                                      "more"   => array()
                                     );
    }

    $Retns = array();
    if ( !empty($Retn) )
    {
      $Retns["bib_".$Seq] = array(
                                   "label"  => $this->CS->getText("bibline.".$Label),
                                   "code"   => $Label,
                                   "items"  => $Retn
                                  );
    }
    return $Retns;
  } 

  private function formatCorp($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Retn = array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "110" => array(" 0","4","6","a","b","c","d"),
                                          $this->Prefix . "111" => array(" 0","4","6","a","b","c","d")));
    $RecsOrg    = $this->getCompleteArray(array($this->Prefix . "880" => array("4","6","a","b","c")));
    
    foreach ( $Recs as $Field => $Rec )
    {
      $Text = "";
      $Tex  = array();
      $Add  = array();
      $Norm = $this->getNorm($Rec);
      $NormGND = $this->getGNDNorm(substr($Field,0,4) == "M110" ? "organisation" : "event", $Rec);

      $Text = "";
      $Add  = array();
      $Norm = $this->getNorm($Rec);
      $NormGND = $this->getGNDNorm("organisation", $Rec);

      $Text  = $this->getSFTexts($Rec, "a");
      $Text .= $this->getSFTexts($Rec, "b", ". ");
      $Text .= $this->getSFTexts($Rec, "c", " ");
      $Text .= $this->getOrgCorp(substr($Field,1,3), $Rec, $RecsOrg);
      if ( !$Text ) continue;

      $Add[] = $this->getSFTexts($Rec, "g");
      $Roles = $this->getPersonRoles($Rec, false);
      if ( count($Roles) )
      {
        foreach ( $Roles as &$Role ) {
          $Role = $this->CS->getText("role.".$Role);
         }
        $Roles = array_filter($Roles);
      }
      if ( count($Roles) ) {
        $Add = [...$Add,
                ...$Roles];
      } else {
        $Add[] = $this->getSFTexts($Rec, "e");
      }
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]    = array("text"   => count($Add) ? $Text . " (" . implode(", ", $Add) . ")" : $Text,
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array("query" => ( $Norm )
                                                                                       ? "norm(" . $Norm . ")"
                                                                                       : "corporation(" . $this->clear($Text) . ")",
                                                                            "type"  => "lukida")
                                                         ),
                                        "more"   => $NormGND);
    }

    $Recs       = $this->getCompleteArray(array($this->Prefix . "710" => array(" 0","4","6","a","b","e","g")));
    $RecsOrg    = $this->getCompleteArray(array($this->Prefix . "880" => array( "4","6","a","b","c")));
    // Newline
    if ( $Item & count($Recs) ) $Retn["item_".++$Item]     = array("display" => "newline");
    foreach ( $Recs as $Field => $Rec )
    {
      $Text = "";
      $Add  = array();
      $Norm = $this->getNorm($Rec);
      $NormGND = $this->getGNDNorm("organisation", $Rec);

      $Text  = $this->getSFTexts($Rec, "a");
      $Text .= $this->getSFTexts($Rec, "b", ". ");
      $Text .= $this->getSFTexts($Rec, "c", " ");
      $Text .= $this->getOrgCorp(substr($Field,1,3), $Rec, $RecsOrg);
      if ( !$Text ) continue;

      $Add[] = $this->getSFTexts($Rec, "g");
      $Roles = $this->getPersonRoles($Rec, false);
      if ( count($Roles) )
      {
        foreach ( $Roles as &$Role ) {
          $Role = $this->CS->getText("role.".$Role);
         }
        $Roles = array_filter($Roles);
      }
      if ( count($Roles) ) {
        $Add = [...$Add,
                ...$Roles];
      } else {
        $Add[] = $this->getSFTexts($Rec, "e");
      }
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]    = array("text"   => count($Add) ? $Text . " (" . implode(", ", $Add) . ")" : $Text,
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array(
                                                                            "query" => ( $Norm ) ? "norm(" . $Norm . ")" : "corporation(" . $this->clear($Text) . ")",
                                                                            "type"  => "lukida"
                                                                           )
                                                         ),
                                        "more"   => $NormGND
                                       );
      $Retn["item_".++$Item]     = array("display" => "newline");
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatPart($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Tmp   = array("A" => [],
                   "N" => [],
                   "P" => [],
                   "V" => []);
    $Parts = array();
    $Item  = 0;


    // Block 245np
    $Recs = $this->getCompleteArray(array($this->Prefix . "245" => array("n","p")));
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["n"]) && count($Rec["n"]) )
      {
        foreach ($Rec["n"] as $Text)
        {
          $Tmp["N"][] = $Text;
        }
      }
      if ( isset($Rec["p"]) && count($Rec["p"]) )
      {
        foreach ($Rec["p"] as $Text)
        {
          $Tmp["P"][] = $Text;
        }
      }
    }
    if ( count($Tmp["N"]) )
    {
      foreach ( $Tmp["N"] as $I => $N )
      {
        if ( isset($Tmp["P"][$I]) )
        {
          $Parts[] = $N . ": " . $Tmp["P"][$I];
        }
        else
        {
          $Parts[] = $N;
        }
      }
    }

    // Block 773g
    if ( !count($Parts) )
    {
      $Recs = $this->getCompleteArray(array($this->Prefix . "773" => array("g")));
      foreach ( $Recs as $Rec )
      {
        if ( isset($Rec["g"]) && count($Rec["g"]) )
        {
          foreach ($Rec["g"] as $Text)
          {
            if ( substr($Text, 0, 7) == "volume:" || substr($Text, 0, 5) == "year:"
              || substr($Text, 0, 7) == "number:" || substr($Text, 0, 6) == "pages:"
              || substr($Text, 0, 6) == "month:"  || substr($Text, 0, 7) == "extent:"
              || substr($Text, 0, 4) == "day:" )  continue;
            // "day:" auch am ENDE eines Wertes entfernen (03.09.2026).
            //
            // Die Liste darueber faengt nur Werte, die MIT dem Schluesselwort
            // beginnen. Manche Lieferungen haengen es aber an denselben Wert an:
            //   "35(1997), 12 vom: 28. Dez., Seite 927-35; day:28"
            // Der Tag steht im Text ohnehin schon ("28. Dez."), und in der
            // kleinen Kachel stand er als technischer Rest sichtbar dahinter.
            $Text = preg_replace('/\s*;\s*day:\s*\d+\s*$/u', "", $Text);
            if ( trim($Text) === "" )  continue;
            $Parts[] = $Text;
          }
        }
      }
    }

    // Block 490av
    if ( !count($Parts) )
    {
      $Recs = $this->getCompleteArray(array($this->Prefix . "490" => array("a","v")));
      foreach ( $Recs as $Rec )
      {
        if ( isset($Rec["a"]) && count($Rec["a"]) )
        {
          foreach ($Rec["a"] as $Text)
          {
            $Tmp["A"][] = $Text;
          }
        }
        if ( isset($Rec["v"]) && count($Rec["v"]) )
        {
          foreach ($Rec["v"] as $Text)
          {
            $Tmp["V"][] = $Text;
          }
        }
      }
      if ( count($Tmp["A"]) )
      {
        foreach ( $Tmp["A"] as $I => $A )
        {
          if ( isset($Tmp["V"][$I]) )
          {
            $Parts[] = $A . ": " . $Tmp["V"][$I];
          }
          else
          {
            $Parts[] = $A;
          }
        }
      }
    }

    $Parts = array_unique($Parts);

    $AllParts = array();
    if ( count($Parts) )
    {
      $AllParts["item_".++$Item]  = array("text"   => implode("; ", $Parts),
                                          "action" => array(),
                                          "more"   => array()
                                         );
    }

    $Return = array();
    if ( !empty($AllParts) )
    {
      $Return["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $AllParts
                                         );
    }
    return $Return;
  }

  private function internArtl($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "773" => array("I1", "d", "g", "t", "w")));
    if ( empty($Recs) )  return array();


    $Parts = array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["I1"][0]) && $Rec["I1"][0] == "1" )
      {
        if ( isset($Rec["g"]) && count($Rec["g"]) )
        {
          foreach ($Rec["g"] as $Text)
          {
            if ( substr($Text, 0, 5) == "year:" )    $Parts["year"]   = substr($Text,5);
            if ( substr($Text, 0, 7) == "number:" )  $Parts["number"] = substr($Text,7);
            if ( substr($Text, 0, 6) == "pages:" )   $Parts["pages"]  = substr($Text,6);
            if ( substr($Text, 0, 7) == "volume:" )  $Parts["volume"] = substr($Text,7);
          }
        }
      }
      else
      {
        if ( isset($Rec["d"]) && count($Rec["d"]) )
        {
          foreach ($Rec["d"] as $Text)
          {
            $Parts["publisher"] = $Text;
          }
        }

        if ( isset($Rec["g"]) && count($Rec["g"]) )
        {
          foreach ($Rec["g"] as $Text)
          {
            $Parts["part"] = $Text;
          }
        }

        if ( isset($Rec["t"]) && count($Rec["t"]) )
        {
          foreach ($Rec["t"] as $Text)
          {
            $Parts["journal"] = $Text;
          }
        }

        if ( isset($Rec["w"]) && count($Rec["w"]) )
        {
          foreach ($Rec["w"] as $Text)
          {
            if ( substr($Text, 0, 8) == "(DE-600)" ) $Parts["zdbid"]  = substr($Text,8);
            if ( substr($Text, 0, 8) == "(DE-627)" ) $Parts["rootid"] = substr($Text,8);
          }
        }
      }
    }

    $Return = array();
    if ( !empty($Parts) )
    {
      $Return["int_".$Seq] = array("code"   => $Label,
                                   "items"  => $Parts
                                  );
    }
    return $Return;
  }

  private function internPubl($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "264" => array("a", "b", "c")));

    $Publ = array();

    // Only first record
    if ( isset($Recs["M264_0"]) )
    {
      $Rec = $Recs["M264_0"];
      $Publ["place"]     = $this->getSFTexts($Rec, "a");
      $Publ["publisher"] = $this->getSFTexts($Rec, "b");
      $Publ["year"]      = $this->getSFTexts($Rec, "c");
    }

    $Return = array();
    if ( !empty($Publ) )
    {
      $Return["int_".$Seq] = array("code"   => $Label,
                                   "items"  => $Publ
                                  );
    }
    return $Return;
  }

  private function internDetl($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Detl = array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "502" => array("a", "b", "c", "d")));

    // Only first record
    if ( isset($Recs["M502_0"]) )
    {
      $Rec   = $Recs["M502_0"];
      $Add   = array();

      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "b");
      $Add   = array_filter($Add);

      $Detl["type"]        = implode(" ", $Add);
      $Detl["institution"] = $this->getSFTexts($Rec, "c");
      $Detl["year"]        = $this->getSFTexts($Rec, "d");
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "300" => array("a")));

    // Only first record
    if ( isset($Recs["M300_0"]) )
    {
      $Rec   = $Recs["M300_0"];
      $Detl["description"] = $this->getSFTexts($Rec, "a");
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "256" => array("a")));

    // Only first record
    if ( isset($Recs["M256_0"]) )
    {
      $Rec   = $Recs["M256_0"];
      $Detl["computerfile"] = $this->getSFTexts($Rec, "a");
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "520" => array("a")));

    // Only first record
    if ( isset($Recs["M520_0"]) )
    {
      $Rec   = $Recs["M520_0"];
      $Detl["summary"] = $this->getSFTexts($Rec, "a");
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "655" => array("a")));

    // Only first record
    if ( isset($Recs["M655_0"]) )
    {
      $Rec   = $Recs["M655_0"];
      $Detl["genre"] = $this->getSFTexts($Rec, "a");
    }

    $Return = array();
    if (!empty($Detl) )
    {
      $Return["int_".$Seq] = array("code"   => $Label,
                                   "items"  => $Detl
                                  );
    }
    return $Return;
  }

  private function internLink($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "856" => array("u")));

    $Link = array();

    // Only first record
    if ( isset($Recs["M856_0"]) )
    {
      $Rec = $Recs["M856_0"];
      $Link["volltext"]  = $this->getSFTexts($Rec, "u");
    }

    $Return = array();
    if ( !empty($Link) )
    {
      $Return["int_".$Seq] = array("code"   => $Label,
                                   "items"  => $Link
                                  );
    }
    return $Return;
  }

  private function formatISBN($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Retn = array();

    // ISBN: je Nummer eine eigene Zeile. Bevorzugt wird Unterfeld "9" (mit
    // Bindestrichen, besser lesbar), sonst "a" (ohne). Doppelte fallen weg -
    // verglichen wird OHNE Bindestriche, weil dieselbe Nummer sonst in beiden
    // Schreibweisen doppelt erschiene (2026-08-11).
    $Seen  = array();
    $Recs  = $this->getCompleteArray(array($this->Prefix . "020" => array("9","a")));
    foreach ( $Recs as $Rec )
    {
      $Text = trim($this->getSFTexts($Rec, "9"));
      if ( !$Text )  $Text = trim($this->getSFTexts($Rec, "a"));
      if ( !$Text )  continue;

      $Key = strtoupper(str_replace(array("-", " "), "", $Text));
      if ( isset($Seen[$Key]) )  continue;
      $Seen[$Key] = true;

      if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Retn["item_".++$Item]  = array("text"   => $Text,
                                      "action" => array(),
                                      "more"   => array()
                                     );
    }

    $Recs  = $this->getCompleteArray(array($this->Prefix . "024" => array("a")));
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;

      $value = trim($this->getSFTexts($Rec, "a"));
      $normalized = str_replace('-', '', $value);

      //DOI raus
      if (strpos($value, "10.") === 0) continue;

      //URN raus
      if (strpos($value, "urn:") === 0) continue;

      //alles was keine ISBN ist raus
      if (!preg_match('/^(97[89])?\d{9}[\dxX]$/', $normalized)) continue;

      // auch gegen die Nummern aus 020 pruefen - sonst steht dieselbe ISBN
      // zweimal da, einmal mit und einmal ohne Bindestriche.
      $Key = strtoupper($normalized);
      if ( isset($Seen[$Key]) )  continue;
      $Seen[$Key] = true;

      if ( $Item ) $Retn["item_".++$Item] = array("display" => "newline");

      $Retn["item_".++$Item] = array(
      "text"   => $value,
      "action" => array(),
      "more"   => array()
      );
    }
    if ( !count($Retn) )
    {
      $Recs  = $this->getCompleteArray(array($this->Prefix . "773" => array("z")));
      foreach ( $Recs as $Rec )
      {
        if ( !isset($Rec["z"]["0"]) ) continue;

        if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

        $Add   = array();
        $Add[] = $this->getSFTexts($Rec, "z");

        $Retn["item_".++$Item]  = array("text"   => implode(" ", $Add),
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    if ( !count($Retn) )
    {
      $Recs  = $this->getCompleteArray(array($this->Prefix . "776" => array("z")));
      foreach ( $Recs as $Rec )
      {
        if ( !isset($Rec["z"]["0"]) ) continue;

        if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

        $Add   = array();
        $Add[] = $this->getSFTexts($Rec, "z");
        $Retn["item_".++$Item]  = array("text"   =>  implode(" ", $Add),
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    // Doppelte entfernen - die TRENNMARKEN dabei nicht mitzaehlen (03.09.2026).
    //
    // Vorher stand hier getUniqueMultArray(). Das vergleicht die Eintraege als
    // Ganzes, und die Trennmarken zwischen zwei Nummern sind untereinander
    // gleich ({"display":"newline"}): die zweite galt als Doppelung und fiel
    // weg. Bei drei ISBN klebte die dritte deshalb ohne Umbruch an der zweiten
    // ("978-3-608-94462-4978-3-7910-3025-8", gemeldet an id 523890060).
    // Ausserdem laesst array_unique die Schluessel stehen, es blieb also eine
    // Luecke in der Nummerierung.
    //
    // Die Marke wird nur zurueckgehalten und erst gesetzt, wenn wirklich eine
    // weitere Nummer folgt - sonst entstuende eine Leerzeile.
    $Sauber  = array();
    $Gesehen = array();
    $Nr      = 0;
    $Marke   = null;
    foreach ( $Retn as $Eintrag )
    {
      if ( !isset($Eintrag["text"]) )
      {
        if ( $Nr )  $Marke = $Eintrag;   // vor der ersten Nummer nie
        continue;
      }
      $Schluessel = strtoupper(str_replace(array("-", " "), "", (string) $Eintrag["text"]));
      if ( $Schluessel === "" || isset($Gesehen[$Schluessel]) )  continue;
      $Gesehen[$Schluessel] = true;

      if ( $Marke !== null ) { $Sauber["item_".++$Nr] = $Marke; $Marke = null; }
      $Sauber["item_".++$Nr] = $Eintrag;
    }
    $Retn = $Sauber;

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq] = array(
                                  "text"   => $this->CS->getText("bibline.".$Label),
                                  "code"   => $Label,
                                  "items"  => $Retn
                                  );
    }
    return $Returns;
  }

  private function formatEdition($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs  = $this->getCompleteArray(array($this->Prefix . "348" => array("a")));
    $Item  = 0;
    $Edits = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;
      $Edits["item_".++$Item]  = array("text"   => str_ireplace(array(", Musikalische Ausgabeform", "Musikalische Ausgabeform"), "", $Rec["a"]["0"]),
                                       "action" => array(),
                                       "more"   => array()
                                      );
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "250" => array("a","b")));
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;
      $Edits["item_".++$Item]  = array("text"   => str_ireplace(array(", Musikalische Ausgabeform", "Musikalische Ausgabeform"), "", $Rec["a"]["0"]),
                                       "action" => array(),
                                       "more"   => array()
                                      );
      if ( !isset($Rec["b"]["0"]) ) continue;
      $Edits["item_".++$Item]  = array("text"   => "/ " . $Rec["b"]["0"],
                                       "action" => array(),
                                       "more"   => array()
                                      );
    }

    $Editions = array();
    if ( !empty($Edits) )
    {
      $Editions["bib_".$Seq] = array(
                                         "text"   => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $Edits
                                         );
    }
    return $Editions;
  }

  private function formatGenre($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "655" => array("0","a","x","y","z")));
    $Item = 0;
    $Gens = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;

      $Unter = array();
      if ( isset($Rec["x"]["0"]) ) $Unter[] = $Rec["x"]["0"];
      if ( isset($Rec["y"]["0"]) ) $Unter[] = $Rec["y"]["0"];
      if ( isset($Rec["z"]["0"]) ) $Unter[] = $Rec["z"]["0"];

      $Norm = $this->getNorm($Rec);
      $Gens["item_".++$Item]  = array("text"   => $Rec["a"]["0"],
                                       "action" => array(
                                                         "type"   => "search",
                                                         "search" => array(
                                                                            "query" => ( $Norm ) ? "norm(" . $Norm . ")"
                                                                                                 : "genre(" . $this->clear($Rec["a"]["0"]) . ")",
                                                                            "type"  => "lukida"
                                                                          )
                                                        ),
                                       "more"   => array()
                                      );
      if ( count($Unter) )
      {
        $Gens["item_".++$Item]  = array("text"   => " (" . implode(", ", $Unter) . ")",
                                        "action" => array(),
                                        "more"   => array()
                                       );
         }
    }

    $Genres = array();
    if ( !empty($Gens) )
    {
      $Genres["bib_".$Seq] = array(
                                   "label"  => $this->CS->getText("bibline.".$Label),
                                   "code"   => $Label,
                                   "items"  => $Gens
                                  );
    }
    return $Genres;
  }

  private function formatIn($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "830" => array("a","b","p","v","w")));
    if ( !isset($Recs[$this->Prefix . "830_0"]["a"]["0"]) )
    {
      $Recs490 = $this->getCompleteArray(array($this->Prefix . "490" => array("a")));
      $Recs[$this->Prefix . "830_0"]["a"]["0"] = isset($Recs490[$this->Prefix . "490_0"]["a"]["0"]) ? $Recs490[$this->Prefix . "490_0"]["a"]["0"] : "";
    }

    $Item = 0;
    $Is   = array();
    foreach ( $Recs as $Rec )
    {
      $Text  = "";
      $Link  = "";
      if ( isset($Rec["a"][0]) && $Rec["a"][0] != "" )  $Text .= $Rec["a"][0] . " ";
      if ( isset($Rec["b"][0]) && $Rec["b"][0] != "" )  $Text .= $Rec["b"][0] . " ";
      if ( isset($Rec["p"][0]) && $Rec["p"][0] != "" )  $Text .= $Rec["p"][0] . ". ";
      if ( isset($Rec["v"][0]) && $Rec["v"][0] != "" )  $Text .= $Rec["v"][0];
      if ( isset($Rec["w"][0]) && in_array(substr($Rec["w"][0],0,8), array("(DE-601)","(DE-627)") ) )
      {
        $Link =  trim(substr($Rec["w"][0],8));
      }
      if ( !$Text ) continue;

      if ( $Link )
      {
        $Is["item_".++$Item]  = array("text"   => $Text,
                                      "action" => array(
                                                        "type"   => "search",
                                                        "search" => array(
                                                                          "query" => "id(" . $Link . ")",
                                                                          "type"  => "lukida"
                                                                         )
                                                       ),
                                      "more"   => array()
                                     );
      }
      else
      {
        $Is["item_".++$Item]  = array("text"   => $Text,
                                      "action" => array(),
                                      "more"   => array()
                                     );
      }
    }

    $Ins = array();
    if ( !empty($Is) )
    {
      $Ins["bib_".$Seq] = array(
                                "label"  => $this->CS->getText("bibline.".$Label),
                                "code"   => $Label,
                                "items"  => $Is
                               );
    }
    return $Ins;
  }

  private function formatInclude($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getSubfield("501", "a");
    if ( empty($Recs) )  return array();

    return array(("bib_".$Seq) => array(
                                "label"  => $this->CS->getText("bibline.".$Label),
                                "code"   => $Label,
                                "items"  => array_unique($Recs)
                               )
                );
  }

  private function formatLicense($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "540" => array("a", "f","u")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $Lics = array();
    foreach ( $Recs as $Rec )
    {
      $Text  = ( isset($Rec["a"]["0"]) ) ? $Rec["a"]["0"] : "";
      $Text .= ( isset($Rec["f"]["0"]) ) ? " " . $Rec["f"]["0"] : "";
      $Link  = ( isset($Rec["u"]["0"]) ) ? $Rec["u"]["0"] : "";

      if ( $Link )
      {
        $Lics["item_".++$Item]  = array("text"   => $Text,
                                        "action" => array(
                                                          "type"    => "link",
                                                          "link"    => array(
                                                                             "url"    => $Link,
                                                                             "target" => "_blank"
                                                                            )
                                                         ),
                                        "more"   => array()
                                       );
      }
      else
      {
        $Lics["item_".++$Item]  = array("text"   => $Text,
                                        "action" => array(),
                                        "more"   => array()
                                        );
      }
    }

    $Licenses = array();
    if ( !empty($Lics) )
    {
      $Licenses["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $Lics
                                         );
    }
    return $Licenses;
  }

  private function formatOtherEs($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $OEds = array();

    // Vorgänger
    $Recs = $this->getCompleteArray(array($this->Prefix . "780" => array("a","b","d","g","h","i","q","t","w")));
    // if ( empty($Recs) )  return array();
    foreach ( $Recs as $Rec )
    {
      if ( $Item > 0 )
      {
        $OEds["item_".++$Item]  = array("display" => "newline");
      }

      $Text = "";
      $Tex  = array();
      $Add  = array();

      $Tex[] = $this->getSFTexts($Rec, "i");
      $Tex[] = $this->getSFTexts($Rec, "t");
      if ( count($Tex) ) $Text = implode(" ", $Tex);
      if ( !$Text ) $Text = $this->getSubfieldFirst($this->Prefix . "245", "a");

      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "d", ", ");
      $Add[] = $this->getSFTexts($Rec, "g", " ", ". ");
      $Add[] = $this->getSFTexts($Rec, "q", " ", $this->CS->getText("bibline.volume") . ": ");
      $Add[] = $this->getSFTexts($Rec, "h", " ");

      if ( isset($Rec["w"][0]) && in_array(substr($Rec["w"][0],0,8), array("(DE-601)","(DE-627)") ) )
      {
        $OEds["item_".++$Item]  = array("text"   => $Text . " " . implode ("", $Add),
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array(
                                                                            "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                            "type"  => "lukida"
                                                                            )
                                                         ),
                                        "more"   => array()
                                       );
      }
      else
      {
        $OEds["item_".++$Item]  = array("text"   => $Text . " " . implode ("", $Add),
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    // Nachfolger
    $Recs = $this->getCompleteArray(array($this->Prefix . "785" => array("a","b","d","g","h","i","q","t","w")));
    // if ( empty($Recs) )  return array();
    foreach ( $Recs as $Rec )
    {
      if ( $Item > 0 )
      {
        $OEds["item_".++$Item]  = array("display" => "newline");
      }

      $Text = "";
      $Tex  = array();
      $Add  = array();

      $Tex[] = $this->getSFTexts($Rec, "i");
      $Tex[] = $this->getSFTexts($Rec, "t");
      if ( count($Tex) ) $Text = implode(" ", $Tex);
      if ( !$Text ) $Text = $this->getSubfieldFirst($this->Prefix . "245", "a");

      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "d", ", ");
      $Add[] = $this->getSFTexts($Rec, "g", " ", ". ");
      $Add[] = $this->getSFTexts($Rec, "q", " ", $this->CS->getText("bibline.volume") . ": ");
      $Add[] = $this->getSFTexts($Rec, "h", " ");

      if ( isset($Rec["w"][0]) && in_array(substr($Rec["w"][0],0,8), array("(DE-601)","(DE-627)") ) )
      {
        $OEds["item_".++$Item]  = array("text"   => $Text . " " . implode ("", $Add),
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array(
                                                                            "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                            "type"  => "lukida"
                                                                            )
                                                         ),
                                        "more"   => array()
                                       );
      }
      else
      {
        $OEds["item_".++$Item]  = array("text"   => $Text . " " . implode ("", $Add),
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    $OtherEs = array();
    if ( !empty($OEds) )
    {
      $OtherEs["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $OEds
                                         );
    }
    return $OtherEs;
  }

  private function formatPeople($SeqA, $LabelA, $SeqB, $LabelB)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs       = $this->getCompleteArray(array($this->Prefix . "100" => array(" 0","4","6","a","c","d","e"),
                                                $this->Prefix . "700" => array(" 0","4","6","a","c","d","e")));
    $RecsOrg    = $this->getCompleteArray(array($this->Prefix . "880" => array( "6","a")));
    $Item   = array("A" => 0      ,"B" => 0);
    $Person = array("A" => array(),"B" => array());

    foreach ( $Recs as $Cat => $Rec )
    {
      // Author oder B
      $Typ  = (substr($Cat,1,3) == "100") ? "A" : "B";
      $Cnt  = $Item[$Typ];

      // Rollen
      $Roles = $this->getPersonRoles($Rec, $Typ == "A");
      $Roles = array_filter($Roles);
      if ( $Typ == "A" )  $Roles = array_diff($Roles, array("aut"));
      foreach ( $Roles as &$Role )
      {
        $Role = $this->CS->getText("role.".$Role);
      }
      $Roles = array_filter($Roles);

      $Name  = $this->formatPersonName($Rec);
      $Name .= $this->getOrgName($Typ == "A", $Rec, $RecsOrg);

      $Norm    = $this->getNorm($Rec);
      $NormGND = $this->getGNDNorm("person", $Rec);

      $Text  = $Name;
      $Datum = isset($Rec["d"][0]) ? $Rec["d"][0] : "";

      $Tmp = [];
      if ( trim($Datum) )    $Tmp[] = $Datum;
      if ( count($Roles) )   $Tmp[] = implode(", ", $Roles);

      if ( count($Tmp) ) $Text .= " (" . implode(", ", $Tmp) . ")";

      $Person[$Typ]["item_".++$Cnt]   = array("text"   => $Text,
                                              "action" => array(
                                                                "type"   => "search",
                                                                "search" => array(
                                                                                  "query" => ( $Norm )
                                                                                             ? "norm(" . $Norm . ")"
                                                                                             : "author(" . $this->clear($Name) . ")",
                                                                                  "type"  => "lukida"
                                                                                 )
                                                               ),
                                              "more"   => $NormGND);
      $Item[$Typ] = $Cnt;
    }

    $People = array();
    if ( !empty($Person["A"]) )
    {
      $People["bib_".$SeqA] = array(
                                    "label"  => $this->CS->getText("bibline.".$LabelA),
                                    "code"   => $LabelA,
                                    "items"  => $Person["A"]
                                   );
    }
    if ( !empty($Person["B"]) )
    {
      $People["bib_".$SeqB] = array(
                                    "label"  => $this->CS->getText("bibline.".$LabelB),
                                    "code"   => $LabelB,
                                    "items"  => $Person["B"]
                                   );
    }
    return $People;
  }

  private function formatPersonName($One)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( isset($One["a"][0]) && $One["a"][0] != "" && isset($One["c"][0]) && $One["c"][0] != "" )
    {
      return $One["a"][0] . " " . $One["c"][0];
    }
    if ( !isset($One["a"][0]) && isset($One["c"][0]) && $One["c"][0] != "" )
    {
      return $One["a"][0];
    }
    if ( isset($One["a"][0]) && $One["a"][0] != "" && !isset($One["c"][0]) )
    {
      return $One["a"][0];
    }
  }

  private function formatProven($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $PROV    = ( isset($this->CS->configs["bibliographic"]["provenance"]) && $this->CS->configs["bibliographic"]["provenance"] )
               ? trim(strtolower($this->CS->configs["bibliographic"]["provenance"])) : "";
    if ( !$PROV ) return array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "361" => array("0","5","a","f","k","l","o","s","u","z")));
    // $Recs = $this->getCompleteArray(array($this->Prefix . "561" => array("3","5","a")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $Pros = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["5"]["0"]) || !isset($Rec["a"]["0"]) ) continue;
      if ( !isset($Rec["5"]["0"]) && ( $PROV == "iln" || $PROV == "iln*" ) ) continue;

      if ( $PROV == "iln" )
      {
        if ( !in_array($Rec["5"]["0"],$this->CS->configs["isils"]) )  continue;
      }
      if ( $PROV == "iln*" )
      {
        // $this->CS->debug("ALXISIL", $this->CS->configs["isils"]);
        $Found = false;
        foreach ($this->CS->configs["isils"] as $I)
        {
          // Es wird ISIL- geprüft, also mit Strich hinter ISIL
          if ( $I == $Rec["5"]["0"] || strncmp($I . "-", $Rec["5"]["0"], strlen($I)+1) === 0 )
          {
            $Found = true;
            break;
          }
        }
        if ( !$Found ) continue;
      }

      $CRec = $this->getCDBIsil($Rec["5"]["0"]);
      if ( !isset($CRec["name"]) || !isset($CRec["infos"]["shortname"]) )  continue;

      if ( $Item > 0 )
      {
        $Pros["item_".++$Item]  = array("display" => "newline");
      }

      $Add  = array();
      $Norm = $this->getNorm($Rec);


      $Text  = $CRec["infos"]["shortname"];
      if ( !$Text ) continue;

      $Add[] = $Text;
      $Add[] = $this->getSFTexts($Rec, "s", ", ");

      $Bez   = $this->getSFTexts($Rec, "o", ", ");
      $Name  = $this->getSFTexts($Rec, "a", "");
      $Add[] = ($Bez) ? $Bez . ": " . $Name : ", " . $Name;
      $Add[] = $this->getSFTexts($Rec, "f", ", ");
      // $Add[] = $this->getSFTexts($Rec, "u", ", ");  // GND
      $Add[] = $this->getSFTexts($Rec, "k", ", ");
      $Add[] = $this->getSFTexts($Rec, "l", ", ");
      $Add[] = $this->getSFTexts($Rec, "z", ", ");
      $Add   = array_filter($Add);

      // $this->CS->debug("ALXPROV", $Add);

      $Pros["item_".++$Item]    = array("text"   => implode("", $Add));
      $Pros["item_".++$Item]    = array("text"   => "",
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array("query" => ( $Norm )
                                                                                       ? "norm(" . $Norm . ")"
                                                                                       : "author(" . $this->clear($Name) . ")",
                                                                            "type"  => "lukida")
                                                         ),
                                        "more"   => array());
    }
    $Provenances = array();
    if ( !empty($Pros) )
    {
      $Provenances["bib_".$Seq] = array(
                                        "label"  => $this->CS->getText("bibline.".$Label),
                                        "code"   => $Label,
                                        "items"  => $Pros
                                       );
    }
    return $Provenances;
  }

  private function formatFinger($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // e                             (ISIL aus 5)
    // n.en erh- e-e. haau 3 1803A 4 (Halle/S UuLB)

    $FingType    = ( isset($this->CS->configs["bibliographic"]["fingerprint"]) && $this->CS->configs["bibliographic"]["fingerprint"] )
                   ? trim(strtolower($this->CS->configs["bibliographic"]["fingerprint"])) : "";
    if ( !$FingType ) return array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "026" => array("5","e")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $Fings = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["5"]["0"]) || !isset($Rec["e"]["0"]) ) continue;
      if ( !isset($Rec["5"]["0"]) && $FingType == "iln" )    continue;

      $ISILs = array_values(array_intersect($this->CS->configs["isils"], $Rec["5"]));

      // No matching ISIL
      if ( !count($ISILs) )
      {
        if ( $FingType == "iln" ) continue;
        $ISILs =  $Rec["5"];
      }

      $ISIL = $ISILs[0];

      $CRec = $this->getCDBIsil($ISIL);
      if ( !isset($CRec["name"]) || !isset($CRec["infos"]["shortname"]) )  continue;

      if ( $Item > 0 )
      {
        $Fings["item_".++$Item]  = array("display" => "newline");
      }

      $Add  = array();
      $Norm = $this->getNorm($Rec);

      $Text  = $CRec["infos"]["shortname"];
      if ( !$Text ) continue;

      $Fings["item_".++$Item]    = array("text"   => $Rec["e"]["0"] . " (" . $Text . ")");
    }

    $Finger = array();
    if ( !empty($Fings) )
    {
      $Finger["bib_".$Seq] = array(
                                   "label"  => $this->CS->getText("bibline.".$Label),
                                   "code"   => $Label,
                                   "items"  => $Fings
                                  );
    }
    return $Finger;
  }

  private function formatPublish($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Pubs = [];

    $Recs = $this->getCompleteArray(array($this->Prefix . "773" => array("a","b","d","g","h","i","q","t","w")));
    $Rec  = ( count($Recs) ) ? array_shift($Recs) : array();  // NUr ersten Satz keine Schleife
    $In   = "";
    if ( count($Rec) )
    {
      if ( isset($Rec["i"]["0"]) )
      {
        $Pubs["item_".++$Item]  = array("text"   => $Rec["i"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
      if ( isset($Rec["w"][0]) && in_array(substr($Rec["w"][0],0,8), array("(DE-601)","(DE-627)") ) )
      {
        // Mit Link
        if ( isset($Rec["t"][0]) && $Rec["t"][0] )
        {
          // Mit Titel
          $Pubs["item_".++$Item]  = array("text"   => $Rec["t"]["0"],
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                               "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                               "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"   => array()
                                         );
        }
        else
        {
          // Ohne Titel
          $Pubs["item_".++$Item]  = array("text"   => $this->getSubfieldFirst($this->Prefix . "245", "a"),
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                               "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                               "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"   => array()
                                         );
        }
      }
      elseif ( isset($Rec["t"][0]) && $Rec["t"][0] )
      {
        // Ohne Link
        $Pubs["item_".++$Item]  = array("text"   => $Rec["t"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }


      if ( isset($Rec["a"][0]) && $Rec["a"][0] )
      {
        $Pubs["item_".++$Item]  = array("text"   => $Rec["a"][0],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
      if ( isset($Rec["d"][0]) && $Rec["d"][0] )
      {
        $Pubs["item_".++$Item]  = array("text"   => $Rec["d"][0],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
      if ( isset($Rec["g"][0]) && $Rec["g"][0] )
      {
        $Pubs["item_".++$Item]  = array("text"   => $Rec["g"][0],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
      if ( isset($Rec["q"][0]) && $Rec["q"][0] )
      {
        $Pubs["item_".++$Item]  = array("text"   => $Rec["q"][0],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
      if ( isset($Rec["h"][0]) && $Rec["h"][0] )
      {
        $Pubs["item_".++$Item]  = array("text"   => $Rec["h"][0],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    $Recs    = $this->getCompleteArray(array($this->Prefix . "264" => array("6","a","b","c","e","I2")));
    $RecsOrg = $this->getCompleteArray(array($this->Prefix . "880" => array("6","a","b","c")));    
    if ( count($Recs) )
    {
      // Copyright-Angaben (I2 = 4) werden nicht als eigene Zeile ausgegeben,
      // sondern hinten an die Veroeffentlichung (I2 = 1) angehaengt - nach
      // "; " und in eckigen Klammern, mehrere durch ", " getrennt.
      // Beispiel (id 1938112083): "Hamburg Junius 2026; [© 2019]" (2026-08-11).
      $Copies = array();
      foreach ( $Recs as $Rec2 )
      {
        if ( !isset($Rec2["I2"][0]) || $Rec2["I2"][0] != "4" )  continue;
        foreach ( (array) (isset($Rec2["c"]) ? $Rec2["c"] : array()) as $One )
        {
          $One = trim((string) $One);
          if ( $One !== "" )  $Copies[] = "[" . $One . "]";
        }
      }

      foreach ( $Recs as $Field => $Rec )
      {
        if ( isset($Rec["I2"][0]) && $Rec["I2"][0] == "4" ) continue;

        // Kein Umbruch zwischen mehreren 264ern: alle Angaben zur
        // Veroeffentlichung stehen in EINER Zeile (2026-08-11).

        $Text = ( isset($Rec["a"]["0"]) ) ? $Rec["a"]["0"] : $this->getSubfieldFirst($this->Prefix . "245", "a");
        $Org  = $this->getOrgPubl(substr($Field,1,3), $Rec, $RecsOrg);
        
        $Pubs["item_".++$Item]  = array("text"   => $Text,
                                        "action" => array(),
                                        "more"   => array()
                                       );
        if ( isset($Rec["b"]["0"]) )
        {
          $Pubs["item_".++$Item]  = array("text"   => $Rec["b"]["0"],
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                               "query" => "publisher(" . $this->clear($Rec["b"]["0"]) . ")",
                                                                               "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"   => array()
                                         );
        }

        if ( isset($Rec["c"]["0"]) )
        {
          $Pubs["item_".++$Item]  = array("text"   => $Rec["c"]["0"],
                                          "action" => array(),
                                          "more"   => array()
                                         );
        }

        if ( isset($Org["a"]) )
        {
          $Pubs["item_".++$Item]  = array("text"   => $Org["a"],
                                          "action" => array(),
                                          "more"   => array()
                                         );
        }
        if ( isset($Org["b"]) )
        {
          $Pubs["item_".++$Item]  = array("text"   => $Org["b"],
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                               "query" => "publisher(" . $this->clear($Org["b"]) . ")",
                                                                               "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"   => array()
                                         );
        }
        if ( isset($Org["c"]) )
        {
          $Pubs["item_".++$Item]  = array("text"   => $Org["c"],
                                          "action" => array(),
                                          "more"   => array()
                                         );
        }

        if ( isset($Rec["e"]["0"]) )
        {
          $Pubs["item_".++$Item]  = array("text"   => $Rec["e"]["0"],
                                          "action" => array(),
                                          "more"   => array()
                                         );
        }
      }

      // Copyright-Angaben ganz am Ende DERSELBEN Zeile, nach "; " und je in
      // eckigen Klammern; mehrere durch ", " getrennt (2026-08-11).
      if ( count($Copies) )
      {
        // An den TEXT des letzten Eintrags anhaengen, nicht als eigener
        // Eintrag: die Oberflaeche setzt zwischen zwei Eintraegen ein
        // Leerzeichen, es stuende sonst "2026 ; [© 2019]".
        $LastKey = "";
        foreach ( $Pubs as $Key => $One )
        {
          if ( isset($One["text"]) )  $LastKey = $Key;
        }
        if ( $LastKey !== "" )
        {
          $Pubs[$LastKey]["text"] .= "; " . implode(", ", $Copies);
        }
        else
        {
          $Pubs["item_".++$Item]  = array("text"   => implode(", ", $Copies),
                                          "action" => array(),
                                          "more"   => array()
                                         );
        }
      }
    }

    $Publish = array();
    if ( !empty($Pubs) )
    {
      $Publish["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $Pubs
                                         );
    }
    return $Publish;
  }

  private function formatRemarks($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "772" => array("i","t","w"),
                                          $this->Prefix . "770" => array("i","t","w")));

    if ( empty($Recs) )  return array();

    $Item = 0;
    $Rems = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["i"]["0"]) && !isset($Rec["t"]["0"])) continue;

      $Text  = ( isset($Rec["i"]["0"]) ) ? $Rec["i"]["0"] : "";
      if ( isset($Rec["t"]["0"]) )
      {
        $Text .= ($Text) ? ": " . $Rec["t"]["0"] : $Rec["t"]["0"];
      }

      if ( isset($Rec["w"][0]) && in_array(substr($Rec["w"][0],0,8), array("(DE-601)","(DE-627)") ) )
      {
        $Rems["item_".++$Item]  = array("text"   => $Text,
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array(
                                                                            "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                            "type"  => "lukida"
                                                                           )
                                                           ),
                                       "more"   => array()
                                         );
      }
      else
      {
        $Rems["item_".++$Item]  = array("text"   => $Text,
                                        "action" => array(),
                                        "more"   => array()
                                        );
      }
    }

    $Remarks = array();
    if ( !empty($Rems) )
    {
      $Remarks["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $Rems
                                         );
    }
    return $Remarks;
  }

  private function formatLang($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "041" => array("a")));

    if ( empty($Recs) )  return array();

    $Item = 0;
    $Retn = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]) )  continue;

      if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Texts = array();
      foreach ( $Rec["a"] as $LC )
      {
        $Texts[] = $this->CS->getText("lang.".$LC);
      }
      $Texts = array_filter($Texts);

      if ( count($Texts) )
      {
        $Retn["item_".++$Item]  = array("text"   => implode(", ", $Texts),
                                        "action" => array(),
                                        "more"   => array());
      }
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq] = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Retn
                                   );
    }
    return $Returns;
  }

  private function formatLangOrg($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "041" => array("h")));

    if ( empty($Recs) )  return array();

    $Item = 0;
    $Retn = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["h"]) )  continue;

      if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Texts = array();
      foreach ( $Rec["h"] as $LC )
      {
        $Texts[] = $this->CS->getText("lang.".$LC);
      }
      $Texts = array_filter($Texts);

      if ( count($Texts) )
      {
        $Retn["item_".++$Item]  = array("text"   => implode(", ", $Texts),
                                        "action" => array(),
                                        "more"   => array());
      }
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq] = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Retn
                                   );
    }
    return $Returns;
  }

  private function formatDissert($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "502" => array("a","b","c","d")));

    if ( empty($Recs) )  return array();

    $Item = 0;
    $Diss = array();
    foreach ( $Recs as $Rec )
    {
      $Add   = array();
      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "b");
      $Add[] = $this->getSFTexts($Rec, "c");
      $Add[] = $this->getSFTexts($Rec, "d");
      $Add   = array_filter($Add);

      if ( count($Add) )
      {
        $Diss["item_".++$Item]  = array("text"   => implode(", ", $Add),
                                        "action" => array(),
                                        "more"   => array());
      }
    }

    $Disserts = array();
    if ( !empty($Diss) )
    {
      $Disserts["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $Diss
                                         );
    }
    return $Disserts;
  }

  private function formatSeeAlso($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "776" => array("I2", "a", "c", "d", "i", "n", "o", "t", "w", "z")));
    $Item = 0;
    $Retn = array();
     $More = array();

    foreach ( $Recs as $Rec )
    {
      // $this->CS->debug("ALX", $Rec);

       $Text = "";
       $Add  = array();
      if ( isset($Rec["I2"]["0"]) && $Rec["I2"]["0"] == "8" )
      {
        if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");

        // Muster: "i z n: t / a, d" (2026-08-13).
        // Der Doppelpunkt gehoert an das LETZTE der einleitenden Felder - ist n
        // nicht vorhanden, steht er hinter i. Vorher haftete er fest an n, und
        // die uebrigen Felder wurden mit Komma angehaengt; nach "n:" stand
        // dadurch ein ueberzaehliges ", " vor dem Titel.
        $Lead = array();
        foreach ( array("i", "z", "n") as $SF )
        {
          $One = trim($this->getSFTexts($Rec, $SF, $SF == "z" ? "ISBN " : ""));
          if ( $One !== "" )  $Lead[] = $One;
        }
        $Text = count($Lead) ? implode(" ", $Lead) . ":" : "";

        // Titel und Verfasserangabe mit " / " verbunden, danach das Impressum
        // mit ", " - wie in der Titelaufnahme selbst.
        $Title = trim($this->getSFTexts($Rec, "t"));
        $Auth  = trim($this->getSFTexts($Rec, "a"));
        if ( $Title !== "" && $Auth !== "" )       $Add[] = $Title . " / " . $Auth;
        elseif ( $Title !== "" )                   $Add[] = $Title;
        elseif ( $Auth !== "" )                    $Add[] = $Auth;
        $Add[] = trim($this->getSFTexts($Rec, "d"));
      }
      elseif ( isset($Rec["c"]["0"]) && trim($Rec["c"]["0"]) )
      {
        $More[] = array("z" => $this->getSFTexts($Rec, "z"),
                        "c" => $this->getSFTexts($Rec, "c"));
      }

      $Add   = array_filter($Add);

      if ( $Text || count($Add) )
      {
        if ( isset($Rec["w"]["0"]) && in_array(substr($Rec["w"]["0"],0,8), array("(DE-601)","(DE-627)") ) )
        {
          $Retn["item_".++$Item]  = array("text"   => trim($Text . " " . implode(", ", $Add)),
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array(
                                                                              "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                              "type"  => "lukida"
                                                                             )
                                                           ),
                                          "more"   => array()
                                         );
        }
        elseif ( isset($Rec["o"]["0"]) && substr($Rec["o"]["0"],0,4) == "urn:" )
        {
          $Retn["item_".++$Item]  = array("text"   => trim($Text . " " . implode(", ", $Add)),
                                          "action" => array(
                                                            "type"   => "link",
                                                            "link"   => array(
                                                                              "url"    => "https://nbn-resolving.org/" . $Rec["o"]["0"],
                                                                              "target" => "_blank"
                                                                             )
                                                           ),
                                          "more"   => array()
                                         );
        }
        elseif ( isset( $Rec["z"]["0"]) )
        {
          $Retn["item_".++$Item]  = array("text"   =>trim($Text . " " . implode(", ", $Add)),
                                          "action" => array(
                                                            "type"   => "search",
                                                            "search" => array("query" => "isn(" . $Rec["z"]["0"] . ")",
                                                                              "type"  => "lukida")
                                                           ),
                                          "more"   => array());
        }
      }
    }

    // $this->CS->debug("ALX", $More);

    if ( count($More) )
    {
      if ( $Item ) $Retn["item_".++$Item]     = array("display" => "newline");
      $Retn["item_".++$Item]     = array("text" => $this->CS->getText("bibline.othereditions") . ": ");

      foreach ($More as $M )
      {
        $Retn["item_".++$Item]  = array("text"   => "ISBN " . trim($M["z"] . " " . $M["c"]),
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array("query" => "isn(" . $M["z"] . ")",
                                                                            "type"  => "lukida")
                                                         ),
                                        "more"   => array());
      }
    }

    // M535 - Vorlage der elektronischen Reproduktion: Einrichtung (a) und
    // Signatur (3), getrennt durch Gedankenstrich; die Signatur traegt die
    // Suche, weil man darueber zum gedruckten Stueck kommt.
    //
    // Hinweis fuer den Fall, dass die Zeile doppelt zu erscheinen scheint:
    // Bei Digitalisaten steht dieselbe Angabe haeufig ZUSAETZLICH in 776 $i/$n
    // ("Elektronische Reproduktion von" + "... -- Ic 4095 (2)"). Das ist eine
    // Doppelung in den DATEN, nicht im Code (2026-08-13 geprueft an 1964949912).
    $Recs = $this->getCompleteArray(array($this->Prefix . "535" => array("3","a")));
    foreach ( $Recs as $Rec )
    {
      $Inst = trim($this->getSFTexts($Rec, "a"));
      $Sign = trim($this->getSFTexts($Rec, "3"));
      if ( !$Inst && !$Sign )  continue;

      if ( $Item ) $Retn["item_".++$Item]  = array("display" => "newline");
      $Retn["item_".++$Item] = array("text"   => $this->CS->getText("bibline.reproductionof") . ": " . $Inst
                                                 . ( $Sign ? " – " : "" ),
                                     "action" => array(),
                                     "more"   => array());

      if ( $Sign )
      {
        $Retn["item_".++$Item] = array("text"   => $Sign,
                                       "action" => array(
                                                         "type"   => "search",
                                                         "search" => array("query" => "signature(" . $this->clear($Sign) . ")",
                                                                           "type"  => "lukida")
                                                        ),
                                       "more"   => array());
      }
    }

    // M533 - Reproduktionshinweis. Gemeint ist NUR der Satz mit Unterfeld 7;
    // daneben steht regelmaessig ein zweiter ohne, der die Reproduktion bloss
    // grob benennt ("Reproduktion, Halle, Saale, ULB") und hier nichts beitraegt.
    // Aufbau: a, b, c, d. – e. – f
    $Recs = $this->getCompleteArray(array($this->Prefix . "533" => array("7","a","b","c","d","e","f")));
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["7"]["0"]) )  continue;

      $Head = array();
      foreach ( array("a","b","c","d") as $SF )
      {
        $One = trim($this->getSFTexts($Rec, $SF));
        if ( $One !== "" )  $Head[] = $One;
      }
      $Parts = array();
      if ( count($Head) )  $Parts[] = implode(", ", $Head);
      foreach ( array("e","f") as $SF )
      {
        $One = trim($this->getSFTexts($Rec, $SF));
        if ( $One !== "" )  $Parts[] = $One;
      }
      if ( !count($Parts) )  continue;

      if ( $Item ) $Retn["item_".++$Item]  = array("display" => "newline");
      $Retn["item_".++$Item] = array("text"   => $this->CS->getText("bibline.reproductionnote") . ": "
                                                 . implode(". – ", $Parts),
                                     "action" => array(),
                                     "more"   => array());
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatSeries($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "490" => array("a", "v")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $Sers = array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["a"]["0"]) )
      {
        $Sers["item_".++$Item]  = array("text"   => $Rec["a"]["0"],
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array(
                                                                             "query" => "series(" . $this->clear($Rec["a"]["0"]) . ")",
                                                                             "type"  => "lukida"
                                                                           )
                                                         ),
                                        "more"   => array()
                                       );
      }
      if ( isset($Rec["v"]["0"]) )
      {
        $Sers["item_".++$Item]  = array("text"   => $Rec["v"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    $Series = array();
    if ( !empty($Sers) )
    {
      $Series["bib_".$Seq] = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Sers
                                   );
    }
    return $Series;
  }

  private function formatSibling($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "787" => array("d","h","i","n","t","w","z")));

    if ( empty($Recs) )  return array();

    $Item = 0;
    $Retn = array();
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["i"]["0"]) && !isset($Rec["t"]["0"])) continue;

      $Add   = array();
      if ( isset($Rec["n"]["0"]) )
      {
        $Add[] = $this->getSFTexts($Rec, "n");
      }
      else
      {
        $Text  = $this->getSFTexts($Rec, "i");
        $Add[] = $this->getSFTexts($Rec, "t");
        $Add[] = $this->getSFTexts($Rec, "d");
        $Add[] = $this->getSFTexts($Rec, "h");
        $Add   = array_filter($Add);
      }
      $Text = isset($Text) && trim($Text) ? $Text . ": " : "";

      if ( isset($Rec["w"][0]) && in_array(substr($Rec["w"][0],0,8), array("(DE-601)","(DE-627)") ) )
      {
        $Retn["item_".++$Item]  = array("text"   => trim($Text . implode(", ", $Add)),
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array(
                                                                            "query" => "id(" . trim(substr($Rec["w"]["0"],8)) . ")",
                                                                            "type"  => "lukida"
                                                                           )
                                                         ),
                                        "more"   => array()
                                       );
      }
      elseif ( isset( $Rec["z"]["0"]) )
      {
        $Retn["item_".++$Item]  = array("text"   =>trim($Text . implode(", ", $Add)),
                                        "action" => array(
                                                          "type"   => "search",
                                                          "search" => array("query" => "isn(" . $Rec["z"]["0"] . ")",
                                                                            "type"  => "lukida")
                                                         ),
                                        "more"   => array());
      }
      else
      {
        $Retn["item_".++$Item]  = array("text"   => trim($Text . implode(", ", $Add)),
                                        "action" => array()
                                        );
      }
      $Retn["item_".++$Item]  = array("display" => "newline");
    }

    $Siblings = array();
    if ( !empty($Retn) )
    {
      $Siblings["bib_".$Seq] = array(
                                         "label"  => $this->CS->getText("bibline.".$Label),
                                         "code"   => $Label,
                                         "items"  => $Retn
                                         );
    }
    return $Siblings;
  }

  private function formatSimple($Seq, $Label, $Filter)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->prettyRecs($Filter);
    if ( empty($Recs) )  return array();

    return array(("bib_".$Seq) => array(
                                "label"  => $this->CS->getText("bibline.".$Label),
                                "code"   => $Label,
                                "items"  => $Recs
                               )
                );
  }

  private function formatSource($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Achtung 2 Collections
    // $this->Collection      Index-Felder als Array von SOLR
    // $this->CS->Collection  Collektions-Beschreibungstabelle von LCDB
    $Recs = $this->Collection;
    if ( in_array("GVK", $Recs) && in_array("KXP", $Recs) ) $Recs = array_diff($Recs, array("GVK"));

    $Retn = array();
    $Item = 0;
    foreach ( $Recs as $Rec )
    {
      $CRec = isset($this->CS->collections[strtoupper($Rec)]) ? $this->CS->collections[strtoupper($Rec)] : array();

      if ( !isset($CRec["name"]) || !isset($CRec["link"]) )  continue;

      if ( in_array($Rec, array("KXP", "OLC", "SWB")) )
      {
        $Tmp = $this->CS->getCollAndIDFromPPN($this->ID);
        if ( isset($Tmp["id"]) )
        {
          $CRec["link"] .= "PPNSET?PPN=" . $Tmp["id"];
        }
      }

      $Retn["item_".++$Item]   = array("text"   => $CRec["name"],
                                       "action" => array(
                                                         "type"    => "link",
                                                         "link"    => array(
                                                                            "url"    => $CRec["link"],
                                                                            "target" => "_blank"
                                                                           )
                                                       ),
                                       "more"   => array()
                                      );
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq] = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Retn
                                   );
    }
    return $Returns;
  }

  // Doppelte Schlagwortketten entfernen.
  //
  // $Retn ist EINE flache, geordnete Liste aus Begriffs-Eintraegen und Trennern
  // ("display" => "newline"); eine Kette ist alles zwischen zwei Trennern.
  // getUniqueMultArray() laesst sich deshalb nicht unmittelbar darauf anwenden:
  // es verglich die EINZELNEN Eintraege, entfernte damit in mehreren Ketten
  // wiederkehrende Begriffe (z.B. "Deutschland") und - weil auch alle Trenner
  // untereinander gleich sind - bis auf einen saemtliche Zeilenumbrueche. Aus
  // drei Ketten wurden so zwei verstuemmelte (2026-08-11 gemeldet).
  //
  // Verglichen wird die SICHTBARE Wertefolge einer Kette in ihrer Reihenfolge:
  //   A B C / A D / A D C / A B C / C A B
  // ergibt A B C / A D / A D C / C A B - nur die Wiederholung faellt weg,
  // "C A B" ist eine andere Kette als "A B C".
  private function uniqueSubjectChains($Retn)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // 1. In Ketten zerlegen: die vollstaendigen Eintraege und - getrennt davon -
    //    nur die sichtbaren Werte, die den Vergleich tragen.
    $Chains = array();
    $Texts  = array();
    $Cur    = array();
    $CurTxt = array();

    foreach ( $Retn as $One )
    {
      if ( isset($One["display"]) && $One["display"] == "newline" )
      {
        if ( count($Cur) )  { $Chains[] = $Cur; $Texts[] = $CurTxt; }
        $Cur    = array();
        $CurTxt = array();
        continue;
      }
      $Cur[]    = $One;
      $CurTxt[] = isset($One["text"]) ? $One["text"] : "";
    }
    if ( count($Cur) )  { $Chains[] = $Cur; $Texts[] = $CurTxt; }

    // 2. Doppelte Ketten entfernen. array_unique() ueber die serialisierten
    //    Wertefolgen behaelt jeweils die ERSTE und liefert deren Positionen mit.
    $Keep = array_keys($this->CS->getUniqueMultArray($Texts));

    // 3. Wieder zu einer flachen Liste mit fortlaufenden Schluesseln zusammensetzen.
    $Out  = array();
    $Item = 0;
    foreach ( $Keep as $K )
    {
      if ( count($Out) )  $Out["item_".++$Item] = array("display" => "newline");
      foreach ( $Chains[$K] as $One )  $Out["item_".++$Item] = $One;
    }
    return $Out;
  }

  private function formatSubject($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "689" => array("I2"," 0","a","b","c","d","D","f","g","m","n","o","p","r","s","t")));
    $Item = 0;
    $Retn = array();

    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;

      if ( $Item > 0 && !isset($Rec["I2"]) ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Add   = array();
      $Norm  = $this->getNorm($Rec);
      $NormGND = $this->getGNDNorm("place", $Rec);
      $Add[] = $this->getSFTexts($Rec, "a");
      $Add[] = $this->getSFTexts($Rec, "p", ". ");
      $Add[] = $this->getSFTexts($Rec, "n", ", ");
      $Add[] = $this->getSFTexts($Rec, "d", " (", ")");
      $Add[] = $this->getSFTexts($Rec, "b", " " );
      $Add[] = $this->getSFTexts($Rec, "t", ": ");
      $Add[] = $this->getSFTexts($Rec, "m", ", ");
      $Add[] = $this->getSFTexts($Rec, "o", ", ");
      $Add[] = $this->getSFTexts($Rec, "r", ", ");
      $Add[] = $this->getSFTexts($Rec, "s", ", ");
      $Add[] = $this->getSFTexts($Rec, "c", ", ");

      $Bra   = array();
      $Bra[] = $this->getSFTexts($Rec, "g");
      $Bra[] = $this->getSFTexts($Rec, "f");
      $Bra   = array_filter($Bra);
      if ( count($Bra) )  $Add[] = " (" . implode(", ", $Bra) . ") ";
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]  = array("text"   => implode("", $Add),
                                      "action" => array(
                                                        "type"   => "search",
                                                        "search" => array(
                                                                           "query" => ( $Norm ) ? "norm(" . $Norm . ")" : "subject(" . $this->clear($Rec["a"]["0"]) . ")",
                                                                            "type"  => "lukida"
                                                                          )
                                                       ),
                                      "more"   => $NormGND
                                     );
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "982" => array(" 0","a")));
    foreach ( $Recs as $Rec )
    {
      if ( !isset($Rec["a"]["0"]) ) continue;

      if ( $Item > 0 ) $Retn["item_".++$Item]     = array("display" => "newline");

      $Add   = array();
      $Norm  = $this->getNorm($Rec);
      $Add[] = $this->getSFTexts($Rec, "a");
      $Add   = array_filter($Add);

      $Retn["item_".++$Item]  = array("text"   => implode("", $Add),
                                      "action" => array(
                                                        "type"   => "search",
                                                        "search" => array(
                                                                           "query" => ( $Norm ) ? "norm(" . $Norm . ")" : "subject(" . $this->clear($Rec["a"]["0"]) . ")",
                                                                            "type"  => "lukida"
                                                                          )
                                                       ),
                                       "more"   => array()
                                      );
    }

    // Doppelte Schlagwortketten entfernen - siehe uniqueSubjectChains().
    $Retn = $this->uniqueSubjectChains($Retn);

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatTitle($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Retn = array();

    $Recs  = $this->getCompleteArray(array($this->Prefix . "245" => array("a", "b", "c")));
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["a"]) && is_array($Rec["a"]) && count($Rec["a"]) )
      {
        foreach ($Rec["a"] as $A)
        {
          if ( $A )
          {
            $Retn["item_".++$Item]    = array("text"   => ($Item>1) ? ": " . $A : $A,
                                               "action" => array(),
                                               "more"   => array()
                                              );
          }
        }
      }
      if ( isset($Rec["b"]) && is_array($Rec["b"]) && count($Rec["b"]) )
      {
        foreach ($Rec["b"] as $B)
        {
          if ( $B )
          {
            $Retn["item_".++$Item]    = array("text"   => ($Item>1) ? ": " . $B : $B,
                                               "action" => array(),
                                               "more"   => array()
                                              );
          }
        }
      }
      if ( isset($Rec["c"]) && is_array($Rec["c"]) && count($Rec["c"]) )
      {
        foreach ($Rec["c"] as $C)
        {
          if ( $C )
          {
            $Retn["item_".++$Item]    = array("text"   => ($Item>1) ? "/ " . $C : $C,
                                               "action" => array(),
                                               "more"   => array()
                                              );
          }
        }
      }
    }

    $Recs  = $this->getCompleteArray(array($this->Prefix . "249" => array("a")));
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["a"]) && is_array($Rec["a"]) && count($Rec["a"]) )
      {
        foreach ($Rec["a"] as $A)
        {
          if ( $A )
          {
            $Retn["item_".++$Item]    = array("text"   => ($Item>1) ? "; " . $A : $A,
                                               "action" => array(),
                                               "more"   => array()
                                              );
          }
        }
      }
    }

    $Returns = array();
    if ( !empty($Retn) )
    {
      $Returns["bib_".$Seq]     = array(
                                       "label"  => $this->CS->getText("bibline.".$Label),
                                       "code"   => $Label,
                                       "items"  => $Retn
                                      );
    }
    return $Returns;
  }

  private function formatVD16($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "024" => array("2", "a",)));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $VD   = array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["2"]["0"]) && $Rec["2"]["0"] == "vd16" && isset($Rec["a"]["0"]) )
      {
        $VD["item_".++$Item]    = array("text"   => $Rec["a"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    $VDs = array();
    if ( !empty($VD) )
    {
      $VDs["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $VD
                                   );
    }
    return $VDs;
  }

  private function formatVD17($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "024" => array("2", "a")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $VD   = array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["2"]["0"]) && $Rec["2"]["0"] == "vd17" && isset($Rec["a"]["0"]) )
      {
        $VD["item_".++$Item]    = array("text"   => $Rec["a"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    $VDs = array();
    if ( !empty($VD) )
    {
      $VDs["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $VD
                                   );
    }
    return $VDs;
  }

  private function formatVD18($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "024" => array("2", "a")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $VD   = array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["2"]["0"]) && $Rec["2"]["0"] == "vd18" && isset($Rec["a"]["0"]) )
      {
        $VD["item_".++$Item]    = array("text"   => $Rec["a"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
      }
    }

    $VDs = array();
    if ( !empty($VD) )
    {
      $VDs["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $VD
                                   );
    }
    return $VDs;
  }

  private function formatDOI($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "024" => array("2", "a")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $DOI  = array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["2"]["0"]) && $Rec["2"]["0"] == "doi" && isset($Rec["a"]["0"]) )
      {
        $DOI["item_".++$Item]    = array("text"   => $Rec["a"]["0"],
                                         "action" => array(
                                                           "type"   => "link",
                                                           "link"    => array(
                                                                              "url"    => "https://www.doi.org/" . $Rec["a"]["0"],
                                                                              "target" => "_blank"
                                                                             )
                                                          ),
                                         "more"   => array()
                                       );


      }
    }

    $DOIs = array();
    if ( !empty($DOI) )
    {
      $DOIs["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $DOI
                                   );
    }
    return $DOIs;
  }

  private function formatCover($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "563" => array("a", "u")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $Retn = array();
    foreach ( $Recs as $Rec )
    {
      $Add   = array();
      $Add[] = $this->getSFTexts($Rec, "a");

      $Link  = $this->getSFTexts($Rec, "u");

      $Key["item_".++$Item]  = array("text"   => implode(", ", $Add),
                                     "action" => array(
                                                       "type"   => "link",
                                                       "link"    => $Link ? array("url"    => $Link,
                                                                                  "target" => "_blank")
                                                                          : array()
                                                      ),
                                      "more"   => array()
                                     );
      $Key["item_".++$Item]  = array("display" => "newline");
    }

    $Keys = array();
    if ( !empty($Key) )
    {
      $Keys["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Key
                                   );
    }
    return $Keys;
  }

  private function formatKeys($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = $this->getCompleteArray(array($this->Prefix . "024" => array("2", "a", "q")));
    if ( empty($Recs) )  return array();

    $Item = 0;
    $Key  = array();
    foreach ( $Recs as $Rec )
    {
      $Tmp   = strtolower($this->getSFTexts($Rec, "q"));
      if ( substr($Tmp, 0, 5) == "nicht" )  continue;

      $Add   = array();
      $Add[] = $this->getSFTexts($Rec, "q", "", ": ");
      $Add[] = $this->getSFTexts($Rec, "2", "", ": ");
      $Add[] = $this->getSFTexts($Rec, "a");

      $Key["item_".++$Item]  = array("text"   => implode("", $Add),
                                     "action" => array(),
                                     "more"   => array()
                                    );
      $Key["item_".++$Item]  = array("display" => "newline");
    }

    $Keys = array();
    if ( !empty($Key) )
    {
      $Keys["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Key
                                   );
    }
    return $Keys;
  }

  private function formatNotes($Seq, $Label)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Item = 0;
    $Note = array();

    $Recs = $this->getCompleteArray(array($this->Prefix . "500" => array("a")));
    // if ( empty($Recs) )  return array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["a"]["0"]) )
      {
        $Note["item_".++$Item]  = array("text"   => $Rec["a"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("display" => "newline");
      }
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "255" => array("a","b","c")));
    // if ( empty($Recs) )  return array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["a"]["0"]) )
      {
        $Note["item_".++$Item]  = array("code"   => "bibline.scale",
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("text"   => ": " . $Rec["a"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item] = array("display" => "newline");
      }
      if ( isset($Rec["b"]["0"]) )
      {
        $Note["item_".++$Item]  = array("code"   => "bibline.projection",
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("text"   => ": " . $Rec["b"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item] = array("display" => "newline");
      }
      if ( isset($Rec["c"]["0"]) )
      {
        $Note["item_".++$Item]  = array("code"   => "bibline.coordinates",
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("text"   => ": " . $Rec["c"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item] = array("display" => "newline");
      }
    }

    $Recs = $this->getCompleteArray(array($this->Prefix . "383" => array("c")));
    // if ( empty($Recs) )  return array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["c"]["0"]) )
      {
        $Note["item_".++$Item]  = array("code"   => "bibline.marking",
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("text"   => $Rec["c"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("display" => "newline");
      }
    }


    $Recs = $this->getCompleteArray(array($this->Prefix . "384" => array("a")));
    // if ( empty($Recs) )  return array();
    foreach ( $Recs as $Rec )
    {
      if ( isset($Rec["a"]["0"]) )
      {
        $Note["item_".++$Item]  = array("code"   => "bibline.tone",
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("text"   => $Rec["a"]["0"],
                                        "action" => array(),
                                        "more"   => array()
                                       );
        $Note["item_".++$Item]  = array("display" => "newline");
      }
    }

    $Notes = array();
    if ( !empty($Note) )
    {
      $Notes["bib_".$Seq]     = array(
                                    "label"  => $this->CS->getText("bibline.".$Label),
                                    "code"   => $Label,
                                    "items"  => $Note
                                   );
    }
    return $Notes;
  }

  private function getArray($Filter)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Output = array();
    foreach ( $Filter as $Field => $Subfields )
    {
      if ( array_key_exists($Field, $this->Convert) )
      {
        foreach ($this->Convert[$Field] as $Record)
        {
          $Tmp = array();
          foreach ( $Record as $Key => $Values )
          {
            if ( in_array((string)$Key, $Subfields) )
            {
              foreach ($Values as $Val)
              {
                $Tmp[] = $Val;
              }
            }
          }
          $Output[] = implode(" ", $Tmp);
        }
      }
    }
    return $Output;
  }

  private function getArticleLinks()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( $this->Format != "Article")  return false;

    $Links = array();
    if ( isset($this->Convert[$this->Prefix . "981"]) )
    {
      foreach ( $this->Convert[$this->Prefix . "981"] as $Record )
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( $Key == "r" )
          {
            foreach ( $Values as $Value )
            {
              if ( !in_array($Value, $Links) )
              {
                $Links[]  = $Value;
              }
            }
          }
        }
      }
    }

    if ( isset($this->Convert[$this->Prefix . "856"]) )
    {
      foreach ( $this->Convert[$this->Prefix . "856"] as $Record )
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( $Key == "u" )
          {
            foreach ( $Values as $Value )
            {
              if ( !in_array($Value, $Links) )
              {
                $Links[]  = $Value;
              }
            }
          }
        }
      }
    }
    return count($Links) ? true : false;
  }

  private function getArtCode()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    return isset($this->Convert[$this->Prefix . "773"][0]["7"][0]) ? $this->Convert[$this->Prefix . "773"][0]["7"][0] : "";
  }

  private function getOwnOrg()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    return isset($this->Convert[$this->Prefix . "535"][0]["a"][0]) ? $this->Convert[$this->Prefix . "535"][0]["a"][0] : "";
  }

  private function getBiblio()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Set OriginalYear
    $this->OriginalYear = ( isset($this->Convert[$this->Prefix . "008"]) && substr($this->Convert[$this->Prefix . "008"],6,1) == "r" )
                          ? substr($this->Convert[$this->Prefix . "008"],11,4)
                          : "";

    $Fields  = array();          // Sequenz  Label               Text-Records                        Link
    $Fields += $this->formatTitle  (   "01", "title");
    $Fields += $this->formatSimple (   "02", "uniformtitle",     array("240" => array("a","f","g","k","m","n","o","p","r","s")));
    $Fields += $this->formatPart   (   "03", "part");
    $Fields += $this->formatPeople (   "04", "author",
                                       "05", "associates");
    $Fields += $this->formatFormat (   "06", "format");
    $Fields += $this->formatCorp   (   "07", "corporation");
    $Fields += $this->formatSeries (   "08", "series");
    $Fields += $this->formatIn     (   "09", "in");
    $Fields += $this->formatEdition(   "10", "edition");
    $Fields += $this->formatSimple (   "11", "recording",        array("518" => array("a")));
    $Fields += $this->formatPublish(   "12", "publisher");
    $Fields += $this->formatSimple (   "13", "originalyear",     array("534" => array("c")));
    $Fields += $this->formatLang   (   "14", "language");
    $Fields += $this->formatLangOrg(   "15", "languageoriginal");
    $Fields += $this->formatSize   (   "16", "size");
    $Fields += $this->formatNotes  (   "17", "notes");
    $Fields += $this->formatInclude(   "18", "includes");
    $Fields += $this->formatSimple (   "19", "frequency",        array("515" => array("a")));
    $Fields += $this->formatOtherEs(   "20", "othereditions");
    $Fields += $this->formatRemarks(   "21", "remarks");
    $Fields += $this->formatSeeAlso(   "22", "seealso");
    $Fields += $this->formatSimple (   "23", "languagenotes",    array("546" => array("a")));
    $Fields += $this->formatDissert(   "24", "dissertation");
    $Fields += $this->formatCits   (   "25", "citation");
    $Fields += $this->formatSimple (   "26", "computerfile",     array("256" => array("a")));
    $Fields += $this->formatSimple (   "27", "systemdetails",    array("538" => array("a")));
    $Fields += $this->formatSimple (   "28", "issn",             array("022" => array("a"),
                                                                       "773" => array("x")));
    $Fields += $this->formatISBN   (   "29", "isbn");
    $Fields += $this->formatSimple (   "30", "summary",          array("520" => array("a")));
    $Fields += $this->formatAddInfo(   "31", "additionalinfo");
    $Fields += $this->formatProven (   "32", "provenance");
    $Fields += $this->formatSubject(   "33", "subject");
    $Fields += $this->formatCover  (   "34", "cover");
    $Fields += $this->formatGenre  (   "35", "genre");
    $Fields += $this->formatClass  (   "36", "classification");
    $Fields += $this->formatSource (   "37", "source");
    $Fields += $this->formatSibling(   "38", "sibling");
    $Fields += $this->formatLicense(   "39", "license");
    $Fields += $this->formatSimple (   "40", "contents",          array("505" => array("a", "t")));
    $Fields += $this->formatSimple (   "41", "worktitle",         array("130" => array("a")));
    $Fields += $this->formatAddTitl(   "42", "addtitle");
    $Fields += $this->formatClassL (   "43", "classlocal");
    $Fields += $this->formatVD16   (   "44", "vd16");
    $Fields += $this->formatVD17   (   "45", "vd17");
    $Fields += $this->formatVD18   (   "46", "vd18");
    $Fields += $this->formatDOI    (   "47", "doi");
    $Fields += $this->formatKeys   (   "48", "keys");
    $Fields += $this->formatFinger (   "49", "fingerprint");
    $Fields += $this->formatSimple (   "50", "appearancehistory", array("362" => array("a")));
    $Fields += $this->formatSimple (   "51", "orderid",           array("028" => array("a")));
    $Fields += $this->formatCollec (   "52", "collection");

    // Technical Items (not for display)
    $Fields += $this->internArtl   (  "100", "article");
    $Fields += $this->internPubl   (  "101", "publisher");
    $Fields += $this->internDetl   (  "102", "details");
    $Fields += $this->internLink   (  "103", "links");

    // Add original characters
    $Fields  = $this->AddOriginalCharacters($Fields);

    // Reihenfolge UND Darstellungsart der Bibline-Zeilen kommen ab sofort aus
    // der API, nicht mehr aus dem Client (im alten User-Modul lagen sie in
    // stores/appl.js als "biblines"-Listen). Damit steuert die API, was in der
    // kleinen Kachel ("title") und was in der Detailansicht ("detail")
    // erscheint, in welcher Folge und in welcher Form:
    //   norm  - Beschriftung + Werte nebeneinander (Standard)
    //   block - mehrspaltig
    //   long  - langer Text, im Client aufklappbar
    //   class - Klassifikation, aufklappbar mit Ober-/Unterbegriffen
    // Gefiltert auf tatsaechlich vorhandene Felder, damit der Client nichts
    // mehr pruefen muss. Rein additiv - aeltere Clients ignorieren "groups".
    $Groups = array("title" => array(), "detail" => array());
    foreach ( $this->biblineLayout() as $Section => $Lines )
    {
      foreach ( $Lines as $ID => $Type )
      {
        if ( isset($Fields[$ID]) )  $Groups[$Section][] = array("id" => $ID, "type" => $Type);
      }
    }

    // Beschriftung lueckenlos nachziehen (20.08.2026): Die meisten Zeilen
    // bekommen ihr "label" schon beim Aufbau, einige (edition, isbn,
    // publisher, details, links) trugen bisher nur den "code" - dann musste
    // der Client daraus selbst "bibline.<code>" bilden. Das faellt weg: hier
    // wird die Beschriftung fuer JEDE Zeile gesetzt, die noch keine hat.
    foreach ( $Fields as $ID => $Field )
    {
      if ( !is_array($Field) || ( isset($Field["label"]) && $Field["label"] !== "" ) )  continue;
      $Code = isset($Field["code"]) ? $Field["code"] : $ID;
      $Lbl  = $this->CS->getText("bibline." . $Code);
      if ( $Lbl === "" )  $Lbl = $this->CS->getText("bibline." . $ID);
      if ( $Lbl !== "" )  $Fields[$ID]["label"] = $Lbl;
    }

    return array("label"      => $this->CS->getText("bibgroup.bibliographicdata"),
                 "code"       => "bibliographicdata",
                 "before"     => array(),
                 "groups"     => $Groups,
                 "groupitems" => $Fields,
                 "after"      => array());
  }

  // Reihenfolge und Darstellungsart der Bibline-Zeilen - 1:1 uebernommen aus
  // dem alten User-Modul (stores/appl.js, "biblines"), damit die Anzeige
  // unveraendert bleibt. Der Abschnitt "unused" von dort entfaellt: was hier
  // nicht steht, wird nicht ausgeliefert.
  private function biblineLayout()
  {
    return array(
      "title" => array(
        "bib_01" => "norm",
        "bib_03" => "norm",
        "bib_41" => "norm",
        "bib_42" => "norm",
        "bib_04" => "block",
        "bib_05" => "block",
        "bib_07" => "norm",
        "bib_12" => "norm",
        "bib_10" => "norm",
      ),
      "detail" => array(
        "bib_02" => "norm",
        "bib_35" => "norm",
        "bib_16" => "norm",
        "bib_40" => "long",
        "bib_18" => "norm",
        "bib_28" => "norm",
        "bib_29" => "norm",
        "bib_14" => "norm",
        "bib_15" => "norm",
        "bib_13" => "norm",
        "bib_50" => "norm",
        "bib_17" => "long",
        "bib_11" => "norm",
        "bib_24" => "norm",
        "bib_25" => "long",
        "bib_26" => "norm",
        "bib_27" => "long",
        "bib_34" => "norm",
        "bib_32" => "norm",
        "bib_49" => "norm",
        "bib_30" => "long",
        "bib_19" => "norm",
        "bib_23" => "norm",
        "bib_39" => "norm",
        "bib_08" => "norm",
        "bib_20" => "norm",
        "bib_31" => "norm",
        "bib_22" => "norm",
        "bib_21" => "norm",
        "bib_33" => "norm",
        "bib_36" => "class",
        "bib_52" => "norm",
        "bib_43" => "norm",
        "bib_44" => "norm",
        "bib_45" => "norm",
        "bib_46" => "norm",
        "bib_37" => "norm",
        "bib_38" => "norm",
        "bib_48" => "norm",
        "bib_47" => "norm",
        "bib_51" => "norm",
      ),
    );
  }

  private function getCDBIsil($Shortcut)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( $Shortcut == "" ) return array();

    $Search = array("search" => array("type" => "code",
                                      "code" => $Shortcut),
                    "start"  => 0,
                    "limit"  => 1);
    $Pack = $this->CS->getIsilsDB($Search);
    $CRec = ( isset($Pack["0"]) ) ? $Pack["0"] : array();
    $Cont = array_intersect_key($CRec, array_flip(array("name", "infos")));
    return $Cont;
  }

  private function getCompleteArray($Filter)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Output = array();
    $Count  = 0;
    foreach ( $Filter as $Field => $Subfields )
    {
      if ( array_key_exists($Field, $this->Convert) )
      {
        foreach ($this->Convert[$Field] as $Record)
        {
          $Tmp = array();
          foreach ( $Record as $Key => $Value )
          {
            if ( in_array((string)$Key, $Subfields) )
            {
              $Tmp[(string)$Key] = $Value;
            }
          }
          $Output[$Field."_".$Count] = $Tmp;
          $Count++;
        }
      }
    }
    return $Output;
  }

  private function getFlatArray($Filter)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Take only first sub-subrecord and therefore create flat array
    $Output = array();
    $Count  = 0;
    foreach ( $Filter as $Field => $Subfields )
    {
      if ( array_key_exists($Field, $this->Convert) )
      {
        // $this->CS->debug("AOX23",$this->Convert[$Field]);
        foreach ($this->Convert[$Field] as $Record)
        {
          foreach ($Subfields as $Subfield)
          {
            if ( isset($Record[$Subfield]) )
            {
              if ( is_array($Record[$Subfield]) )
              {
                $Output[$Field."_".$Count][$Subfield] = implode(", ", $Record[$Subfield]);
              }
              else
              {
                $Output[$Field."_".$Count][$Subfield] = $Record[$Subfield];
              }
            }
          }
          $Count++;
        }
      }
    }
    return $Output;
  }

  private function getID($Field, $Subfield, $Prefix)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Output = array();
    if ( array_key_exists($Field, $this->Convert) )
    {
      foreach ($this->Convert[$Field] as $Record)
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( (string)$Key == $Subfield )
          {
            foreach ($Values as $Val)
            {
              if (strncasecmp($Prefix, $Val, strlen($Prefix)) === 0)  $Output[] = substr($Val, strlen($Prefix));
            }
          }
        }
      }
    }
    return $Output;
  }

  // Fehlenden zweiten Schraegstrich im Schema ergaenzen (01.09.2026)
  //
  // In den Katalogdaten stehen vereinzelt Adressen mit nur EINEM Schraegstrich:
  //
  //   https:/doi.org/10.1515/abitech-2026-0030
  //
  // Browser richten das beim Anklicken still, parse_url() dagegen nicht - dort
  // ist "doi.org" dann kein Rechnername mehr, sondern Teil des Pfades, und
  // getHost() liefert nichts zurueck. In der alten Oberflaeche stand daneben
  // deshalb eine LEERE Klammer: "Resolving-System, kostenfrei ()".
  //
  // Repariert wird ausschliesslich dieser eine Fall - "http:/" oder "https:/"
  // ohne folgenden zweiten Schraegstrich. Alles andere bleibt unangetastet:
  // relative Adressen, mailto:, Schemata mit einem Schraegstrich, an denen
  // nichts falsch ist.
  private function repairScheme($Url)
  {
    return preg_replace('~^(https?):/(?!/)~i', '$1://', (string) $Url);
  }

  private function getItems()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Items    = array();
    $AddItems = array();

    $HWithO   = ( isset($this->CS->configs["item"]["hapticwithonline"])   && $this->CS->configs["item"]["hapticwithonline"]   == "y" ) ? true                                                                       : false;
    $PLocO    = ( isset($this->CS->configs["item"]["preferlocalonlines"]) && $this->CS->configs["item"]["preferlocalonlines"] == "y" ) ? true                                                                       : false;
    $OnlyMast = ( isset($this->CS->configs["item"]["onlymasterlink"])     && $this->CS->configs["item"]["onlymasterlink"]     == "y" ) ? true                                                                       : false;
    $SkipAUIs = ( isset($this->CS->configs["item"]["skipauis"])           && trim($this->CS->configs["item"]["skipauis"]) )            ? array_filter(explode(",", $this->CS->configs["item"]["skipauis"]))         : array();
    $SkipI2s  = ( isset($this->CS->configs["item"]["skipi2s"])            && trim($this->CS->configs["item"]["skipi2s"]) )             ? array_filter(explode(",", $this->CS->configs["item"]["skipi2s"]))          : array();
    $SkipMans = ( isset($this->CS->configs["item"]["skipmans"])           && trim($this->CS->configs["item"]["skipmans"]) )            ? array_filter(explode(",", $this->CS->configs["item"]["skipmans"]))         : array();
    $BlackCom = ( isset($this->CS->configs["item"]["commentblacklist"])   && trim($this->CS->configs["item"]["commentblacklist"]) )    ? array_filter(explode(",", $this->CS->configs["item"]["commentblacklist"])) : array();

    // Container
    $Items  = array();

     // Always because of $AddItems

    // Get MARC items
    $MExps = $this->getCompleteArray(array($this->Prefix . "980" => array("1","2","a","b","c","d","e","f","g","h","j","k","l","u","x","y","z")));

    // Backup full recs
    $M980s = $MExps;

    $ILNGs = array();
    foreach ($MExps as $Rec)
    {
      // Skip Items from other mandants
      $MAN = isset($Rec["y"][0]) ? $Rec["y"][0] : "";
      if ( $MAN && count($SkipMans) )
      {
        // $this->CS->debug("ALX", $SkipMans);
        // $this->CS->debug("ALX", $MAN);

        $Break = false;
        foreach ( $SkipMans as $SM )
        {
          if ( substr($SM, -1) == "*" )
          {
            $Pos = strpos($SM, "*");
            if ( substr($SM, 0, $Pos) == substr($MAN, 0, $Pos) ) $Break = true;
          }
          else
          {
             if ( $SM == $MAN )  $Break = true;
          }
          if ( $Break ) break;
        }
        if ( $Break ) continue;
      }

      // $this->CS->debug("ALX", "Yes");

      // Skip FakeItems without ILN or with ILN=s
      $ALI = isset($Rec["e"][0]) ? $Rec["e"][0] : "";
      if ( !$ALI
        || !$ALI && in_array("empty",        $SkipAUIs)
        ||  $ALI && in_array($ALI,           $SkipAUIs)
        ||  $ALI && in_array($ALI.".online", $SkipAUIs) &&  $this->Online )
      {
        $AddItems[] = $Rec;
      }

      // $this->CS->debug("ALX", $Rec["2"][0] . ": " . $ALI);
      // $this->CS->debug("ALX", $SkipAUIs);
      // $this->CS->debug("ALX", $this->Online);
      // $this->CS->debug("ALX327", $AddItems);

      if ( !$this->Online || $HWithO )
      {

        if ( !$ALI && in_array("empty",        $SkipAUIs)
          ||  $ALI && in_array($ALI,           $SkipAUIs)
          ||  $ALI && in_array($ALI.".online", $SkipAUIs) &&  $this->Online )
        {
          continue;
        }

        $this->Counts["physical"]++;

        $ExpID = isset($Rec["b"][0]) ? strtoupper($Rec["b"][0]) : $this->Counts["physical"];

        if ( isset($Rec["2"][0]) && isset($Rec["b"][0]) ) $ILNGs[$Rec["2"][0]][] = strtoupper($Rec["b"][0]);

        // $this->CS->debug("ALX", "Found: " .  $ExpID);

        foreach ($Rec as $Code => $SubRecs)
        {
          // Add displays MARC
          if ( is_array($SubRecs) )
          {
            $Items[$ExpID][$Code] = $SubRecs;
          }
        }
        $Items[$ExpID]["loc"] = isset($Rec["f"][0]) ? $Rec["f"][0]  : "default";
        $Items[$ExpID]["epn"] = $ExpID;
      }

      if ( count($ILNGs) ) $this->ILNGroups = $ILNGs;
    }

    if ( !$this->Online && !$HWithO ) return $Items;
    if ( !isset($this->CS->configs["item"]["links"]) || $this->CS->configs["item"]["links"] == "n" ) return $Items;

    // Determine OA
    $OA    = $this->get856OA();

    // Get local onlines

    // Group records together based on 1 & 2
    $MExps     = $this->getFlatArray(array($this->Prefix . "981" => array("1","2","r","w","y")));
    $Grps      = array();
    $GrpI      = 0;
    $LicLinks = array();

    foreach ($MExps as &$Rec)
    {
      if ( !isset($Rec["2"]) || !isset($Rec["1"]) )  continue;
      if ( !isset($Rec["r"]) && !isset($Rec["w"]) ) continue;

      if ( isset($Rec["r"]) )
      {
        $GID = ++$GrpI;
        $Grps[$GID] = array(
                            "iln" => $Rec["2"],
                            "enr" => $Rec["1"]
                           );
        $Grps[$GID]["link"] = $Rec["r"];
        $Grps[$GID]["host"] = $this->CS->getHost($Rec["r"]);
        if ( isset($Rec["y"]) )  $Grps[$GID]["text"] = $Rec["y"];
      }

      if ( isset($Rec["w"]) )
      {
        $UText = isset($Rec["y"])  ? $Rec["y"] : $this->CS->getText("bibline.license");
        $LicLinks[$Rec["2"] . "_" .$Rec["1"]][] = array(
                                                        "usagetext" => $UText,
                                                        "usagelink" => $Rec["w"]
                                                       );
      }
    }

      // if ( isset($Rec["y"]) )  $Grps[$GID]["text"] = $Rec["y"];
      // if ( isset($Rec["w"]) )  $Grps[$GID]["usagelink"] = $Rec["w"];

    // $this->CS->debug("ALX327", $Grps);
    // $this->CS->debug("ALX327", $LicLinks);

    foreach ($Grps as &$GVals)
    {
      $ExpID = "onlinelocal_" . $this->Counts["online"]++;
      $Items[$ExpID] = $GVals;
      $Items[$ExpID]["loc"] = "onlinelocal";
      $Items[$ExpID]["epn"] = $ExpID;
      $Items[$ExpID]["oa"]  = $OA;

      if ( isset($LicLinks[$Items[$ExpID]["iln"] . "_" . $Items[$ExpID]["enr"]]) )
      {
        $Items[$ExpID]["usagelinks"] = $LicLinks[$Items[$ExpID]["iln"] . "_" . $Items[$ExpID]["enr"]];
      }

      // Add additional records AddItems
      if ( count($AddItems) )
      {
        // $this->CS->debug("AL41X", $AddItems);

        $Items[$ExpID]["comment"] = array();
        $Items[$ExpID]["stock"]   = array();
        foreach ( $AddItems as $AIRec )
        {
          if ( isset($AIRec["k"]) )
          {

            // $this->CS->debug("AL41X", $BlackCom);
            foreach ( $AIRec["k"] as $K )
            {
              // $this->CS->debug("AL41X", $K);
              if (!in_array($K, $BlackCom)) $Items[$ExpID]["comment"][] = $K;
            }
          }
          if ( isset($AIRec["g"]) )
          {
            foreach ( $AIRec["g"] as $G )
            {
              $Items[$ExpID]["stock"][] =  (substr($G,0,8) == "Bestand:") ? trim(substr($G, 8)) : $G;
            }
          }
        }
      }

      // Add additional records
      if ( count($M980s) )
      {
        // $this->CS->debug("AL41X", $AddItems);
        foreach ( $M980s as $M980Rec )
        {
          if ( !isset($M980Rec["1"][0]) || $M980Rec["1"][0] != $Items[$ExpID]["enr"] )  continue;
          if ( !isset($M980Rec["2"][0]) || $M980Rec["2"][0] != $Items[$ExpID]["iln"] )  continue;

          if ( !isset($Items[$ExpID]["a"]) && isset($M980Rec["a"]) )  $Items[$ExpID]["a"] = $M980Rec["a"];
          if ( !isset($Items[$ExpID]["b"]) && isset($M980Rec["b"]) )  $Items[$ExpID]["b"] = $M980Rec["b"];
          if ( !isset($Items[$ExpID]["c"]) && isset($M980Rec["c"]) )  $Items[$ExpID]["c"] = $M980Rec["c"];
          if ( !isset($Items[$ExpID]["d"]) && isset($M980Rec["d"]) )  $Items[$ExpID]["d"] = $M980Rec["d"];
          if ( !isset($Items[$ExpID]["e"]) && isset($M980Rec["e"]) )  $Items[$ExpID]["e"] = $M980Rec["e"];
          if ( !isset($Items[$ExpID]["f"]) && isset($M980Rec["f"]) )  $Items[$ExpID]["f"] = $M980Rec["f"];
          if ( !isset($Items[$ExpID]["g"]) && isset($M980Rec["g"]) )  $Items[$ExpID]["g"] = $M980Rec["g"];
          if ( !isset($Items[$ExpID]["h"]) && isset($M980Rec["h"]) )  $Items[$ExpID]["h"] = $M980Rec["h"];
          if ( !isset($Items[$ExpID]["j"]) && isset($M980Rec["j"]) )  $Items[$ExpID]["j"] = $M980Rec["j"];
          if ( !isset($Items[$ExpID]["k"]) && isset($M980Rec["k"]) )  $Items[$ExpID]["k"] = $M980Rec["k"];
          if ( !isset($Items[$ExpID]["l"]) && isset($M980Rec["l"]) )  $Items[$ExpID]["l"] = $M980Rec["l"];
          if ( !isset($Items[$ExpID]["u"]) && isset($M980Rec["u"]) )  $Items[$ExpID]["u"] = $M980Rec["u"];
          if ( !isset($Items[$ExpID]["x"]) && isset($M980Rec["x"]) )  $Items[$ExpID]["x"] = $M980Rec["x"];
          if ( !isset($Items[$ExpID]["y"]) && isset($M980Rec["y"]) )  $Items[$ExpID]["y"] = $M980Rec["y"];
          if ( !isset($Items[$ExpID]["z"]) && isset($M980Rec["z"]) )  $Items[$ExpID]["z"] = $M980Rec["z"];
        }
      }
    }

    // $this->CS->debug("ALEXMARC", $Items);

    // return local onlines without globals
    if ( $this->Counts["online"] > 0 && $PLocO ) return $Items;

    // $this->CS->debug("ALEXMARC", $this->ID);
    // $this->CS->debug("ALEXMARC", $OA);

    // Get global onlines
    $MasterLink = "";
    $MExps  = $this->getFlatArray(array($this->Prefix . "856" => array("I1","I2","3","t","u","x","y","z")));

    foreach ($MExps as $Rec)
    {
      $ExpID = "onlineglobal_" . $this->Counts["online"]++;
      // $this->CS->debug("AÖXR", $Rec);

      if ( isset($Rec["I2"]) && in_array($Rec["I2"], $SkipI2s) ) continue;

      // MasterLink
      if ( ( !isset($Rec["I1"]) || !isset($Rec["I2"]) )
        && ( !( isset($Rec["I1"]) && $Rec["I1"] == "4"
          && isset($Rec["I2"]) && $Rec["I2"] == "2" ) )        // NOT
          // Schalter statt Clientname (03.09.2026): "y" laesst JEDEN Link zum
            // Masterlink werden, nicht nur einen mit x="Resolving-System".
          && ( ( isset($this->CS->configs["item"]["masterlinkany"])
              && $this->CS->configs["item"]["masterlinkany"] == "y" )
            || (isset($Rec["x"]) && $Rec["x"] == "Resolving-System") )
        && !$MasterLink && isset($Rec["u"]) )
      {
        $MasterLink = $Rec["u"];
      }
      else
      {
        if ( $OnlyMast )  continue;
      }

      $Text = [];
      foreach ($Rec as $Code => $Value)
      {
        if ( $Code == "3")  $Text[] = $Value;
        if ( $Code == "t")  $Text[] = $Value;
        if ( $Code == "x")  $Text[] = $Value;
        if ( $Code == "y")  $Text[] = $Value;
        if ( $Code == "z")  $Text[] = $Value;
        if ( $Code == "u")
        {
          // Adresse vor der Weitergabe geraderuecken - sonst scheitert auch
          // die Rechnernamen-Ermittlung darunter (siehe repairScheme).
          $Value = $this->repairScheme($Value);
          $Items[$ExpID]["link"] = $Value;
          $Items[$ExpID]["host"] = $this->CS->getHost($Value);
        }
      }
      $Items[$ExpID]["text"] = implode(", ", $Text);
      $Items[$ExpID]["oa"]   = $OA;

      // Add additional records
      if ( count($AddItems) )
      {
        // $this->CS->debug("AL41X", $AddItems);
        $Items[$ExpID]["comment"] = array();
        $Items[$ExpID]["stock"]   = array();
        foreach ( $AddItems as $AIRec )
        {
          if ( isset($AIRec["k"]) )
          {
            foreach ( $AIRec["k"] as $K )
            {
              $Items[$ExpID]["comment"][] = $K;
            }
          }
          if ( isset($AIRec["g"]) )
          {
            foreach ( $AIRec["g"] as $G )
            {
              $Items[$ExpID]["stock"][] =  (substr($G,0,8) == "Bestand:") ? trim(substr($G, 8)) : $G;
            }
          }
        }
      }

      $Items[$ExpID]["loc"]  = "onlineglobal";
      $Items[$ExpID]["epn"]  = $ExpID;
    }
    $this->MasterLink = $MasterLink;

    return $Items;
  }

  private function getLinks()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // GetLinks    (Links)
    // GetSearches (Suchen)


    if ( !isset($this->CS->configs["item"]["links"]) || $this->CS->configs["item"]["links"] == "n" ) return array();

    $HWithO   = ( isset($this->CS->configs["item"]["hapticwithonline"])   && $this->CS->configs["item"]["hapticwithonline"]   == "y" ) ? true : false;
    if ( !$this->Online && !$HWithO ) return array();

    // Container
    $Links = array();

    // Get MARC items
    $MExps = $this->getCompleteArray(array($this->Prefix . "776" => array("I2", "d", "i", "n", "o")));

    // $this->CS->debug("ALX", $MExps);

    foreach ($MExps as $Rec)
    {
      // $this->CS->debug("ALX", $Rec);
      if ( !isset($Rec["I2"]["0"]) || $Rec["I2"]["0"] != "8" ) continue;
      if ( !isset($Rec["o"]["0"])  || substr($Rec["o"]["0"],0,4) != "urn:" )  continue;

      $ExpID  = "link_" . $this->Counts["search"]++;

      $Text = $this->getSFTexts($Rec, "i", "", ": ");

      $Add   = array();
      $Add[] = $this->getSFTexts($Rec, "z", "ISBN ");
      $Add[] = $this->getSFTexts($Rec, "d");
      $Add[] = $this->getSFTexts($Rec, "n");
      $Add   = array_filter($Add);
      $Text  = trim($Text . " " . implode(", ", $Add));

      $Links[$ExpID]["link"]   = "https://nbn-resolving.org/" . $Rec["o"]["0"];
      $Links[$ExpID]["text"]   = $Text;
      $Links[$ExpID]["loc"]    = "link";
      $Links[$ExpID]["epn"]    = $ExpID;
    }

    // $this->CS->debug("ALX", $Links);
    return $Links;
  }

  private function getSearches()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // GetLinks    (Links)
    // GetSearches (Suchen)

    if ( !isset($this->CS->configs["item"]["siblings"]) || $this->CS->configs["item"]["siblings"] == "n" ) return array();

    // Container
    $Searches = array();

    // Get MARC items
    $MExps = $this->getCompleteArray(array($this->Prefix . "776" => array("I2", "d", "i", "n", "w", "z")));

    // $this->CS->debug("ALX", $MExps);

    foreach ($MExps as $Rec)
    {
      // $this->CS->debug("ALX", $Rec);
      if ( !isset($Rec["I2"]["0"]) || $Rec["I2"]["0"] != "8" ) continue;
      if ( ( !isset($Rec["z"]["0"])  || !trim($Rec["z"]["0"]) )
        && ( !isset($Rec["w"]["0"])  || !in_array(substr($Rec["w"]["0"],0,8), array("(DE-601)","(DE-627)"))) ) continue;

      $ExpID  = "search_" . $this->Counts["search"]++;

      $Text = $this->getSFTexts($Rec, "i");

      $Add   = array();
      $Add[] = $this->getSFTexts($Rec, "n");
      $Add[] = $this->getSFTexts($Rec, "z", "ISBN ");
      $Add[] = $this->getSFTexts($Rec, "d");
      $Add   = array_filter($Add);
      $Text  = trim($Text . " " . implode(", ", $Add));

      $Searches[$ExpID]["search"] = ( isset($Rec["w"]["0"]) && in_array(substr($Rec["w"]["0"],0,8), array("(DE-601)","(DE-627)") ) )
                                    ? "id("   . trim(substr($Rec["w"]["0"],8)) . ")"
                                    : "isn("  . trim($Rec["z"]["0"])           . ")";
      $Searches[$ExpID]["text"]   = $Text;
      $Searches[$ExpID]["loc"]    = "search";
      $Searches[$ExpID]["epn"]    = $ExpID;
    }

    // $this->CS->debug("ALX", $Searches);
    return $Searches;
  }

  private function getNorm($Norms)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( !isset($Norms["0"]) && !isset($Norms[" 0"]) )  return "";

    if ( isset($Norms["0"]) )
    {
      foreach ( $Norms["0"] as $One )
      {
        if ( isset($One) && substr($One,0,8) == "(DE-627)" ) return substr($One,8);
      }
    }
    if ( isset($Norms[" 0"]) )
    {
      foreach ( $Norms[" 0"] as $One )
      {
        if ( isset($One) && substr($One,0,8) == "(DE-627)" ) return substr($One,8);
      }
    }
    return "";
  }

  private function getGNDNorm($Type, $Norms)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( !isset($Norms["0"]) && !isset($Norms[" 0"]) )  return "";

    $More  = [];
    $GNDID = "";
    if ( isset($Norms["0"]) )
    {
      foreach ( $Norms["0"] as $One )
      {
        if ( isset($One) && substr($One,0,8) == "(DE-588)" )
        {
          $GNDID = substr($One,8);
          break;
        }
      }
    }
    if ( !$GNDID && isset($Norms[" 0"]) )
    {
      foreach ( $Norms[" 0"] as $One )
      {
        if ( isset($One) && substr($One,0,8) == "(DE-588)" )
        {
          $GNDID = substr($One,8);
          break;
        }
      }
    }
    /*
    if ( !$GNDID && isset($Norms["0"]) )
    {
      foreach ( $Norms["0"] as $One )
      {
        if ( isset($One) && substr($One,0,8) == "(DE-627)" )
        {
          $GNDID = substr($One,8);
          break;
        }
      }
    }
    if ( !$GNDID && isset($Norms[" 0"]) )
    {
      foreach ( $Norms[" 0"] as $One )
      {
        if ( isset($One) && substr($One,0,8) == "(DE-627)" )
        {
          $GNDID = substr($One,8);
          break;
        }
      }
    }
    */

    if ( $GNDID )
    {
      $Sort = ["person"      => ['prefix','surname','forename','dateofbirthanddeath','dateofbirth','dateofdeath','periodofactivity',
                                 'associateddate','gender','completename','earliername','latername','pseudonym','realidentity',
                                 'variantname','placeofbirth','placeofdeath','placeofactivity','associatedcountry',
                                 'professionoroccupation','academicdegree','titleofnobility','affiliation','familialrelationship',
                                 'relatedperson','biographicalorhistoricalinformation'],
               "family"      => ['prefix','surname','forename','dateofbirthanddeath','dateofbirth','dateofdeath','periodofactivity',
                                 'associateddate','gender','completename','earliername','latername','pseudonym','realidentity',
                                 'variantname','placeofbirth','placeofdeath','placeofactivity','associatedcountry',
                                 'professionoroccupation','academicdegree','titleofnobility','affiliation','familialrelationship',
                                 'relatedperson','biographicalorhistoricalinformation'],
              "organisation" => ['homepage','dateofestablishment','dateoftermination','dateofestablishmentandtermination',
                                 'variantname','predecessor','successor','ispartof','placeofbusiness','associatedplace',
                                 'associatedplace','isa','topic','relatedperson','founder','relatedorganisation',
                                 'biographicalorhistoricalinformation'],
              "place"        => ['homepage','location','dateofestablishment','dateoftermination','dateofestablishmentandtermination',
                                 'datepfproduction','variantname','predecessor','successor','ispartof','associatedplace',
                                 'associatedcountry','isa','relatedperson','architect','relatedsubject',
                                 'biographicalorhistoricalinformation'],
              "event"        => ['homepage','dateofevent','dateoftermination','dateofestablishment','variantname','predecessor',
                                 'successor','ispartof','organizerorhost','placeofevent','associatedcountry','associatedplace',
                                 'isa','topic','relatedperson','relatedorganisation','relatedevent',
                                 'biographicalorhistoricalinformation']];

      // $this->CS->debug("ALEXNORM1", $Type . " - " . $GNDID);
      $Pack = $this->CS->getNormsDB($Type, $GNDID);
      // $this->CS->debug("ALEXNORM1", $Pack);
      if ( isset($Pack["data"]) && is_array($Pack["data"]) && count($Pack["data"]) )
      {
        $More["type"]  = $Type;
        $More["gndid"] = $GNDID;
        $More["label"] = isset($Pack["data"]["preferredname"]) ? $Pack["data"]["preferredname"] : "";
        unset($Pack["data"]["preferredname"]);
        $More["more"] = isset($Pack["more"]) ? $Pack["more"] : [];
        foreach ( $Pack["data"] as $Key => $Val )
        {
          if ( $Key == "depiction" )  continue;
          // $this->CS->debug("ALEXNORM2", $Type . "." . $Key);
          $Text = $this->CS->getText($Type . "." . strtolower($Key));
          if ( !$Text )   $Text = $Key;

          // $this->CS->debug("ALEXNORM1", $Key);
          // $this->CS->debug("ALEXNORM2", $Val);

          if ( $Key == "gender"   && isset($Val["label"]) )    $Val = $Val["label"];

          if ( is_array($Val) && count($Val) && isset($Val[0]) && is_array($Val[0]) )
          {
            $Tmp = array();
            foreach ($Val as $V)
            {
              if ( isset($V["preferredname"]) )
              {
                $PT = $V["preferredname"];
                if ( isset($V["relationship"]) )  $PT .= " (" . $V["relationship"] . ")";
                if ( isset($V["gndid"]) )
                {
                  $Tmp[] = array("label"  => $PT,
                                 "search" => array("query" => "norm(" . $V["gndid"] . ")",
                                                   "type"  => "lukida"));
                }
                else
                {
                  $Tmp[] = array("label"  => $PT);
                }
              }
            }
            $Val = $Tmp;
            // $this->CS->debug("ALEXNORM1", $Val);
            // continue;
          }
          elseif ( is_array($Val) && count($Val) )
          {
            $Tmp = array();
            foreach ($Val as $V)
            {
              $Tmp[] = array("label"  => $V);
            }
            $Val = $Tmp;
          }

          $More["data"][strtolower($Key)] = array("label" => $Text,
                                                  "data"  => $Val);
        }

        // Sort stuff
        $Sorted = [];
        foreach ($Sort[$Type] as &$Key)
        {
          if ( isset($More["data"][$Key]) ) {
            $Sorted[$Key] = $More["data"][$Key];
          }
        }
        $More["data"] = $Sorted;

      }
      // $this->CS->debug("ALEXNORM2", $More);
    }
    return $More;
  }

  private function getOwnILNsOrCols()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // ILNs
    foreach ($this->ColDetails as $One)
    {
      if ( substr($One, 0, 8) == "GBV_ILN_" && isset($this->CS->configs["ilnmans"]) && in_array(substr($One,8),  $this->CS->configs["ilnmans"]) ) return true;
    }

    // Cols
    if ( $this->OwnCollection )  return true;

    return false;
  }

  private function getParents()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Parents = array();
    if ( isset($this->Convert[$this->Prefix . "773"]) )
    {
      foreach ( $this->Convert[$this->Prefix . "773"] as $Record )
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( $Key == "w" )
          {
            foreach ( $Values as $Value )
            {
              if ( in_array(substr($Value,0,8), array("(DE-601)","(DE-627)")) )
              {
                $Parents[]  = trim(substr($Value,8));
              }
            }
          }
        }
      }
    }
    if ( isset($this->Convert[$this->Prefix . "800"]) )
    {
      foreach ( $this->Convert[$this->Prefix . "800"] as $Record )
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( $Key == "w" )
          {
            foreach ( $Values as $Value )
            {
              if ( in_array(substr($Value,0,8), array("(DE-601)","(DE-627)")) )
              {
                $Parents[]  = trim(substr($Value,8));
              }
            }
          }
        }
      }
    }
    if ( isset($this->Convert[$this->Prefix . "810"]) )
    {
      foreach ( $this->Convert[$this->Prefix . "810"] as $Record )
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( $Key == "w" )
          {
            foreach ( $Values as $Value )
            {
              if ( in_array(substr($Value,0,8), array("(DE-601)","(DE-627)")) )
              {
                $Parents[]  = trim(substr($Value,8));
              }
            }
          }
        }
      }
    }
    if ( isset($this->Convert[$this->Prefix . "830"]) )
    {
      foreach ( $this->Convert[$this->Prefix . "830"] as $Record )
      {
        foreach ( $Record as $Key => $Values )
        {
          if ( $Key == "w" )
          {
            foreach ( $Values as $Value )
            {
              if ( in_array(substr($Value,0,8), array("(DE-601)","(DE-627)")) )
              {
                $Parents[]  = trim(substr($Value,8));
              }
            }
          }
        }
      }
    }
    return array_unique($Parents);
  }

  private function getPersonRoles($One, $onlyauthor = true)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Roles = [];
    if ( isset($One["4"]) && is_array($One["4"]) && count($One["4"]) )
    {
      $Roles = array_values($One["4"]);
    }
    elseif ( isset($One["e"]) && is_array($One["e"]) && count($One["e"]) )
    {
      foreach ( $One["e"] as $Role )
      {
        if ( strlen($Role) == 3 )
        {
          $Roles[] = $Role;
        }
        else
        {
          $Tmp = preg_replace("/[^a-zA-Z0-9öäü ]+/", "", strtolower($Role));
          if ( in_array($Tmp, array("bearb","begr","hrsg","komp","mitarb","red","ubers","adressat","komm",
                                    "stecher","verstorb","zeichner","präses","praeses","resp","widmungsempfänger",
                                    "widmungsempfaenger","zensor","beiträger","beitraeger","beiträger k","beitraeger k",
                                    "beiträger m","beitraeger m","interpr","verf")) )
          {
            $Tmp = str_replace(array("bearb","begr","hrsg","komp","mitarb","red","ubers","adressat","komm",
                                    "stecher","verstorb","zeichner","präses","praeses","resp","widmungsempfänger",
                                    "widmungsempfaenger","zensor","beiträger","beitraeger","beiträger k","beitraeger k",
                                    "beiträger m","beitraeger m","interpr","verf"),
                               array("edi","fdr","edt","cmp","cbr","pbd","trl","rcp","cmm","egr","dcs","drm","chm","chm",
                                     "rsp","dte","dte","cns","ctl","ctl","cta","cta","ctm","ctm","ipt","aut"), $Tmp);
            $Roles[] = $Tmp;
          }
        }
      }
    }
    else
    {
      if ( $onlyauthor ) $Roles[] = "aut";
    }
    return array_unique($Roles);
  }

  private function getOrgName($isAuthor, $Rec, $RecsOrg)
  {
    if ( !isset($Rec["6"][0]) )  return "";
    $Tmp = explode("-", $Rec["6"][0]);
    if ( !isset($Tmp[1]) ) return "";

    $Find = ($isAuthor ? "100" : "700" ) . "-" . $Tmp[1];
    foreach ( $RecsOrg as $RO )
    {
      if ( isset($RO["6"][0]) && substr($RO["6"][0],0,6) == $Find && isset($RO["a"][0]) )  return " " . $RO["a"][0];
    }
  }

  private function getOrgCorp($Field, $Rec, $RecsOrg)
  {
    if ( !isset($Rec["6"][0]) )  return "";
    $Tmp = explode("-", $Rec["6"][0]);
    if ( !isset($Tmp[1]) ) return "";
    $Find6 = $Field . "-" . $Tmp[1];

    if ( !isset($Rec["4"][0]) )  return "";
    $Find4 = $Rec["4"][0];

    foreach ( $RecsOrg as $RO )
    {
      if ( isset($RO["6"][0]) && substr($RO["6"][0],0,6) == $Find6 &&  
           isset($RO["4"][0]) && $RO["4"][0] == $Find4 )
      {
        $Tmp2 = "";
        if ( isset($RO["a"][0]) && $RO["a"][0] != "" && isset($RO["b"][0]) && $RO["b"][0] != "" )       $Tmp2  = $RO["a"][0] . ". " . $RO["b"][0];
        if ( isset($RO["a"][0]) && $RO["a"][0] != "" && (!isset($RO["b"][0]) || $RO["b"][0] == "" ) )   $Tmp2  = $RO["a"][0];
        if ( ( !isset($RO["a"][0]) || $RO["a"][0] == "" ) && isset($RO["b"][0]) && $RO["b"][0] != "" )  $Tmp2  = $RO["b"][0];
        if ( isset($RO["c"][0]) && $RO["c"][0] != "" )                                                  $Tmp2 .= " " . $RO["c"][0];
        return $Tmp2;
      }
    }
    return "";
  }

  private function getOrgPubl($Field, $Rec, $RecsOrg)
  {
    if ( !isset($Rec["6"][0]) )  return "";
    $Tmp = explode("-", $Rec["6"][0]);
    if ( !isset($Tmp[1]) ) return "";
    $Find6 = $Field . "-" . $Tmp[1];

    foreach ( $RecsOrg as $RO )
    {
      if ( isset($RO["6"][0]) && substr($RO["6"][0],0,6) == $Find6 )
      {
        $Return = [];
        if ( isset($RO["a"][0]) && $RO["a"][0] != "" )  $Return["a"]  = $RO["a"][0];
        if ( isset($RO["b"][0]) && $RO["b"][0] != "" )  $Return["b"]  = $RO["b"][0];
        if ( isset($RO["c"][0]) && $RO["c"][0] != "" )  $Return["c"]  = $RO["c"][0];
        return $Return;
      }
    }
    return "";
  }

  // Reines Zerlegen des MARC-XML - ohne Clientbezug UND ohne Praefix
  // (03.09.2026).
  //
  // Die Feldnamen heissen hier schlicht "980", "008", "Lead". Das Praefix - "M"
  // fuer den eigenen Satz, "PM" fuer denselben Satz in der Rolle des
  // Elternteils - kommt erst in getReducedConvert() dazu. Damit ist der
  // zerlegte Satz von der Rolle unabhaengig: EINE Datei je Id genuegt, und
  // derselbe Satz kann einmal Kind und einmal Elternteil sein.
  private function parseMarcXml($XML)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    try
    {
      $xml = simplexml_load_string($XML);
    }
    catch (Exception $e)
    {
      // $this->CS->debug("XMLError", $XML);
      return array();
    }

    $Convert = array();
    if ( !isset($xml->record[0]) ) return $Convert;

    // Leader
    $Convert["Lead"] = (isset($xml->record[0]->leader)) ? (string) $xml->record[0]->leader : "";

    // Control Fields
    foreach($xml->record[0]->controlfield as $CF)
    {
      $Convert[(string) $CF["tag"]] = (string) $CF[0];
    }

    // Data Fields
    $CTags    = array();
    $ErsteSub = array();   // erstes Unterfeld je Feld >= 900, s. reduceToClient()
    foreach($xml->record[0]->datafield as $DF)
    {
      $Tag = (string) $DF["tag"];

      // Die Auswahl nach ILN, Mandant und ISIL steht seit 03.09.2026 NICHT mehr
      // hier im Zerleger, sondern hinter der Schleife in reduceToClient().
      // Grund: der zerlegte Satz soll clientneutral werden (Stufe 2 des
      // Satz-Caches). Erst kurz vor dem Ausliefern wird er auf das reduziert,
      // was dieser Client sehen darf.
      //
      // Hier wird nur gemerkt, was die Auswahl braucht und was aus dem
      // zerlegten Satz nicht mehr hervorginge: der Wert des ERSTEN Unterfeldes
      // (fuer 912; weiter unten werden die Unterfelder alphabetisch sortiert).
      if ( $Tag >= "900" )
      {
        $ErsteSub[$Tag][isset($CTags[$Tag]) ? $CTags[$Tag] + 1 : 0] = (string) $DF->subfield;
      }


      if ( !isset($CTags[$Tag]) )
      {
        $CTags[$Tag] = 0;
      }
      else
      {
        $CTags[$Tag]++;
      }
      $DFI = $CTags[$Tag];
      $Convert[$Tag][$DFI] = array();

      // Indicators
      if ( trim((string) $DF["ind1"]) )  $Convert[$Tag][$DFI]["I1"] = (string) $DF["ind1"];
      if ( trim((string) $DF["ind2"]) )  $Convert[$Tag][$DFI]["I2"] = (string) $DF["ind2"];

      // Subfields
      $CSubs = array();
      foreach($DF->subfield as $SF)
      {
        $Sub = (string) $SF["code"];
        if ( !isset($CSubs[$Sub]) )
        {
          $CSubs[$Sub] = 0;
        }
        else
        {
          $CSubs[$Sub]++;
        }
        $SFI = $CSubs[$Sub];
        $Convert[$Tag][$DFI][$Sub][$SFI] = (string) $SF[0];
      }
      ksort($Convert[$Tag][$DFI]);
    }
    ksort($Convert);

    // Zerlegt ist der Satz jetzt CLIENTNEUTRAL. Zusammen mit $ErsteSub (dem
    // ersten Unterfeld je Feld ab 900) ist das alles, was reduceToClient()
    // spaeter braucht - deshalb wird genau dieses Paar zwischengespeichert
    // (03.09.2026, Stufe 2).
    return array("marc" => $Convert, "erstesub" => $ErsteSub);
  }

  // Zerlegen ODER aus dem gemeinsamen Bestand holen, danach fuer diesen Client
  // reduzieren (03.09.2026).
  //
  // Das Zerlegen ist der teure Teil (gemessen 0,56 ms je Satz gegen 0,06 ms fuer
  // alles Weitere), und sein Ergebnis ist fuer jeden Client dasselbe. Es liegt
  // deshalb in EINER Datei je Satz, ohne Client und ohne Sprache:
  //
  //     caches/ids/<Id in Vierergruppen>/marcxml.inc
  //
  // Gueltig bleibt sie, solange das Indexfeld "up_date" unveraendert ist -
  // dieselbe Regel wie bei readCache2Marc() in user.php.
  private function getReducedConvert($XML, $ID = "", $UpDate = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Neutral = $ID ? $this->CS->readMarcNeutral($ID, $UpDate) : null;
    if ( !is_array($Neutral) )
    {
      $Neutral = $this->parseMarcXml($XML);
      // Die wenigen Werte, die doConvert() ausser dem MARC-Satz noch aus der
      // Solr-Antwort nimmt, gehen mit in den Bestand (03.09.2026). Sie sind
      // ebenso clientneutral, und mit ihnen laesst sich ein Satz spaeter ganz
      // ohne Solr-Abfrage wieder aufbauen - das spart beim Nachladen der
      // Elternsaetze den zweiten Weg zum Index.
      $Neutral["solr"] = array(
        "collection"         => isset($this->SolrOne["collection"])         ? $this->SolrOne["collection"]         : array(),
        "collection_details" => isset($this->SolrOne["collection_details"]) ? $this->SolrOne["collection_details"] : array(),
        "format_phy_str_mv"  => isset($this->SolrOne["format_phy_str_mv"])  ? $this->SolrOne["format_phy_str_mv"]  : array(),
        "publishDateSort"    => isset($this->SolrOne["publishDateSort"])    ? $this->SolrOne["publishDateSort"]    : "",
        "remote_bool"        => isset($this->SolrOne["remote_bool"])        ? $this->SolrOne["remote_bool"]        : ""
      );
      if ( $ID && isset($Neutral["marc"]) && count($Neutral["marc"]) )
        $this->CS->writeMarcNeutral($ID, $Neutral, $UpDate);
    }

    // Jetzt bekommt der Satz seine ROLLE: "M" fuer den eigenen Satz, "PM" fuer
    // denselben Satz als Elternteil. Vorher ist er rollenfrei gespeichert -
    // deshalb genuegt EINE Datei je Id, gleichgueltig, in welcher Rolle ihn
    // eine Bibliothek zuerst gesehen hat.
    $Convert  = $this->setzePraefix(isset($Neutral["marc"])     ? $Neutral["marc"]     : array());
    $ErsteSub = $this->setzePraefix(isset($Neutral["erstesub"]) ? $Neutral["erstesub"] : array());

    $this->reduceToClient($Convert, $ErsteSub);

    return $Convert;
  }

  // Feldnamen um das Praefix der Rolle ergaenzen (03.09.2026).
  //
  // Der Zerleger legt sie rollenfrei ab ("980", "Lead"); erst hier werden
  // daraus "M980"/"PM980" bzw. "Lead"/"PLead". Alles Weitere - reduceToClient()
  // und saemtliche Auswertungen in marc.php und item.php - arbeitet
  // unveraendert mit den vollen Namen.
  private function setzePraefix($Felder)
  {
    if ( !is_array($Felder) || !count($Felder) )  return array();

    $Neu = array();
    foreach ( $Felder as $Tag => $Wert )
    {
      if ( $Tag === "Lead" )  $Neu[$this->Prefix == "PM" ? "PLead" : "Lead"] = $Wert;
      else                    $Neu[$this->Prefix . $Tag] = $Wert;
    }
    ksort($Neu);
    return $Neu;
  }

  // Felder ab 900 auf das reduzieren, was DIESER Client sehen darf.
  //
  // Wortgleiche Uebernahme der Bedingungen, die bis zum 03.09.2026 im Zerleger
  // standen (dort als "continue", bevor das Feld ueberhaupt angelegt wurde):
  //
  //   912          Kennungen fremder ILN/ISIL, dazu SYSFLAG_1 und SYSFLAG_A
  //   >= 900       Saetze fremder ILN            (configs["ilns"])
  //   980          zusaetzlich fremde Mandanten  (configs["ilnandman"])
  //
  // $ErsteSub traegt je Feld den Wert des ersten Unterfeldes - fuer 912 wurde
  // frueher genau dieser geprueft.
  private function reduceToClient(&$Convert, $ErsteSub)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Grenze = $this->Prefix . "900";
    $I912   = $this->Prefix . "912";
    $I980   = $this->Prefix . "980";

    foreach ( $Convert as $Tag => $Recs )
    {
      if ( !is_array($Recs) || $Tag < $Grenze )  continue;

      foreach ( $Recs as $DFI => $Rec )
      {
        $Weg = false;

        if ( $Tag == $I912 )
        {
          $Tmp = isset($ErsteSub[$Tag][$DFI]) ? $ErsteSub[$Tag][$DFI] : "";
          if ( ( isset($this->CS->configs["ilnmans"]) && is_array($this->CS->configs["ilnmans"])
              && substr($Tmp,0,8) == "GBV_ILN_" && !in_array(substr($Tmp,8), $this->CS->configs["ilnmans"]) )
            || ( isset($this->CS->configs["isils"]) && is_array($this->CS->configs["isils"]) && substr($Tmp,0,5) == "ISIL_"
              && !in_array(substr($Tmp,5), $this->CS->configs["isils"]) )
            || ( substr($Tmp,0,8) != "GBV_ILN_" && substr($Tmp,0,5) != "ISIL_" && in_array($Tmp, array("SYSFLAG_1", "SYSFLAG_A")) ) )  $Weg = true;
        }
        else
        {
          // Wie frueher: der ZULETZT gelesene Wert des jeweiligen Unterfeldes
          $SF2 = ( isset($Rec["2"]) && is_array($Rec["2"]) && count($Rec["2"]) ) ? (string) end($Rec["2"]) : "";
          $SFY = ( isset($Rec["y"]) && is_array($Rec["y"]) && count($Rec["y"]) ) ? substr((string) end($Rec["y"]), 1) : "";

          if ( trim($SF2) && isset($this->CS->configs["ilns"])
            && is_array($this->CS->configs["ilns"])
            && !in_array($SF2, $this->CS->configs["ilns"]) )  $Weg = true;

          if ( !$Weg && $Tag == $I980
            && isset($this->CS->configs["ilnandman"][$SF2])
            && is_array($this->CS->configs["ilnandman"][$SF2])
            && count($this->CS->configs["ilnandman"][$SF2])
            && !in_array($SFY, $this->CS->configs["ilnandman"][$SF2]) )  $Weg = true;
        }

        if ( $Weg )  unset($Convert[$Tag][$DFI]);
      }

      // Bleibt nichts uebrig, faellt das Feld ganz weg - wie frueher, wo es dann
      // gar nicht erst angelegt wurde. Sonst die Zaehlung wieder schliessen.
      if ( !count($Convert[$Tag]) )  unset($Convert[$Tag]);
      else                           $Convert[$Tag] = array_values($Convert[$Tag]);
    }
  }

  private function getSeries()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Lead = isset($this->Convert["Lead"])                ? $this->Convert["Lead"]                : "";
    $M008 = isset($this->Convert[$this->Prefix . "008"]) ? $this->Convert[$this->Prefix . "008"] : "";

    if ( isset($Lead) && substr($Lead, 7,1) == "s"  && substr($M008,21,1) == "m")                              return true;
    if ( isset($Lead) && substr($Lead, 7,1) == "m"  && substr($M008, 6,1) == "m" && substr($Lead,19,1) == "a") return true;
    if ( isset($Lead) && substr($Lead, 6,2) == "ai"
      && in_array(substr($M008, 6,1), array("c","m")) && substr($Lead,19,1) == "a")                            return true;
    return false;
  }

  private function getSFTexts($Array, $Subfield, $Prefix = "", $PostFix = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Texts = "";
    if ( isset($Array[$Subfield]) && is_array($Array[$Subfield]) && count($Array[$Subfield]) )
    {
      foreach ($Array[$Subfield] as $Text)
      {
        $Texts .= ($Texts) ? ", " . $Text : $Prefix . $Text . $PostFix;
      }
    }
    return $Texts;
  }

  private function getSubfield($Field, $Subfield = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Data = array();
    if ( array_key_exists($Field, $this->Convert) )
    {
      foreach ( $this->Convert[$Field] as $Record )
      {
        foreach ( $Record as $Key => $Value )
          {
            if ( $Key == $Subfield )
            {
              $Data[] = $Value;
            }
          }
      }
    }
    return ($Data);
  }

  private function getSubfields($Field, $Subfields = array() )
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Data = array();
    if ( array_key_exists($Field, $this->Convert) )
    {
      foreach ( $this->Convert[$Field] as $Record )
      {
        $Rec = array();
        foreach ( $Record as $Key => $Subrecord )
        {
          if ( in_array($Key, $Subfields) )
          {
            foreach ( $Subrecord as  $Value )
            {
              $Rec[$Key] = $Value;
            }
          }
        }
        $Data[] = $Rec;
      }
    }
    return ($Data);
  }

  private function getSubfieldFirst($Field, $Subfield = "")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    if ( array_key_exists($Field, $this->Convert) )
    {
      foreach ( $this->Convert[$Field] as $Record )
      {
        foreach ( $Record as $Key => $Subrecord )
        {
          if ( $Key == $Subfield )
          {
            foreach ( $Subrecord as $Value )
            {
              return $Value;
            }
          }
        }
      }
    }
    return "";
  }

  private function getVolume($Field, $Subfield, $Indikator1)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Output = array();
    if ( array_key_exists($Field, $this->Convert) )
    {
      foreach ($this->Convert[$Field] as $Record)
      {
        if ( !isset($Record["I1"]) || $Record["I1"] != $Indikator1 )  continue;
        foreach ( $Record as $Key => $Values )
        {
          if ( (string)$Key == $Subfield )
          {
            foreach ($Values as $Val)
            {
              if (true)  $Output[] = $Val;
            }
          }
        }
      }
    }
    return $Output;
  }

  private function isOpenAccess($Text)
  {
    if ( stripos( $Text, "oa"                            ) !== false ) return true;
    if ( stripos( $Text, "oalizenz"                      ) !== false ) return true;
    if ( stripos( $Text, "openaccess"                    ) !== false ) return true;
    if ( stripos( $Text, "kostenfrei"                    ) !== false ) return true;
    if ( stripos( $Text, "open access-lizenzbedingungen" ) !== false ) return true;
    if ( stripos( $Text, "openaccess lizenz"             ) !== false ) return true;
    return false;
  }

  private function get856OA()
  {
    // Get global onlines
    $MExps = $this->getFlatArray(array($this->Prefix . "856" => array("z")));
    foreach ($MExps as $Rec)
    {
      foreach ($Rec as $Code => $Value)
      {
        if ( $Code == "z" && $this->isOpenAccess($Value) )   return true;
      }
    }
    return false;
  }

  private function isMulti()
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    return ( in_array($this->Format, $this->CS->formatsMulti) )          ? true : false;
  }

  private function prettyRecs($Filter)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $Recs = array();
    $Item = 0;
    foreach ( $Filter as $Field => $Subfields )
    {
      if ( array_key_exists($this->Prefix . $Field, $this->Convert) )
      {
        foreach ($this->Convert[$this->Prefix . $Field] as $Record)
        {
          $Rec = array("text" => "", "action" => array(), "more"   => array());
          $Item++;
          foreach ( $Record as $Key => $Subrecord )
          {
            foreach ( $Subfields as $Sub )
            {
              if ( (string)$Key == (string)$Sub )
              {
                foreach ( $Subrecord as $Value )
                {
                  if ( $Rec["text"] )
                  {
                    $Rec["text"] .= " : " . $Value;   // Append values
                  }
                  else
                  {
                    $Rec["text"] = $Value;
                  }
                }
              }
            }
          }
          if ($Rec["text"]) $Recs["item_" . $Item] = $Rec;
        }
      }
    }
    $Doub = array();
    foreach ( $Recs as $Ind => $Rec )
    {
      if ( !in_array($Rec["text"], $Doub) )
      {
        $Doub[] = $Rec["text"];
      }
      else
      {
        unset($Recs[$Ind]);
      }
    }
    return $Recs;
  }

  private function doConvert(&$One, $Prefix)
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    // Load MARC library and pass params
    if ( !isset($One["fullrecord_marcxml"]) || !isset($One["id"]) ) return;
    $this->Counts = array("search" => 0, "physical" => 0, "online" => 0);

    // MasterLink zuruecksetzen (03.09.2026).
    //
    // Das marc-Objekt verarbeitet ALLE Saetze einer Trefferseite nacheinander,
    // und $this->MasterLink wird erst am ENDE von getItems() gesetzt. Die
    // Funktion hat drei fruehe Ausstiege (nicht online und kein
    // hapticwithonline; item.links = n; eigene Onlineexemplare bei
    // preferlocalonlines). Nimmt ein Satz einen davon, behielt er den Wert des
    // ZUVOR verarbeiteten Satzes - beim ersten Satz war das null, danach "" oder
    // sogar der Verweis des Nachbarn. Daher stand bei denselben Saetzen mal ""
    // und mal null, je nachdem, welche vorher aus dem Cache kamen.
    $this->MasterLink = "";

    $this->SolrOne       = $One;   // fuer den neutralen Bestand, s. getReducedConvert()
    $this->ID            = ($One["id"] != "" && $One["id"] != "0") ? (string) $One["id"] : "RAND".rand(100000000,999999999);
    $this->Collection    = ( isset($One["collection"]) )                                   ? $One["collection"]           : array();
    $this->ColDetails    = ( isset($One["collection_details"]) )                           ? $One["collection_details"]   : array();
    $this->Format        = ( isset($One["format_phy_str_mv"][0]) )                         ? $One["format_phy_str_mv"][0] : "";
    $this->Year          = ( isset($One["publishDateSort"]) )                              ? $One["publishDateSort"]      : "";
    $this->Online        = ( isset($One["remote_bool"]) && $One["remote_bool"] == "true" ) ? true                         : false;
    $this->Prefix        = $Prefix;
    $this->Convert       = $this->getReducedConvert($One["fullrecord_marcxml"], $this->ID,
                                                     isset($One["up_date"]) ? (string) $One["up_date"] : "");  // Important for functions below
    $this->Parents       = $this->getParents();
    // OwnCollection MUSS vor getOwnILNsOrCols() stehen: die Funktion wertet
    // $this->OwnCollection mit aus. Stand es danach, galt beim Kind noch der
    // Wert des ZUVOR verarbeiteten Satzes - also des Elternteils (20.08.2026).
    $this->OwnCollection = (count(array_intersect($this->Collection, $this->CS->configs["mycolls"]))) ? true : false;
    $this->Owner         = $this->getOwnILNsOrCols();
    $this->ArticleLink   = $this->getArticleLinks();
    $this->Series        = $this->getSeries();
    $this->ArtCode       = $this->getArtCode();
    $this->OwnOrg        = $this->getOwnOrg();
    $this->Fields        = $this->getBiblio();
    $this->Items         = $this->getItems();
    $this->Searches      = $this->getSearches();
    $this->Links         = $this->getLinks();

     // Log ID list for marc
    if ( APPDEBUG["IDS"] && ( (is_array(APPDEBUG["IDS"]) && in_array($this->ID, APPDEBUG["IDS"])) || (!is_array(APPDEBUG["IDS"]) && APPDEBUG["IDS"] == $this->ID)) )
    {
      $this->CS->debug("IDS", $this->ID);
      $this->CS->debug("MCD", $this->Convert);
    }

    // $this->CS->debug("ALXMA", $this->Convert );

    // Log media data

    if ( APPDEBUG["MCD"] ) $this->CS->debug("MCD", $this->Convert);
    $Media  = array
    (
      "id"            => $this->ID,
      "format"        => $this->Format,
      "online"        => $this->Online,
      "series"        => $this->Series,
      "formatcode"    => str_replace(" ", "", strtolower($this->Format)),
      "artcode"       => $this->ArtCode,
      "ownorg"        => $this->OwnOrg,
      "year"          => $this->Year,
      "rootid"        => count($this->Parents) ? $this->Parents[0] : "",
      "area"          => "findex",
      "owner"         => $this->Owner,
      "collections"   => array_map('strtolower', $this->Collection),
      "collection_details" => $this->ColDetails,
      "owncollection" => $this->OwnCollection,
      "hapticarticlelinks" => !$this->Online && $this->ArticleLink ? true : false,
      "onlinearticlelinks" =>  $this->Online && $this->ArticleLink ? true : false,
      "items"         => $this->Items,
      "itemcount"     => $this->Counts,
      "searches"      => $this->Searches,
      "links"         => $this->Links,
      "ilngroups"     => $this->ILNGroups,
      "masterlink"    => $this->MasterLink,
      "bibliographic" => $this->Fields,     // Should be cached as well and
      "marc"          => $this->Convert     // will be removed later
    );

    if ( isset($this->CS->configs["item"]["use264casyear"]) && $this->CS->configs["item"]["use264casyear"] == "y"
       && isset($this->Convert["M264"][0]["c"][0]) && trim($this->Convert["M264"][0]["c"][0]) )
    {
      $Media["year"] = $this->Convert["M264"][0]["c"][0];
    }

    // Log ID list for media
    if ( APPDEBUG["IDS"] && ( (is_array(APPDEBUG["IDS"]) && in_array($this->ID, APPDEBUG["IDS"])) || (!is_array(APPDEBUG["IDS"]) && APPDEBUG["IDS"] == $this->ID)) )
    {
      $this->CS->debug("IDS", $this->ID);
      $this->CS->debug("MED", $Media);
    }

    // Log media data
    if ( APPDEBUG["MED"] ) $this->CS->debug("MED", $Media);

    return $Media;
  }

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  public function getConverts($Add, $Prefix = "M")
  {
    if ( APPDEBUG["FKT"] ) $this->CS->debug("FKT",__METHOD__);

    $this->add = $Add;

    if ( isset($this->CS->works["solr"]) && is_array($this->CS->works["solr"]) && count($this->CS->works["solr"]) )
    {
      foreach ( $this->CS->works["solr"] as $SolrOne )
      {
        $ID = isset($SolrOne["id"]) ? $SolrOne["id"] : "";
        if ( !$ID ) continue;

        // Stand des Satzes im Index - er entscheidet ueber den Cache
        // (03.09.2026). Fehlt das Feld, greift wie bisher das Alter.
        $UpDate = isset($SolrOne["up_date"]) ? (string) $SolrOne["up_date"] : "";
        $this->CS->works["updates"][$ID] = $UpDate;

        // Der clientspezifische Satz-Cache ist zum 03.09.2026 entfallen: er
        // brachte nichts mehr, seit das Zerlegen des MARC-Satzes und die
        // Auskuenfte der zentralen Datenbank in gemeinsamen Bestaenden liegen
        // (gemessen 1609 ms ohne ihn gegen 1302 ms mit ihm - Rauschen). Der
        // Satz wird also immer gebaut; teuer war daran nie das Bauen.
        {
          // Convert and store data
          $One = $this->doConvert($SolrOne, $Prefix);

          if ( !$this->add )
          {
          	// $this->CS->debug("ALX C", $ID);
          	
            $this->CS->works["marc"][$ID] = $One;
            $this->CS->results["data"][]  = $One;

            // $this->CS->debug("ALXMMA", $One);

            // Update hierarchy
            if ( isset($One["format"]) )
            {
              if ( isset($One["rootid"]) && $One["rootid"] != "" )
              {
                // Array from child to parent (Only one parent)
                $this->CS->hierarchy["childs"][$ID]               = $One["rootid"];

                 // Array from parent to childs (multiple childs)
                $this->CS->hierarchy["parents"][$One["rootid"]][] = $ID;
              }
            }

            // $this->CS->debug("ALXMMA", $this->CS->hierarchy);

            if ( isset($this->CS->display["marc"]) && $this->CS->display["marc"] )      $this->CS->results["marc"][$ID] = $this->Convert;
          }
          else
          {
          	// $this->CS->debug("ALX P", $ID);

            $this->CS->works["add"][$ID] = $One;

            $CIDs = isset($this->CS->hierarchy["parents"][$ID]) ? $this->CS->hierarchy["parents"][$ID] : array();
            $Kids = array_flip(array_column($this->CS->results["data"], "id"));

            // $this->CS->debug("ALX333", $Kids);

            foreach ( $CIDs as $CID )
            {
              if ( isset($this->CS->display["marc"]) && $this->CS->display["marc"]
                && isset($this->CS->results["marc"][$CID]) )
              {
                $this->CS->results["marc"][$CID] += $this->Convert;

                // Bei aktivem articleparentshift wird der Besitz des Elternteils
                // NICHT auf die Kinder vererbt: ErfurtUB besitzt nur die Eltern,
                // nicht die Kinder, und die Eltern sollen nicht betrachtet
                // werden. Gleiche Linie wie useParent() in item.php. (20.08.2026)
                //
                // Der frueher hier genannte Cache-Schaden - der Wert ging per
                // writeMarc2Cache mit in die Datei und vergiftete sie dauerhaft - kann
                // seit dem 03.09.2026 nicht mehr entstehen: der clientspezifische
                // Satz-Cache ist entfallen, gespeichert wird nur noch der
                // clientneutrale, zerlegte Satz.
                $APSInherit = ( isset($this->CS->configs["item"]["articleparentshift"])
                             && trim($this->CS->configs["item"]["articleparentshift"]) == "y" ) ? true : false;
                if ( $this->Owner && !$APSInherit && isset($this->CS->results["data"]) && is_array($this->CS->results["data"]) && count($this->CS->results["data"]) )
                {
                  // Inherit parent ownership to childs
                  if ( isset($Kids[$CID]) )
                  {
                    // $this->CS->debug("ALX333", $this->CS->works["marc"][$CID]["owner"]);
                    $this->CS->works["marc"][$CID]["owner"] = 1;
                    // $this->CS->debug("ALX333", $this->CS->works["marc"][$CID]["owner"]);
                  }
                }
              }
            }
          }

        }
      }
    }
    return;
  }

  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************

}

?>