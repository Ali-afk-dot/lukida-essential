<?php

class systemuserdb extends appl
{
  // *******************************************************************
  // ************* V A R S  &  C O N S T R U C T ***********************
  // *******************************************************************
  public function __construct($f3)
  {
    parent::__construct($f3);
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);
  }
  
  // *******************************************************************
  // ************* P R I V A T E ***************************************
  // *******************************************************************

  // *******************************************************************
  // ************* P R O T E C T E D ***********************************
  // *******************************************************************
  protected function renewTokenDB()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__FUNCTION__);

    // Active inside view
    $SQL  = "select client from hostingclients where client=:client";
    $Data = array (
                    "client"   => $this->client
                  );
    $Rows = $this->execDB($SQL, $Data);
    if ( count($Rows) != 1 )  return array();

    $APIToken = $this->encodeToken(array(
                                          'ml' => 'user',
                                          'cl' => $this->client,
                                          'dt' => time()
                                         ));
    $ValidUntil = (new DateTime())->modify("+" . $this->f3->get("application.daysvalidtoken") . " day")->format('Y-m-d H:i:s');

    // No storage of token on iser level

    return array("token" => $APIToken, "validuntil" => $ValidUntil);
  }

  // *******************************************************************
  // ************* P U B L I C *****************************************
  // *******************************************************************
  
  // **********************************************************************
  // ************* A R E A  5 1 *******************************************
  // **********************************************************************
}

?>