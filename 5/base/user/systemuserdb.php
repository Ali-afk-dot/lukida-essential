<?php

class systemuserdb extends appl
{
  
  
  
  public function __construct($f3)
  {
    parent::__construct($f3);
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__METHOD__);
  }
  
  
  
  

  
  
  
  protected function renewTokenDB()
  {
    if ( APPDEBUG["FKT"] ) $this->debug("FKT",__FUNCTION__);

    
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

    

    return array("token" => $APIToken, "validuntil" => $ValidUntil);
  }

  
  
  
  
  
  
  
}

?>