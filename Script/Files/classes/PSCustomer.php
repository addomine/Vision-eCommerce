<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

define("FILE_CUSTOMER", "TSD_UTENTI");

define("FILE_ANAGRAFICHE", "TSD_ANAGRAFICHE");

/**
 * Description of PSCustomer
 *
 * @author ALLINITSRLS
 */
class PSCustomer extends PSObject {

  /**
   * 
   * Metodo utilizzato per caricare dentro Prestashop tutte le utenze passate da
   * tracciato ERP
   */
  function getCustomer() {
    $array_customer_enabled = $this->getEnabledCustomer();

    if (!file_exists(FOLDER_UNZIP . FILE_CUSTOMER . ".txt"))
    {
      $this->fileLog("#7 ERROR FILE UTENTI NON PRESENTE.");
      return;
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_CUSTOMER . ".txt");
      $array_customer = explode("\n", $content);
      if ($this->checkInsertOrAppend(FILE_CUSTOMER) == 0)
      {
        $this->resetCustomers();
      }
      foreach ($array_customer as $single_customer)
      {
        $data = explode("§", $single_customer);
        if (isset($data[0]) and ! empty($data[0]))
        {
          if ($this->existsCustomer($data[0]) == 0)
          {
            $id_customer = $this->insertCustomer($data);
          }
          else
          {
            $id_customer = $this->existsCustomer($data[0]);
            $this->updateCustomer($data, $id_customer);
          }
          if (($array_customer_enabled == null or ! in_array($id_customer, $array_customer_enabled)) and ( $id_customer > 0))
          {
            // mando email all'utente no abilitato o non presente

            $customer = $this->getCustomerFromId($id_customer);
            if (!empty($customer->email))
            {

              $template_email = file_get_contents("conf/account_activated.html");
              $from = FROM_ACTIVATEEMAIL;
              $to = $customer->email;
              $shop_name = SHOP_NAME;
              $shop_url = SHOP_URL;
              $shop_logo = SHOP_LOGO;
              $subject = SUBJ_ACTIVATEEMAIL;
              $firstname = $customer->firstname;
              $lastname = $customer->lastname;
              $email = $customer->email;

              $template_email = str_replace("{shop_name}", $shop_name, $template_email);
              $template_email = str_replace("{shop_logo}", $shop_logo, $template_email);
              $template_email = str_replace("{shop_url}", $shop_url, $template_email);
              $template_email = str_replace("{subject}", $subject, $template_email);
              $template_email = str_replace("{firstname}", $firstname, $template_email);
              $template_email = str_replace("{lastname}", $lastname, $template_email);
              $template_email = str_replace("{email}", $email, $template_email);

              $headers = "MIME-Version: 1.0\r\n";
              $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
              $headers .= "From: $from\r\n";

              //mail($to, $subject, $template_email, $headers);
            }
          }
        }
        else
        {
          $this->fileLog("#7 ERROR UTENTE SENZA CODICE. (" . $single_customer . ")");
        }
      }
      $this->adjustCutomer();
    }
  }

  /**
   * Metodo utilizzato per inserire le anagrafiche / indirizzi degli utenti 
   * in Prestashop
   * @return type
   */
  function getAnagrafiche() {
    if (!file_exists(FOLDER_UNZIP . FILE_ANAGRAFICHE . ".txt"))
    {
      $this->fileLog("#8 ERROR FILE ANAGRAFICHE NON PRESENTE. SCRIPT TERMINATO");
      return;
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_ANAGRAFICHE . ".txt");
      $array_anagrafiche = explode("\n", $content);
      $this->resetAddressNonFatturazione();
      if ($this->checkInsertOrAppend(FILE_ANAGRAFICHE) == 0)
      {
        $this->fileLog("#8: Reset Anagrafiche");
        $this->truncateTable("ps_address");
        $this->truncateTable("ps_specific_price");
      }
      $arrDest = $this->loadRelAnagrafiche();
      foreach ($array_anagrafiche as $single_anagrafica)
      {
        $data = explode("§", $single_anagrafica);
        $this->insertAnagrafica($data, $arrDest);
      }
    }
  }

  /**
   * Metodo utilizzato per recuperare tutti gli utenti attivi in Prestashop
   * 
   * @return type
   */
  function getEnabledCustomer() {
    $sql = "SELECT * FROM ps_customer WHERE active = 1";
    $db = new MySQL();
    $db->Query($sql);
    $row = NULL;
    if ($db->RowCount() > 0)
    {
      while (!$db->EndOfSeek())
      {
        $appoRow = $db->Row();
        $row[] = $appoRow->id_customer;
      }
    }
    return $row;
  }

  /**
   * Metodo per disabilitare gli utenti presenti in Prestashop
   */
  function resetCustomers() {
    $sql = "UPDATE ps_customer SET active = 0";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Metodo utilizzato per assegnare gruppo di default Customer agli utenti caricati in
   * Prestashop
   */
  function adjustCutomer() {
    $db = new MySQL();
    $query = "INSERT INTO `ps_customer_group` (id_customer,id_group) SELECT id_customer,3 FROM ps_customer WHERE id_customer NOT IN (SELECT id_customer FROM `ps_customer_group`) ";
    $db->Query($query);
  }

  /**
   * Metodo utilizzato per verificare se un utente è già presente in Prestashop a 
   * partire dal codice ERP
   * 
   * @param String $code Codice univoco ERP
   * @return int Id dell'utente, 0 se non presente
   */
  function existsCustomer($code) {
    $query = "SELECT id_customer FROM ps_customer WHERE code = '{$code}'";

    $db = new MySQL();
    $result = $db->QuerySingleRow($query);
    if ($db->RowCount() > 0)
    {
      return $result->id_customer;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Metodo utilizzato per inserire utente all'interno di prestashop in base ai 
   * dati passati dal tracciato ERP
   * 
   * @param Array $arrCst array di dati dell'utente dal tracciato ERP
   * @return type
   */
  function insertCustomer($arrCst) {

    $values['id_shop_group'] = MySQL::SQLValue(1);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['id_gender'] = MySQL::SQLValue(1);
    $values['id_default_group'] = MySQL::SQLValue(3);
    $values['id_lang'] = MySQL::SQLValue(1);
    $values['id_risk'] = MySQL::SQLValue(0);
    if (!empty($arrCst[1]))
    {
      $values['firstname'] = MySQL::SQLValue($arrCst[1]);
    }
    else
    {
      $values['firstname'] = MySQL::SQLValue("-Senza Nome-");
    }

    $values['code'] = MySQL::SQLValue($arrCst[0]);
    $values['secure_key'] = MySQL::SQLValue(md5(uniqid(rand(), true)));
    if (!empty($arrCst[2]))
    {
      $values['lastname'] = MySQL::SQLValue($arrCst[2]);
    }
    else
    {
      $values['lastname'] = MySQL::SQLValue("-Senza Cognome-");
    }

    if (!empty($arrCst[2]))
    {
      $values['email'] = MySQL::SQLValue($arrCst[2]);
    }
    else
    {
      $this->fileLog("#7 ERROR UTENTE SENZA EMAIL. (" . $arrCst[0] . ") SKIP ADD ");
      return;
    }
    if (!empty($arrCst[3]))
    {
      $values['passwd'] = MySQL::SQLValue(md5(SALT_PASSWORD . $arrCst[3]));
    }
    else
    {
      $values['passwd'] = MySQL::SQLValue(md5(SALT_PASSWORD . "12345678"));
      //return;
    }
    $values['optin'] = MySQL::SQLValue(1);
    $values['active'] = MySQL::SQLValue(1);
    $values['erp_diff'] = MySQL::SQLValue(1);
    $now = date("Y-m-d H:i:s");
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $db = new MySQL();
    return $db->InsertRow("ps_customer", $values);
  }

  /**
   * MEtodo utilizzato per fare update di utente esistente
   * 
   * @param Array $arrCst Array utente da tracciato ERP
   * @param Int $id_customer Id utente esistente
   * @return type
   */
  function updateCustomer($arrCst, $id_customer) {

    $values['id_shop_group'] = MySQL::SQLValue(1);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['id_gender'] = MySQL::SQLValue(1);
    $values['id_default_group'] = MySQL::SQLValue(3);
    $values['id_lang'] = MySQL::SQLValue(1);
    $values['id_risk'] = MySQL::SQLValue(0);
    if (!empty($arrCst[1]))
    {
      $values['firstname'] = MySQL::SQLValue($arrCst[1]);
    }
    else
    {
      $values['firstname'] = MySQL::SQLValue("-Senza Nome-");
    }
    $values['code'] = MySQL::SQLValue($arrCst[0]);
    if (!empty($arrCst[2]))
    {
      $values['lastname'] = MySQL::SQLValue($arrCst[2]);
    }
    else
    {
      $values['lastname'] = MySQL::SQLValue("-Senza Cognome-");
    }

    if (!empty($arrCst[2]))
    {
      $values['email'] = MySQL::SQLValue($arrCst[2]);
    }
    else
    {
      $this->fileLog("#7 ERROR UTENTE SENZA EMAIL. (" . $arrCst[0] . ") SKIP ADD ");
      return;
    }

    if (!empty($arrCst[3]))
    {
      
    }
    else
    {
      $values['passwd'] = MySQL::SQLValue(md5($this->salt_passwrd . "12345678"));
    }
    $values['secure_key'] = MySQL::SQLValue(md5(uniqid(rand(), true)));
    $values['optin'] = MySQL::SQLValue(1);
    $values['active'] = MySQL::SQLValue(1);
    $values['erp_diff'] = MySQL::SQLValue(1);
    $now = date("Y-m-d H:i:s");
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $where['id_customer'] = MySQL::SQLValue($id_customer);
    $db = new MySQL();
    $db->UpdateRows("ps_customer", $values, $where);
  }

  function getCustomerFromId($id_customer) {
    $sql = "SELECT * FROM ps_customer WHERE id_customer = {$id_customer}";
    $db = new MySQL();
    return $db->QuerySingleRow($sql);
  }

  function resetAddressNonFatturazione() {
    $sql = "DELETE FROM ps_address WHERE alias != 'fatturazione'";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Metodo utilizzato per caricare tutte le anagrafiche
   * 
   * @return type Strutturra anagraficeh da traciato ERP
   * 
   */
  function loadRelAnagrafiche() {
    $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSD_ANAGRAFICHE_DESTINAZIONI.txt");
    $destinazioni = explode("\n", $content);
    foreach ($destinazioni as $dest)
    {
      $data = explode("§", $dest);
      $arrDest[trim($data[1])] = trim($data[0]);
    }
    return $arrDest;
  }

  function insertAnagrafica($anagrafica, $arrDest) {

    $db = new MySQL();
    $id_address = 0;
    $id_country = (empty($anagrafica[10])) ? $this->getIdCountry("IT") : $this->getIdCountry($anagrafica[10]);
    $values['id_country'] = MySQL::SQLValue($id_country);

    $db = new MySQL();
    $id_state = $this->getIdState($id_country, $anagrafica[9]);
    $values['id_state'] = MySQL::SQLValue($id_state);

    /*     * ********************************************************************************* */
    /* 	MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (1/2)
      /*********************************************************************************** */

    if ((empty($anagrafica[0])) || ($anagrafica[0] == null) || (strlen($anagrafica[0]) < 1))
    {
      $this->fileLog("#8 ERROR: codice ANAGRAFICA non trovato");
      return;
    }
    $values["vs_code"] = MySQL::SQLValue($anagrafica[0]);
    if (strlen($anagrafica[0]) <= 6)
    {
      $id_address = $this->checkIfAnagraficaFatturazioneEsiste($anagrafica[0]);
      // indirizzo di fatturazione!
      $values["alias"] = MySQL::SQLValue("fatturazione");
      if (isset($anagrafica[2]) and ! empty($anagrafica[2]))
      {
        $db = new MySQL();
        $id_cust = $this->getIdUserFromCode($anagrafica[2]);
        $values['id_customer'] = MySQL::SQLValue($id_cust);
      }
      else
      {
        $db = new MySQL();
        $id_cust = $this->getIdUserFromCode($anagrafica[0]);
        $values['id_customer'] = MySQL::SQLValue($id_cust);
      }
    }
    else
    {
      // indirizzo di spedizione!
      $values["alias"] = MySQL::SQLValue("spedizione " . substr($anagrafica[0], 6));
      $anagrafica[0] = substr($anagrafica[0], 0, 6);
      if (isset($anagrafica[2]) and ! empty($anagrafica[2]))
      {
        $db = new MySQL();
        $id_cust = $this->getIdUserFromCode($anagrafica[2]);
        $values['id_customer'] = MySQL::SQLValue($id_cust);
      }
      else
      {
        $db = new MySQL();
        $id_cust = $this->getIdUserFromCode($anagrafica[0]);
        $values['id_customer'] = MySQL::SQLValue($id_cust);
      }
    }

    /*     * ********************************************************************************* */
    /* 	FINE MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (1/1)
      /*********************************************************************************** */

    $values['id_manufacturer'] = MySQL::SQLValue(0);
    $values['id_supplier'] = MySQL::SQLValue(0);
    $values['id_warehouse'] = MySQL::SQLValue(0);

    /*     * ********************************************************************************* */
    /* 	MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (2/2)
      /*********************************************************************************** */
    //$values['alias'] = MySQL::SQLValue("address " . $this -> getCountAddress($id_cust));
    /*     * ********************************************************************************* */
    /* 	FINE MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (2/2)
      /*********************************************************************************** */

    $values['company'] = MySQL::SQLValue($anagrafica[3]);
    $values['lastname'] = MySQL::SQLValue($anagrafica[4] . " ");
    $values['firstname'] = MySQL::SQLValue($anagrafica[5] . " ");
    $values['address1'] = MySQL::SQLValue($anagrafica[6]);
    $values['postcode'] = MySQL::SQLValue($anagrafica[8]);
    $values['city'] = MySQL::SQLValue($anagrafica[7]);
    $values['phone'] = MySQL::SQLValue($anagrafica[11]);
    $values['phone_mobile'] = MySQL::SQLValue($anagrafica[13]);
    $values['vat_number'] = MySQL::SQLValue($anagrafica[16]);
    $values['dni'] = MySQL::SQLValue($anagrafica[17]);
    $now = date("Y-m-d H:i:s");
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);

    /*     * ****************************************************************************************
     * 	Gestione Anagrafiche bloccate (1/1)
     * **************************************************************************************** */
    $values_customer = null;
    if (($anagrafica[44] != null) && (strlen($anagrafica[44]) > 0))
    {
      $values_customer['dsblocco'] = MySQL::SQLValue($anagrafica[44]);
    }
    else
    {
      $values_customer['dsblocco'] = MySQL::SQLValue(NULL);
    }

    /*     * ****************************************************************************************
     *  Fine Gestione Anagrafiche bloccate (1/1)
     * **************************************************************************************** */

    if (($anagrafica[52] != null) && (strlen($anagrafica[52]) > 0))
    {
      $values_customer['cdpag_pre'] = MySQL::SQLValue($anagrafica[52]);
    }
    else
    {
      $values_customer['cdpag_pre'] = MySQL::SQLValue(NULL);
    }

    /*     * ****************************************************************************************
     * 	Gestione Spese di spedizione (1/1)
     * **************************************************************************************** */

    if ((!empty($anagrafica[46])) && (strlen($anagrafica[46] > 0)))
    {
      $values_customer["vs_fgspesesped"] = MySQL::SQLValue($anagrafica[46]);
    }
    else
    {
      $values_customer["vs_fgspesesped"] = MySQL::SQLValue($anagrafica[46]);
    }

    if (!empty($anagrafica[47]))
    {
      $values_customer["vs_nrsogliaspesesped"] = MySQL::SQLValue($anagrafica[47], "float");
    }
    else
    {
      $values_customer["vs_nrsogliaspesesped"] = MySQL::SQLValue(0, "float");
    }

    if (!empty($anagrafica[48]))
    {
      $values_customer["vs_nrcostofissospesesped"] = MySQL::SQLValue($anagrafica[48], "float");
    }
    else
    {
      $values_customer["vs_nrcostofissospesesped"] = MySQL::SQLValue(0, "float");
    }

    if (!empty($anagrafica[49]))
    {
      $values_customer["vs_nrcostopercspesesped"] = MySQL::SQLValue($anagrafica[49], "float");
    }
    else
    {
      $values_customer["vs_nrcostopercspesesped"] = MySQL::SQLValue(0, "float");
    }

    /*     * ****************************************************************************************
     * 	Fine Gestione Spese di spedizione (1/1)
     * **************************************************************************************** */

    /*     * ****************************************************************************************
     * 	Modalit� Pagamento (2/4)
     * **************************************************************************************** */

    if (($anagrafica[52] != null) && (strlen($anagrafica[52]) > 0))
    {
      $values_customer['cdpag_pre'] = MySQL::SQLValue($anagrafica[52]);
    }
    else
    {
      $values_customer['cdpag_pre'] = MySQL::SQLValue(NULL);
    }

    /*     * ****************************************************************************************
     * 	Fine Modalit� Pagamento (2/4)
     * **************************************************************************************** */


    /*     * ****************************************************************************************
     *  campo cat_ute (per area download) (1/1)
     * **************************************************************************************** */

    // CDcatCliente char(50)  no  Codice categoria del cliente  posizione 40 (partendo da 0)
    if (!is_null($anagrafica[40]))
      $values_customer['vs_cat_ute'] = MySQL::SQLValue($anagrafica[40]);

    /*     * ****************************************************************************************
     *  fine campo cat_ute (per area download) (1/1)
     * **************************************************************************************** */

    $where_blocco['id_customer'] = MySQL::SQLValue($id_cust);
    $db = new MySQL();
    $db->UpdateRows("ps_customer", $values_customer, $where_blocco);

    $db2 = new MySQL();
    if ($id_address == 0)
    {
      $db2->InsertRow("ps_address", $values);
    }
    else
    {
      $where["id_address"] = MySQL::SQLValue($id_address);
      $db2->UpdateRows("ps_address", $values, $where);
    }

    $this->refreshCustomer($id_cust, $anagrafica[5], $anagrafica[4], $anagrafica[3], $anagrafica[42], $anagrafica[43]);
  }

  function getIdCountry($country) {
    $db = new MySQL();
    $sql = "SELECT id_country FROM ps_country WHERE iso_code = '{$country}'";
    $result = $db->QuerySingleRow($sql);
    return $result->id_country;
  }

  function getIdState($id_country, $state) {
    $db = new MySQL();
    $sql = "SELECT id_state FROM ps_state WHERE iso_code = '{$state}' AND id_country = " . $id_country;
    //echo $sql;
    $result = $db->QuerySingleRow($sql);
    return $result->id_state;
  }

  function checkIfAnagraficaFatturazioneEsiste($vs_code) {
    $sql = "SELECT * FROM ps_address WHERE vs_code = '{$vs_code}' AND alias = 'fatturazione'";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->id_address;
    }
    else
    {
      return 0;
    }
  }

  function getIdUserFromCode($code) {
    $db = new MySQL();
    $sql = "SELECT id_customer FROM ps_customer WHERE code = '{$code}'";

    $result = $db->QuerySingleRow($sql);
    return $result->id_customer;
  }

  function refreshCustomer($id_cust, $firstname, $lastname, $company, $fido, $utilizzo) {
    $values = null;
    if (!empty($firstname))
    {
      $values['firstname'] = MySQL::SQLValue($firstname);
    }
    else
    {
      $values['firstname'] = MySQL::SQLValue("-Senza Nome-");
    }
    if (!empty($lastname))
    {
      $values['lastname'] = MySQL::SQLValue($lastname);
    }
    else
    {
      $values['lastname'] = MySQL::SQLValue(htmlentities("-Senza Cognome-"));
    }

    if (!empty($company))
    {
      $values['company'] = MySQL::SQLValue($company);
    }
    else
    {
      $values['company'] = MySQL::SQLValue(htmlentities("-Senza Ragione Sociale-"));
    }
    if (!empty($utilizzo))
    {
      $values['outstanding_allow_amount'] = MySQL::SQLValue(str_replace(",", ".", $fido) - str_replace(",", ".", $utilizzo));
    }
    else
    {
      $values['outstanding_allow_amount'] = MySQL::SQLValue(str_replace(",", ".", $fido));
    }

    $where['id_customer'] = MySQL::SQLValue($id_cust);
    $db = new MySQL();
    $db->UpdateRows("ps_customer", $values, $where);
  }

}
