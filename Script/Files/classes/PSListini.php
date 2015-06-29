<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

define("FILE_CUSTOMER", "TSD_UTENTI");

define("FILE_ANAGRAFICHE", "TSD_ANAGRAFICHE");

define("FILE_PRODUCT", "TPD_PRODOTTI");

define("FILE_LISTINI_BASE", "TPD_LISTINI_BASE");

define("FILE_LISTINI", "TPD_LISTINI");

define("FILE_SCONTI_QT", "TSD_SCONTI_SCAGLIONE");

define("LIST_BASE_NUMBER", 2);

define("PROPAPAGAZIONE_LISTINI_CLIENTI", 0);

/**
 * Description of PSListini
 *
 * @author ALLINITSRLS
 */
class PSListini extends PSObject {

  function insertGestioneListini() {
    $this->truncateTable("ps_specific_price");
    $this->truncateTable("erp_sconti_scaglione");
    $this->mappingUtentiCodeId();
    $this->mappingProdottiCodeId();
    $this->deleteOldGroup();

    $content_catClienti = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSD_REL_UTE_ANAG_CATEG.txt");
    $array_CatClienti = explode("\n", $content_catClienti);
    $erpCatClienti = array();
    foreach ($array_CatClienti as $single_catCliente)
    {
      $data = explode("§", $single_catCliente);
      $erpCatClienti[trim($data[1])][] = $data[0];
    }
    $this->ERP_CATCUSTOMER = $erpCatClienti;

    $this->fileLog("#8b recupero informazioni CLIENTI");
    $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_CUSTOMER . ".txt");
    $array_customer = explode("\n", $content);
    foreach ($array_customer as $single_customer)
    {
      $data = explode("§", $single_customer);
      unset($erpCtr);
      $erpCtr = new \stdClass();
      //$erpCtr = new ERP_TSD_UTENTI();
      $erpCtr->_0_Cdute = $data[0];
      if ($data[0] == "CCORRI")
      {
        continue;
      }
      if (isset($this->TSD_ANAGRAFICHE[$erpCtr->_0_Cdute]))
      {
        $erpCtr->_ANAGRAFICA = $this->TSD_ANAGRAFICHE[$erpCtr->_0_Cdute];
      }
      $this->TSD_UTENTI[trim($erpCtr->_0_Cdute)] = $erpCtr;
    }


    $this->fileLog("#8b recupero informazioni ANAGRAFICHE");
    $content_anagrafiche = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_ANAGRAFICHE . ".txt");
    $array_anagrafiche = explode("\n", $content_anagrafiche);
    foreach ($array_anagrafiche as $single_anagrafica)
    {
      $data = explode("§", $single_anagrafica);
      unset($erpAnag);
      $erpAnag = new \stdClass();
      // controllo ESISTENZA cliente CCORRI per il gruppo VISITOR
      if ($data[0] == "CCORRI" && !empty($data[45]))
      {
        $this->VISITOR = $data[45];
        continue;
      }
      $erpAnag->_0_CDanag = $data[0];
      if (empty($data[39]))
      {
        $data[39] = 0;
      }
      $erpAnag->_39_Dssconto = $data[39];
      $erpAnag->_45_Cdlistino = $data[45];
      $this->TSD_ANAGRAFICHE[trim($erpAnag->_0_CDanag)] = $erpAnag;
      if (!empty($data[45]))
      {
        $this->_MAPPING_ANAG_IDLISTINO[$erpAnag->_0_CDanag] = $data[45];
      }
      if (!empty($erpAnag->_45_Cdlistino) && is_numeric($erpAnag->_45_Cdlistino))
      {

        if (($erpAnag->_45_Cdlistino != LIST_BASE_NUMBER) or ! empty($erpAnag->_39_Dssconto))
        {
          $this->_GROUP_MISTO_PER_LISTINI[$erpAnag->_45_Cdlistino][$erpAnag->_39_Dssconto]['customers'][] = $erpAnag->_0_CDanag;
          if (!isset($this->_GROUP_MISTO_PER_LISTINI[$erpAnag->_45_Cdlistino][$erpAnag->_39_Dssconto]['id_group']))
          {
            $this->_GROUP_MISTO_PER_LISTINI[$erpAnag->_45_Cdlistino][$erpAnag->_39_Dssconto]['id_group'] = $this->createListiniBaseGroup($erpAnag->_45_Cdlistino . "_ " . $erpAnag->_39_Dssconto);
            $this->_ARRAY_GROUP_LISTINI[] = $this->_GROUP_MISTO_PER_LISTINI[$erpAnag->_45_Cdlistino][$erpAnag->_39_Dssconto]['id_group'];
          }
          $this->insertCustomerGroup($this->getCustomerIdFromCode($erpAnag->_0_CDanag), $this->_GROUP_MISTO_PER_LISTINI[$erpAnag->_45_Cdlistino][$erpAnag->_39_Dssconto]['id_group']);
          $this->_APPO_LIST_BASE[$erpAnag->_0_CDanag] = $erpAnag->_45_Cdlistino . "-" . $erpAnag->_39_Dssconto;
        }
      }
    }

    $this->fileLog("#8b recupero informazioni PRODOTTI");
    $content_prodotti = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_PRODUCT . ".txt");
    $array_prodotti = explode("\n", $content_prodotti);
    foreach ($array_prodotti as $single_product)
    {
      $data = explode("§", $single_product);
      unset($erpPrd);
      $erpPrd = new \stdClass();
      //$erpPrd = new ERP_TPD_PRODOTTI();
      $erpPrd->_0_Cdprodotto = $data[0];
      if (empty($data[42]))
      {
        $data[42] = 0;
      }
      $erpPrd->_42_Dssconto = str_replace(",", ".", $data[42]);

      $this->TPD_PRODOTTI[trim($erpPrd->_0_Cdprodotto)] = $erpPrd;
    }

    ## PRE MODIFICA PER CARICARE NEL DB I LISTINI BASE

    $this->fileLog("#8b recupero informazioni LISTINI BASE");
    $content_listini_base = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_LISTINI_BASE . ".txt");
    $array_listini_base = explode("\n", $content_listini_base);
    $count_listin_grp = 0;
    foreach ($array_listini_base as $single_listino_base)
    {
      $passed = false;
      $passed_cust = false;
      $data = explode("§", $single_listino_base);
      unset($erpListBase);
      if (isset($this->_GROUP_MISTO_PER_LISTINI[$data[0]]))
      {
        foreach ($this->_GROUP_MISTO_PER_LISTINI[$data[0]] as $key => $value)
        {
          $count_listin_grp++;
          $erpListBase = new \stdClass();
          $specific_price = new \stdClass();
          $erpListBase->_0_Idlistino = $data[0];
          $erpListBase->_1_Cdprodotto = $data[1];
          $erpListBase->_2_CDvaluta = $data[2];
          $erpListBase->_3_NRprezzo = str_replace(",", ".", $data[3]);
          if (empty($data[4]))
          {
            $data[4] = 0;
          }
          $erpListBase->_4_DSsconto = str_replace(",", ".", $data[4]);
          $erpListBase->_5_CDlistino = $data[5];
          $infoReduction = explode(";", $this->calculateREduction(array($this->TPD_PRODOTTI[$erpListBase->_1_Cdprodotto]->_42_Dssconto, $key, $erpListBase->_4_DSsconto)));
          // Dato il listino base il prezo può essere determinato da
          $specific_price->id_product = $erpListBase->_1_Cdprodotto;
          $specific_price->id_customer = 0;
          $specific_price->price = $erpListBase->_3_NRprezzo;
          $specific_price->from_quantity = 1;
          $specific_price->reduction = $infoReduction[0];
          if ($specific_price->reduction == 1)
          {
            $specific_price->reduction = 0;
          }
          $specific_price->reduction_type = "percentage";
          if ($specific_price->reduction == 0)
          {
            $specific_price->reduction_type = "amount";
          }

          $specific_price->from = "0000-00-00 00:00:00";
          $specific_price->to = "0000-00-00 00:00:00";
          $specific_price->stringSconto = $infoReduction[1];
          $specific_price->type = "12_" . $erpListBase->_1_Cdprodotto . "-" . $erpListBase->_0_Idlistino . "-" . $key;
          $specific_price->id = $erpListBase->_1_Cdprodotto . "-" . $erpListBase->_0_Idlistino . "-" . $key;
          if (!empty($specific_price->price) || !empty($specific_price->reduction))
          {
            $this->insertListiniBaseGroups($specific_price, $this->getIdProductFromCode($erpListBase->_1_Cdprodotto), $this->_GROUP_MISTO_PER_LISTINI[$data[0]][$key]['id_group']);
            if (($data[0] == $this->VISITOR) and ( $passed == false))
            {
              $passed = true;
              #$this -> fileLog(" ---- dentro per i VISITOR");
              $this->insertListiniBaseGroups($specific_price, $this->getIdProductFromCode($erpListBase->_1_Cdprodotto), 1);
            }
            if (($data[0] == LIST_BASE_NUMBER) and ( $passed_cust == false))
            {
              $passed_cust = true;
            }
          }
          unset($specific_price);
          $specific_price = null;
        }
      }
    }

    ## END PRE MODIFICA PER CARICARE NEL DB I LISTINI BASE

    $this->fileLog("#8b recupero informazioni SCONTI QUANTITA");
    $content_scaglione = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_SCONTI_QT . ".txt");
    $array_scaglione = explode("\n", $content_scaglione);
    $array_generico_scaglione = null;

    foreach ($array_scaglione as $single_scaglione)
    {
      $data = explode("§", $single_scaglione);
      unset($erpScaglione);
      $erpScaglione = new \stdClass();
      $specific_price = new \stdClass();
      //$erpScaglione = new ERP_TSD_SCONTI_SCAGLIONE();
      $erpScaglione->_0_Id = $data[0];
      $erpScaglione->_1_CDprodotto = $data[1];
      $erpScaglione->_2_NRqtaLimite = $data[2];
      $erpScaglione->_3_CDanag = $data[3];
      if (!empty($data[4]))
      {
        $erpScaglione->_4_NRsconto = str_replace(",", ".", trim($data[4]));
      }
      else
      {
        $erpScaglione->_4_NRsconto = 0;
      }
      if (isset($data[5]))
      {
        $erpScaglione->_5_Cdcatart = $data[5];
      }
      else
      {
        $erpScaglione->_5_Cdcatart = 0;
      }
      if (isset($data[6]))
      {
        $erpScaglione->_6_Cdcatcli = $data[6];
      }
      else
      {
        $erpScaglione->_6_Cdcatcli = 0;
      }
      if (isset($data[7]))
      {
        $erpScaglione->_7_Nrprezzo = str_replace(",", ".", $data[7]);
      }
      else
      {
        $erpScaglione->_7_Nrprezzo = 0;
      }
      if (isset($data[8]))
      {
        $erpScaglione->_8_Dtinizio = $data[8];
      }
      else
      {
        $erpScaglione->_8_Dtinizio = "19000101";
      }
      if (isset($data[9]))
      {
        $erpScaglione->_9_Dtfine = trim($data[9]);
      }
      else
      {
        $erpScaglione->_9_Dtfine = "20501231";
      }

      $now_date_select = date("Ymd");
      if ($erpScaglione->_9_Dtfine >= $now_date_select)
      {
        $this->insertErpScontiScaglione($erpScaglione);
      }
    }

    $this->fileLog("#8b recupero informazioni PREZZI SPECIALI");
    $content_speciali = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_LISTINI . ".txt");
    $array_speciali = explode("\n", $content_speciali);
    foreach ($array_speciali as $single_row_speciale)
    {
      $data = explode("§", $single_row_speciale);
      unset($erpPrezziSpeciali);
      $erpPrezziSpeciali = new \stdClass();
      //$erpPrezziSpeciali = new ERP_TPD_LISTINI();
      $erpPrezziSpeciali->_0_Idlistino = $data[0];
      $erpPrezziSpeciali->_1_Cdprodotto = $data[1];
      $erpPrezziSpeciali->_2_Cdanag = $data[2];
      $erpPrezziSpeciali->_3_Cdcategoria = $data[3];
      $erpPrezziSpeciali->_4_CDcatCliente = $data[4];
      $erpPrezziSpeciali->_5_Nrprezzo = str_replace(",", ".", $data[5]);
      $erpPrezziSpeciali->_6_Dssconto = str_replace(",", ".", $data[6]);
      $erpPrezziSpeciali->_7_Dtinizio = $data[7];
      $erpPrezziSpeciali->_8_Dtfine = trim($data[8]);
      $erpPrezziSpeciali->_9_Quantita = trim($data[10]);

      $this->insertErpPrezziSpeciali($erpPrezziSpeciali);
    }


    $this->startFillSpecificPrice();

    /*     * ** SISTEMO GLI SCONTI PER TUTTI I PRODOTTI CHE HANNO ATTRIBUTI *** */
    # CONTROLLO CHE LE TAGLIE SONO ABILITATE
    if (ENABLE_TAGLIE)
    {
      # recupero tutti i prodotti presenti in ps_product_attribute
      $array_obj_prodCombination = $this->getAllProductForTaglie();
      foreach ($array_obj_prodCombination as $single_prdCombination)
      {
        # per ogni prodotto recupero tutti gli sconti applicati
        $array_sconti_profCombination = $this->getProductPsSpecificPrice($single_prdCombination->id_product);

        foreach ($array_sconti_profCombination as $single_psPriceCombination)
        {
          # per ogni singolo sconto inserisco un nuova riga con id_attribute settato
          # modifica id_product_attribute
          $single_psPriceCombination->id_product_attribute = $single_prdCombination->id_product_attribute;

          # inserisco la nuova regola
          $this->insertCombinationSpecificPrice($single_psPriceCombination);
        }
      }
    }
    /*     * *** FINE MODIFICA PER ALLINEARE GLI SCONTI PER I PRODOTTI CON COMBINAZIONI **** */
  }

  function mappingUtentiCodeId() {
    $sql = "SELECT id_customer, code FROM ps_customer";
    $db = new MySQL();
    $db->Query($sql);
    $this->_MAPPING_UTENTI_CODE_ID = NULL;
    if ($db->RowCount() > 0)
    {
      while (!$db->EndOfSeek())
      {
        $appoRow = $db->Row();
        $this->_MAPPING_UTENTI_CODE_ID[$appoRow->code] = $appoRow->id_customer;
      }
    }
  }

  function mappingProdottiCodeId() {
    $sql = "SELECT id_product, code FROM ps_product";
    $db = new MySQL();
    $db->Query($sql);
    $this->_MAPPING_PRODOTTI_CODE_ID = NULL;
    if ($db->RowCount() > 0)
    {

      while (!$db->EndOfSeek())
      {
        $appoRow = $db->Row();
        $this->_MAPPING_PRODOTTI_CODE_ID[$appoRow->code] = $appoRow->id_product;
      }
    }
  }

  function deleteOldGroup() {
    $sql = "DELETE FROM ps_group WHERE id_group > 3";
    $db = new MySQL();
    $db->Query($sql);
    $sql_ai = "ALTER TABLE ps_group AUTO_INCREMENT=4";
    $db->Query($sql_ai);
    $sql_l = "DELETE FROM ps_group_lang WHERE id_group > 3";
    $db->Query($sql_l);
    $sql_ai_l = "ALTER TABLE ps_group_lang AUTO_INCREMENT=4";
    $db->Query($sql_ai_l);
    $sql_s = "DELETE FROM ps_group_shop WHERE id_group > 3";
    $db->Query($sql_s);
    $sql_ai_s = "DELETE FROM ps_group_shop AUTO_INCREMENT=4";
    $db->Query($sql_ai_s);
    $sql_grp_cust = "DELETE FROM ps_customer_group WHERE id_group > 3";
    $db->Query($sql_grp_cust);
  }

  function createListiniBaseGroup($id_listino) {
    $values = null;
    $values['reduction'] = MySQL::SQLValue(0);
    $values['price_display_method'] = MySQL::SQLValue(1);
    $values['show_prices'] = MySQL::SQLValue(1);
    $now = date("Y-m-d H:i:s");
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $db = new MySQL();
    $id_group = $db->InsertRow("ps_group", $values);
    $values_lang = null;
    $values_lang['id_group'] = MySQL::SQLValue($id_group);
    $values_lang['id_lang'] = MySQL::SQLValue(1);
    $values_lang['name'] = MySQL::SQLValue("LISTINO_BASE_" . $id_listino);
    $db->InsertRow("ps_group_lang", $values_lang);
    $values_shop = null;
    $values_shop['id_group'] = MySQL::SQLValue($id_group);
    $values_shop['id_shop'] = MySQL::SQLValue(1);
    $db->InsertRow("ps_group_shop", $values_shop);
    return $id_group;
  }

  function getCustomerIdFromCode($code) {
    if (isset($this->_MAPPING_UTENTI_CODE_ID[trim($code)]))
    {
      return $this->_MAPPING_UTENTI_CODE_ID[trim($code)];
    }
    else
    {
      return null;
    }
    $code = trim($code);
    $sql = "SELECT id_customer FROM ps_customer WHERE code = '{$code}'";
    //$this -> fileLog($sql);
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    return $result->id_customer;
  }

  function calculateREduction($array_sconti) {
    $reduction = 1;
    $stringa_sconto = "";
    foreach ($array_sconti as $singolo_sconto)
    {
      $pos = strpos($singolo_sconto, "+");
      if ($pos !== FALSE)
      {
        // c'è il +
        $array_sconti_plus = explode("+", $singolo_sconto);
        for ($i = 0; $i < count($array_sconti_plus); $i++)
        {
          $reduction = $reduction * (1 - ($array_sconti_plus[$i] / 100));
          if (strlen($stringa_sconto) > 0)
          {
            $stringa_sconto = $stringa_sconto . "+" . $array_sconti_plus[$i];
          }
          else
          {
            $stringa_sconto = $array_sconti_plus[$i];
          }
        }
      }
      else
      {
        if (!empty($singolo_sconto) and $singolo_sconto > 0)
        {
          $reduction = $reduction * (1 - ($singolo_sconto / 100));
          if (strlen($stringa_sconto) > 0)
          {
            $stringa_sconto = $stringa_sconto . "+" . $singolo_sconto;
          }
          else
          {
            $stringa_sconto = $singolo_sconto;
          }
        }
      }
    }
    $reduction = (1 - $reduction);
    return $reduction . ";" . $stringa_sconto;
  }

  function insertListiniBaseGroups($specific_prezzi_speciali, $id_product, $id_group) {
    $values = null;
    $values['id_specific_price_rule'] = MySQL::SQLValue(0);
    $values['id_cart'] = MySQL::SQLValue(0);
    $values['id_product'] = MySQL::SQLValue($id_product);

    $values['id_shop'] = MySQL::SQLValue(0);
    $values['id_shop_group'] = MySQL::SQLValue(0);
    $values['id_currency'] = MySQL::SQLValue(0);
    $values['id_country'] = MySQL::SQLValue(0);
    $values['id_group'] = MySQL::SQLValue($id_group);
    $values['id_customer'] = MySQL::SQLValue(0);
    $values['price'] = MySQL::SQLValue($specific_prezzi_speciali->price);

    $values['from_quantity'] = MySQL::SQLValue(1);
    $values['reduction'] = MySQL::SQLValue($specific_prezzi_speciali->reduction);
    $values['reduction_type'] = MySQL::SQLValue($specific_prezzi_speciali->reduction_type);
    $values['from'] = MySQL::SQLValue("0000-00-00 00:00:00");
    $values['to'] = MySQL::SQLValue("0000-00-00 00:00:00");
    $values['type'] = MySQL::SQLValue("LSTBASE");
    $values['stringSconto'] = MySQL::SQLValue($specific_prezzi_speciali->stringSconto);

    $db = new MySQL();
    $last_id = $db->InsertRow("ps_specific_price", $values);
  }

  function getIdProductFromCode($code) {
    if (isset($this->_MAPPING_PRODOTTI_CODE_ID[$code]))
    {
      return $this->_MAPPING_PRODOTTI_CODE_ID[$code];
    }
    else
    {
      return null;
    }
    $db = new MySQL();
    $sql = "SELECT id_product FROM ps_product WHERE code = '{$code}'";

    $result = $db->QuerySingleRow($sql);
    return $result->id_product;
  }

  function insertErpScontiScaglione($sconto_scaglione) {
    $values = null;
    $values["id"] = MySQL::SQLValue($sconto_scaglione->_0_Id);
    $values["CDprodotto"] = MySQL::SQLValue($sconto_scaglione->_1_CDprodotto);
    $values["NRqtaLimite"] = MySQL::SQLValue($sconto_scaglione->_2_NRqtaLimite);
    $values["CDanag"] = MySQL::SQLValue($sconto_scaglione->_3_CDanag);
    $values["NRsconto"] = MySQL::SQLValue($sconto_scaglione->_4_NRsconto);
    $values["Cdcatart"] = MySQL::SQLValue($sconto_scaglione->_5_Cdcatart);
    $values["Cdcatcli"] = MySQL::SQLValue($sconto_scaglione->_6_Cdcatcli);
    $values["Nrprezzo"] = MySQL::SQLValue($sconto_scaglione->_7_Nrprezzo);
    $values["Dtinizio"] = MySQL::SQLValue(substr($sconto_scaglione->_8_Dtinizio, 0, 4) . "-" . substr($sconto_scaglione->_8_Dtinizio, 4, 2) . "-" . substr($sconto_scaglione->_8_Dtinizio, 6, 2) . " 00:00:00");
    $values["Dtfine"] = MySQL::SQLValue(substr($sconto_scaglione->_9_Dtfine, 0, 4) . "-" . substr($sconto_scaglione->_9_Dtfine, 4, 2) . "-" . substr($sconto_scaglione->_9_Dtfine, 6, 2) . " 23:59:59");
    if (!empty($sconto_scaglione->_1_CDprodotto) and ! empty($sconto_scaglione->_3_CDanag))
    {
      $values["priority"] = MySQL::SQLValue(7);
    }
    else if (!empty($sconto_scaglione->_1_CDprodotto) and ! empty($sconto_scaglione->_6_Cdcatcli))
    {
      $values["priority"] = MySQL::SQLValue(8);
    }
    else if (!empty($sconto_scaglione->_5_Cdcatart) and ! empty($sconto_scaglione->_3_CDanag))
    {
      $values["priority"] = MySQL::SQLValue(9);
    }
    else if (!empty($sconto_scaglione->_5_Cdcatart) and ! empty($sconto_scaglione->_6_Cdcatcli))
    {
      $values["priority"] = MySQL::SQLValue(10);
    }
    else if (empty($sconto_scaglione->_3_CDanag) and empty($sconto_scaglione->_6_Cdcatcli))
    {
      $values["priority"] = MySQL::SQLValue(11);
    }
    $db = new MySQL();
    $db->InsertRow("erp_sconti_scaglione", $values);
  }

  function insertErpPrezziSpeciali($prezzi_special) {
    $values = null;
    $values["id"] = MySQL::SQLValue($prezzi_special->_0_Idlistino);
    $values["CDprodotto"] = MySQL::SQLValue($prezzi_special->_1_Cdprodotto);
    $values["NRqtaLimite"] = MySQL::SQLValue($prezzi_special->_9_Quantita);
    $values["CDanag"] = MySQL::SQLValue($prezzi_special->_2_Cdanag);
    if ($prezzi_special->_6_Dssconto == "")
    {
      $prezzi_special->_6_Dssconto = 0;
    }
    $values["NRsconto"] = MySQL::SQLValue($prezzi_special->_6_Dssconto);
    $values["Cdcatart"] = MySQL::SQLValue($prezzi_special->_3_Cdcategoria);
    $values["Cdcatcli"] = MySQL::SQLValue($prezzi_special->_4_CDcatCliente);
    $values["Nrprezzo"] = MySQL::SQLValue($prezzi_special->_5_Nrprezzo);
    $values["Dtinizio"] = MySQL::SQLValue(substr($prezzi_special->_7_Dtinizio, 0, 4) . "-" . substr($prezzi_special->_7_Dtinizio, 4, 2) . "-" . substr($prezzi_special->_7_Dtinizio, 6, 2) . " 00:00:00");
    $values["Dtfine"] = MySQL::SQLValue(substr($prezzi_special->_8_Dtfine, 0, 4) . "-" . substr($prezzi_special->_8_Dtfine, 4, 2) . "-" . substr($prezzi_special->_8_Dtfine, 6, 2) . " 23:59:59");
    $fine = substr($prezzi_special->_8_Dtfine, 0, 4) . "-" . substr($prezzi_special->_8_Dtfine, 4, 2) . "-" . substr($prezzi_special->_8_Dtfine, 6, 2) . " 23:59:59";
    $today = date("Y-m-d H:i:s");

    if ($fine > $today)
    {
      $priority = 0;
      if (!empty($prezzi_special->_1_Cdprodotto) and ! empty($prezzi_special->_2_Cdanag))
      {
        $values["priority"] = MySQL::SQLValue(1);
        $priority = 1;
      }
      else if (!empty($prezzi_special->_1_Cdprodotto) and ! empty($prezzi_special->_4_CDcatCliente))
      {
        $values["priority"] = MySQL::SQLValue(2);
        $priority = 2;
      }
      else if (!empty($prezzi_special->_3_Cdcategoria) and ! empty($prezzi_special->_2_Cdanag))
      {
        $values["priority"] = MySQL::SQLValue(3);
        $priority = 3;
      }
      else if (!empty($prezzi_special->_3_Cdcategoria) and ! empty($prezzi_special->_4_CDcatCliente))
      {
        $values["priority"] = MySQL::SQLValue(4);
        $priority = 4;
      }
      else if (empty($prezzi_special->_2_Cdanag) and empty($prezzi_special->_4_CDcatCliente))
      {
        $values["priority"] = MySQL::SQLValue(5);
        $priority = 5;
      }
      if ($priority > 0)
      {
        $db = new MySQL();
        $db->InsertRow("erp_sconti_scaglione", $values);
      }
      # faccio il controllo se c'Ã¨ uno scanto scaglione per tutti con qt > qt prezo speciale
      $this->checkScontoScaglioneQt($prezzi_special->_1_Cdprodotto, $prezzi_special->_9_Quantita, $prezzi_special);
    }
  }

  function checkScontoScaglioneQt($CDProdotto, $NRqtaLimite, $prezzi_special) {

    $sql = "SELECT * FROM erp_sconti_scaglione WHERE CDprodotto = '{$CDProdotto}' AND NRqtaLimite > {$NRqtaLimite} AND priority > 6";

    $db = new MySQL();
    $db->Query($sql);
    $row = NULL;
    if ($db->RowCount() > 0)
    {

      while (!$db->EndOfSeek())
      {

        $row[] = $db->Row();
      }
    }
    if (!empty($row))
    {
      foreach ($row as $sconto_scaglione_sup)
      {
        $prezzi_special->_9_Quantita = $sconto_scaglione_sup->NRqtaLimite;
        $this->insertErpPrezziSpeciali($prezzi_special);
      }
    }
  }

  function getAllProductForTaglie() {
    $sql = "SELECT id_product_attribute, id_product FROM ps_product_attribute";
    $db = new MySQL();
    $db->Query($sql);
    $row = NULL;
    if ($db->RowCount() > 0)
    {
      while (!$db->EndOfSeek())
      {
        $row[] = $db->Row();
      }
    }
    return $row;
  }

  function getProductPsSpecificPrice($id_product) {
    $sql = "SELECT * FROM ps_specific_price WHERE id_product = {$id_product} AND id_product_attribute = 0";
    $db = new MySQL();
    $db->Query($sql);
    $row = NULL;
    if ($db->RowCount() > 0)
    {
      while (!$db->EndOfSeek())
      {
        $row[] = $db->Row();
      }
    }
    return $row;
  }

  function insertCombinationSpecificPrice($specific_price) {
    $values = null;
    $values['id_specific_price_rule'] = MySQL::SQLValue($specific_price->id_specific_price_rule);
    $values['id_cart'] = MySQL::SQLValue($specific_price->id_cart);
    $values['id_product'] = MySQL::SQLValue($specific_price->id_product);

    $values['id_shop'] = MySQL::SQLValue($specific_price->id_shop);
    $values['id_shop_group'] = MySQL::SQLValue($specific_price->id_shop_group);
    $values['id_currency'] = MySQL::SQLValue($specific_price->id_currency);
    $values['id_country'] = MySQL::SQLValue($specific_price->id_country);
    $values['id_group'] = MySQL::SQLValue($specific_price->id_group);
    $values['id_customer'] = MySQL::SQLValue($specific_price->id_customer);
    $values['id_product_attribute'] = MySQL::SQLValue($specific_price->id_product_attribute);
    $values['price'] = MySQL::SQLValue($specific_price->price);

    $values['from_quantity'] = MySQL::SQLValue($specific_price->from_quantity);
    $values['reduction'] = MySQL::SQLValue($specific_price->reduction);
    $values['reduction_type'] = MySQL::SQLValue($specific_price->reduction_type);
    $values['from'] = MySQL::SQLValue($specific_price->from);
    $values['to'] = MySQL::SQLValue($specific_price->to);
    $values['type'] = MySQL::SQLValue($specific_price->type);
    $values['stringSconto'] = MySQL::SQLValue($specific_price->stringSconto);

    $db = new MySQL();
    $last_id = $db->InsertRow("ps_specific_price", $values);
  }

  function startFillSpecificPrice() {
    $sql = "SELECT * FROM erp_sconti_scaglione ORDER BY priority ASC";
    $db = new MySQL();
    $db->Query($sql);
    $row = NULL;
    if ($db->RowCount() > 0)
    {

      while (!$db->EndOfSeek())
      {

        $row[] = $db->Row();
      }
    }
    $this->_GLOBAL_COUNTER = 0;
    foreach ($row as $sconto_erp)
    {
      $specific_price = new \stdClass();
      $specific_price->priority = $sconto_erp->priority;
      if (!empty($sconto_erp->CDprodotto) and ! empty($sconto_erp->CDanag))
      {

        $infoReduction = explode(";", $this->calculateREduction(array($sconto_erp->NRsconto)));
        $cdprodotto = $sconto_erp->CDprodotto;
        $cdanag = $sconto_erp->CDanag;
        $specific_price->price = $sconto_erp->Nrprezzo;
        $specific_price->from_quantity = $sconto_erp->NRqtaLimite;
        $specific_price->reduction = $infoReduction[0];
        if ($specific_price->reduction == 1)
        {
          $specific_price->reduction = 0;
        }
        $specific_price->reduction_type = "percentage";
        if ($specific_price->reduction == 0)
        {
          $specific_price->reduction_type = "amount";
        }
        $specific_price->from = $sconto_erp->Dtinizio;
        $specific_price->to = $sconto_erp->Dtfine;
        $specific_price->stringSconto = $infoReduction[1];
        $specific_price->type = $sconto_erp->priority . "_" . $sconto_erp->id;

        $id_product = $this->getIdProductFromCode($cdprodotto);
        $id_customer = $this->getCustomerIdFromCode($cdanag);
        if ($sconto_erp->Nrprezzo == 0.00000)
        {
          if (PROPAPAGAZIONE_LISTINI_CLIENTI == 1 && isset($this->_APP1O_LIST_BASE[$cdanag]))
          {
            $appo_split = explode("_", $this->_APPO_LIST_BASE[$cdanag]);
            $id_listino = $appo_split[0];
            $list_sconto = $appo_split[1];
            $specific_price->price = $this->_GROUP_MISTO_PER_LISTINI[$id_listino][$list_sconto]['specific_price'][$cdprodotto]->price;
          }
          else
          {
            $specific_price->price = -1;
          }
        }
        $id_group = 0;
        if ($cdanag == "CCORRI")
        {
          $id_customer = null;
          $id_group = 1;
        }
        if ($id_product != null and $id_group == 1)
        {
          $this->checkIfExistsHighPriceRuleCCORRI($specific_price, $id_product);
        }
        if ($id_customer != null and $id_product != null)
        {
          $this->checkIfExistsHighPriceRule($specific_price, $id_product, $id_customer);
        }
      }
      else if (!empty($sconto_erp->CDprodotto) and ! empty($sconto_erp->Cdcatcli))
      {
        foreach ($this->ERP_CATCUSTOMER[$sconto_erp->Cdcatcli] as $cdanag)
        {

          $infoReduction = explode(";", $this->calculateREduction(array($sconto_erp->NRsconto)));
          $cdprodotto = $sconto_erp->CDprodotto;
          $specific_price->price = $sconto_erp->Nrprezzo;
          $specific_price->from_quantity = $sconto_erp->NRqtaLimite;
          $specific_price->reduction = $infoReduction[0];
          if ($specific_price->reduction == 1)
          {
            $specific_price->reduction = 0;
          }
          $specific_price->reduction_type = "percentage";
          if ($specific_price->reduction == 0)
          {
            $specific_price->reduction_type = "amount";
          }
          $specific_price->from = $sconto_erp->Dtinizio;
          $specific_price->to = $sconto_erp->Dtfine;
          $specific_price->stringSconto = $infoReduction[1];
          $specific_price->type = $sconto_erp->priority . "_" . $sconto_erp->id;
          $id_product = $this->getIdProductFromCode($cdprodotto);
          $id_customer = $this->getCustomerIdFromCode($cdanag);

          if ($sconto_erp->Nrprezzo == 0.00000)
          {
            if (PROPAPAGAZIONE_LISTINI_CLIENTI == 1 && isset($this->_APP1O_LIST_BASE[$cdanag]))
            {
              $appo_split = explode("_", $this->_APPO_LIST_BASE[$cdanag]);
              $id_listino = $appo_split[0];
              $list_sconto = $appo_split[1];
              $specific_price->price = $this->_GROUP_MISTO_PER_LISTINI[$id_listino][$list_sconto]['specific_price'][$cdprodotto]->price;
            }
            else
            {
              $specific_price->price = -1;
            }
          }
          $id_group = 0;
          if ($cdanag == "CCORRI")
          {
            $id_customer = null;
            $id_group = 1;
          }
          if ($id_product != null and $id_group == 1)
          {
            $this->checkIfExistsHighPriceRuleCCORRI($specific_price, $id_product);
          }
          if ($id_customer != null and $id_customer != "" and $id_product != null)
          {

            $this->checkIfExistsHighPriceRule($specific_price, $id_product, $id_customer);
          }
        }
      }
      else if (!empty($sconto_erp->Cdcatart) and ! empty($sconto_erp->CDanag))
      {
        $arrayProductInCat = $this->getProductInsideCategory($sconto_erp->Cdcatart);
        if (!empty($arrayProductInCat) and count($arrayProductInCat) > 0)
        {
          foreach ($arrayProductInCat as $CdprodottoObj)
          {
            $cdprodotto = $CdprodottoObj->code;
            $infoReduction = explode(";", $this->calculateREduction(array($sconto_erp->NRsconto)));
            $cdanag = $sconto_erp->CDanag;
            $specific_price->price = $sconto_erp->Nrprezzo;
            $specific_price->from_quantity = $sconto_erp->NRqtaLimite;
            $specific_price->reduction = $infoReduction[0];
            if ($specific_price->reduction == 1)
            {
              $specific_price->reduction = 0;
            }
            $specific_price->reduction_type = "percentage";
            if ($specific_price->reduction == 0)
            {
              $specific_price->reduction_type = "amount";
            }
            $specific_price->from = $sconto_erp->Dtinizio;
            $specific_price->to = $sconto_erp->Dtfine;
            $specific_price->stringSconto = $infoReduction[1];
            $specific_price->type = $sconto_erp->priority . "_" . $sconto_erp->id;
            $id_product = $this->getIdProductFromCode($cdprodotto);
            $id_customer = $this->getCustomerIdFromCode($cdanag);
            if ($sconto_erp->Nrprezzo == 0.00000)
            {

              if (PROPAPAGAZIONE_LISTINI_CLIENTI == 1 && isset($this->_APP1O_LIST_BASE[$cdanag]))
              {
                $appo_split = explode("_", $this->_APPO_LIST_BASE[$cdanag]);
                $id_listino = $appo_split[0];
                $list_sconto = $appo_split[1];
                $specific_price->price = $this->_GROUP_MISTO_PER_LISTINI[$id_listino][$list_sconto]['specific_price'][$cdprodotto]->price;
              }
              else
              {
                $specific_price->price = -1;
              }
            }
            $id_group = 0;
            if ($cdanag == "CCORRI")
            {
              $id_customer = null;
              $id_group = 1;
            }
            if ($id_product != null and $id_group == 1)
            {
              $this->checkIfExistsHighPriceRuleCCORRI($specific_price, $id_product);
            }
            if ($id_customer != null and $id_product != null)
            {
              $this->checkIfExistsHighPriceRule($specific_price, $id_product, $id_customer);
            }
          }
        }
      }
      else if (!empty($sconto_erp->Cdcatart) and ! empty($sconto_erp->Cdcatcli))
      {
        foreach ($this->ERP_CATCUSTOMER[$sconto_erp->Cdcatcli] as $cdanag)
        {

          $arrayProductInCat = $this->getProductInsideCategory($sconto_erp->Cdcatart);
          if (!empty($arrayProductInCat) and count($arrayProductInCat) > 0)
          {
            foreach ($arrayProductInCat as $CdprodottoObj)
            {
              $infoReduction = explode(";", $this->calculateREduction(array($sconto_erp->NRsconto)));
              $cdprodotto = $CdprodottoObj->code;
              $specific_price->price = $sconto_erp->Nrprezzo;
              $specific_price->from_quantity = $sconto_erp->NRqtaLimite;
              $specific_price->reduction = $infoReduction[0];
              if ($specific_price->reduction == 1)
              {
                $specific_price->reduction = 0;
              }
              $specific_price->reduction_type = "percentage";
              if ($specific_price->reduction == 0)
              {
                $specific_price->reduction_type = "amount";
              }
              $specific_price->from = $sconto_erp->Dtinizio;
              $specific_price->to = $sconto_erp->Dtfine;
              $specific_price->stringSconto = $infoReduction[1];
              $specific_price->type = $sconto_erp->priority . "_" . $sconto_erp->id;
              $id_product = $this->getIdProductFromCode($cdprodotto);
              $id_customer = $this->getCustomerIdFromCode($cdanag);
              if ($sconto_erp->Nrprezzo == 0.00000)
              {
                //if (isset($this -> _GESTIONE_PREZZI[11][$cdprodotto . "-" . $cdanag])) {
                /*
                  if (isset($this -> _MAPPING_ANAG_IDLISTINO[$cdanag]) and isset($this -> GROUP_LISTINI_BASE[$this -> _MAPPING_ANAG_IDLISTINO[$cdanag]][$cdprodotto] -> price)) {
                  //$specific_price -> price = $this -> _GESTIONE_PREZZI[11][$cdprodotto . "-" . $cdanag] -> price;
                  $specific_price -> price = $this -> GROUP_LISTINI_BASE[$this -> _MAPPING_ANAG_IDLISTINO[$cdanag]][$cdprodotto] -> price;
                  } else {
                  $specific_price -> price = -1;
                  }
                 */
                if (PROPAPAGAZIONE_LISTINI_CLIENTI == 1 && isset($this->_APP1O_LIST_BASE[$cdanag]))
                {
                  $appo_split = explode("_", $this->_APPO_LIST_BASE[$cdanag]);
                  $id_listino = $appo_split[0];
                  $list_sconto = $appo_split[1];
                  $specific_price->price = $this->_GROUP_MISTO_PER_LISTINI[$id_listino][$list_sconto]['specific_price'][$cdprodotto]->price;
                }
                else
                {
                  $specific_price->price = -1;
                }
              }
              $id_group = 0;
              if ($cdanag == "CCORRI")
              {
                $id_customer = null;
                $id_group = 1;
              }
              if ($id_product != null and $id_group == 1)
              {
                $this->checkIfExistsHighPriceRuleCCORRI($specific_price, $id_product);
              }
              if ($id_customer != null and $id_product != null)
              {
                $this->checkIfExistsHighPriceRule($specific_price, $id_product, $id_customer);
              }
            }
          }
        }
      }
      else
      {
        // sconti scaglione
        $infoReduction = explode(";", $this->calculateREduction(array($sconto_erp->NRsconto)));
        $cdprodotto = $sconto_erp->CDprodotto;
        $cdanag = 0;
        $specific_price->price = $sconto_erp->Nrprezzo;
        $specific_price->from_quantity = $sconto_erp->NRqtaLimite;
        $specific_price->reduction = $infoReduction[0];
        if ($specific_price->reduction == 1)
        {
          $specific_price->reduction = 0;
        }
        $specific_price->reduction_type = "percentage";
        if ($specific_price->reduction == 0)
        {
          $specific_price->reduction_type = "amount";
        }
        $specific_price->from = $sconto_erp->Dtinizio;
        $specific_price->to = $sconto_erp->Dtfine;
        $specific_price->stringSconto = $infoReduction[1];
        $specific_price->type = $sconto_erp->priority . "_" . $sconto_erp->id;

        $id_product = $this->getIdProductFromCode($cdprodotto);
        //$id_customer = $this -> getCustomerIdFromCode($cdanag);
        if ($sconto_erp->Nrprezzo == 0.00000)
        {
          $specific_price->price = -1;
        }
        if (PROPAPAGAZIONE_LISTINI_CLIENTI == 0)
        {
          $specific_price->price = -1;
        }
        if ($id_product != null)
        {
          $this->checkIfExistsHighPriceRule($specific_price, $id_product, 0);
        }
      }
    }
  }

  function checkIfExistsHighPriceRuleCCORRI($specific_prezzi_speciali, $id_product) {
    $from = $specific_prezzi_speciali->from;
    $to = $specific_prezzi_speciali->to;
    $count = 0;
    if (true)
    {

      // caso 2 - FUORI
      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop FROM ps_specific_price WHERE id_product = {$id_product} AND id_group = 1 AND `from` >= '{$from}' AND `to` <= '{$to}'";
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      $db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        if ($result->start != NULL and $result->stop != null)
        {
          if ($result->start != $result->stop)
          {
            if ($from != $result->start)
            {
              $result->start = date("yyyy-mm-dd HH:ii:ss", strtotime($result->start) - 1);
              if ($result->start >= date("yyyy-mm-dd HH:ii:ss"))
              {
                $array_last[] = $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $result->start);
              }
            }
            if ($result->stop != $to)
            {
              $to = date("yyyy-mm-dd HH:ii:ss", strtotime($to) - 1);
              if ($to >= date("yyyy-mm-dd HH:ii:ss"))
              {
                $array_last[] = $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $result->stop, $to);
              }
            }
            $count++;
          }
        }
        else
        {
          $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $from, $to);
          $count++;
        }
      }

      // caso 3 - fuori sx

      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_group = 1 AND `from` >= '{$from}' AND `to` >= '{$to}' AND `from` < '{$to}' AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$this -> fileLog($specific_prezzi_speciali -> id . " CASO3 FUORI SX" . $sql);
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        //$this -> fileLog(" --- CASO 3");
        if ($result->start != NULL)
        {
          if ($from != $result->stop)
          {
            $result->start = date("yyyy-mm-dd HH:ii:ss", strtotime($result->start) - 1);
            if ($result->start >= date("yyyy-mm-dd HH:ii:ss"))
            {
              $array_last[] = $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $from, $result->start);
            }
            $count++;
          }
        }
      }
      // caso 4 - FUORI DX

      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_group = 1 AND `from` >= '{$from}' AND `to` >= '{$to}' AND `to` > '{$from}' AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$this -> fileLog($specific_prezzi_speciali -> id . " CASO4 FUORI DX" . $sql);
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        //$this -> fileLog(" --- CASO 4");
        if ($result->stop != null)
        {
          if ($result->stop != $to)
          {

            $to = date("yyyy-mm-dd HH:ii:ss", strtotime($to) - 1);
            if ($to >= date("yyyy-mm-dd HH:ii:ss"))
            {
              $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $result->stop, $to);
            }
            $count++;
          }
        }
      }
      // caso 5 - ESTERNO DX

      $sql = "SELECT MIN(from) AS start, MAX(to) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_group = 1 AND `from` >= '{$to}' AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$this -> fileLog($specific_prezzi_speciali -> id . " CASO5 ESTERNO DX" . $sql);
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        //$this -> fileLog(" --- CASO 5");
        if ($to >= date("yyyy-mm-dd HH:ii:ss"))
        {
          $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $from, $to);
        }
        $count++;
      }

      // caso 6 - ESTERNO SX

      $sql = "SELECT MIN(from) AS start, MAX(to) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_group = 1 AND `to` <= '{$to}' AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$this -> fileLog($specific_prezzi_speciali -> id . " CASO6 ESTERNO SX" . $sql);
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        //$this -> fileLog(" --- CASO 6");
        if ($to >= date("yyyy-mm-dd HH:ii:ss"))
        {
          $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $from, $to);
        }
        $count++;
      }
    }
    //$this -> fileLog($specific_prezzi_speciali -> id . " COUNt $count");
    if ($count == 0)
    {
      if ($to >= date("yyyy-mm-dd HH:ii:ss"))
      {
        $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $from, $to);
        //$this -> fileLog(" --- CASO PRIMO SCONTO");
        //$this -> fileLog($specific_prezzi_speciali -> id . " COUNT = 0");
      }
    }
  }

  function checkIfExistsHighPriceRule($specific_prezzi_speciali, $id_product, $id_customer) {
    $from = $specific_prezzi_speciali->from;
    $to = $specific_prezzi_speciali->to;
    $count = 0;
    if ($id_customer > 0)
    {

      // caso 2 - FUORI
      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, from_quantity FROM ps_specific_price WHERE id_product = {$id_product} AND id_customer = {$id_customer} AND `from` >= '{$from}' AND `to` <= '{$to}'";
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      $db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        if ($result->start != NULL and $result->stop != null)
        {
          if ($result->start != $result->stop)
          {
            if ($from != $result->start)
            {
              $result->start = date("yyyy-mm-dd HH:ii:ss", strtotime($result->start) - 1);
              if ($result->start >= date("yyyy-mm-dd HH:ii:ss"))
              {
                $array_last[] = $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $result->start);
              }
            }
            if ($result->stop != $to)
            {
              $to = date("yyyy-mm-dd HH:ii:ss", strtotime($to) - 1);
              if ($to >= date("yyyy-mm-dd HH:ii:ss"))
              {
                $array_last[] = $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $result->stop, $to);
              }
            }
            if ($specific_prezzi_speciali->from_quantity != $result->from_quantity and $from == $result->start and $result->stop == $to)
            {
              $array_last[] = $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $to);
            }
            $count++;
          }
        }
      }

      // caso 3 - fuori sx

      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_customer = {$id_customer} AND `from` > '{$from}' AND `to` >= '{$to}' AND `from` < '{$to}'";
      if (count($array_last) > 0)
      {
        $sql = $sql . " AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      }
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        //$this -> fileLog(" --- CASO 3");
        if ($result->start != NULL)
        {
          if ($from != $result->stop)
          {
            $result->start = date("yyyy-mm-dd HH:ii:ss", strtotime($result->start) - 1);
            if ($result->start >= date("yyyy-mm-dd HH:ii:ss"))
            {
              $array_last[] = $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $result->start);
            }
            $count++;
          }
        }
      }
      // caso 4 - FUORI DX

      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_customer = {$id_customer} AND `from` <= '{$from}' AND `to` < '{$to}' AND `to` > '{$from}' ";
      if (count($array_last) > 0)
      {
        $sql = $sql . " AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      }
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        //$this -> fileLog(" --- CASO 4");
        if ($result->stop != null)
        {
          if ($result->stop != $to || $specific_prezzi_speciali->from_quantity != $result->from_quantity)
          {

            $to = date("yyyy-mm-dd HH:ii:ss", strtotime($to) - 1);
            if ($to >= date("yyyy-mm-dd HH:ii:ss"))
            {
              $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $result->stop, $to);
            }
            $count++;
          }
        }
      }
      // caso 5 - ESTERNO DX

      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_customer = {$id_customer} AND `from` >= '{$to}' ";
      if (count($array_last) > 0)
      {
        $sql = $sql . " AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      }
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        if ($result->start != NULL and $result->stop != null)
        {
          //$this -> fileLog(" --- CASO 5");
          if ($to >= date("yyyy-mm-dd HH:ii:ss"))
          {
            $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $to);
          }
          $count++;
        }
      }

      // caso 6 - ESTERNO SX

      $sql = "SELECT MIN(`from`) AS start, MAX(`to`) AS stop, id_specific_price FROM ps_specific_price WHERE id_product = {$id_product}";
      $sql = $sql . " AND id_customer = {$id_customer} AND `to` <= '{$from}' ";
      if (count($array_last) > 0)
      {
        $sql = $sql . " AND id_specific_price NOT IN (" . implode(",", $array_last) . ")";
      }
      if ($specific_prezzi_speciali->priority == 12)
      {
        $sql = $sql . " AND from_quantity = 1 AND (`type` LIKE '1_%' OR `type` LIKE '2_%' OR `type` LIKE '3_%' OR `type` LIKE '4_%' OR `type` LIKE '5_%')";
      }
      //$db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        if ($result->start != NULL and $result->stop != null)
        {
          //$this -> fileLog(" --- CASO 6");
          if ($to >= date("yyyy-mm-dd HH:ii:ss"))
          {
            $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $to);
          }
          $count++;
        }
      }
    }
    else
    {
      if (PROPAPAGAZIONE_LISTINI_CLIENTI == 1)
      {
        // non Ã¨ configurato id_customer
        // quindi lo sconto va applicato a tutti i clienti.
        // ma perla nuova logica si applica a tutti i gruppi clienti legati ai listini base
        foreach ($this->_ARRAY_GROUP_LISTINI as $id_grp_listbase)
        {
          // configuro il nuovo prezo speciale a seconda del listino
          $specific_prezzi_speciali->price = $this->getPriceListBaseGroupFromPrId($id_product, $id_grp_listbase);
          // recupero anche lo sconto applicato al listino base e lo configuro
          $infoReduction = explode(";", $this->calculateREduction(array($specific_prezzi_speciali->stringSconto, $this->getReductionListBaseGroupFromPrId($id_product, $id_grp_listbase))));
          $specific_prezzi_speciali->reduction = $infoReduction[0];
          $originale_stringSconto = $specific_prezzi_speciali->stringSconto;
          $specific_prezzi_speciali->stringSconto = $infoReduction[1];
          $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, $id_grp_listbase, $from, $to);
          $specific_prezzi_speciali->stringSconto = $originale_stringSconto;
          $infoReduction = null;
        }
      }
      else
      {
        $specific_prezzi_speciali->price = $this->getProductBasePrice($id_product);
        // recupero anche lo sconto applicato al listino base e lo configuro
        $infoReduction = explode(";", $this->calculateREduction(array($specific_prezzi_speciali->stringSconto)));
        $specific_prezzi_speciali->reduction = $infoReduction[0];
        $originale_stringSconto = $specific_prezzi_speciali->stringSconto;
        $specific_prezzi_speciali->stringSconto = $infoReduction[1];
        $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 0, $from, $to);
        $specific_prezzi_speciali->stringSconto = $originale_stringSconto;
        $infoReduction = null;
      }
      if (!empty($this->VISITOR))
      {
        // configuro il nuovo prezo speciale a seconda del listino
        $specific_prezzi_speciali->price = $this->getPriceListBaseGroupFromPrId($id_product, 1);
        // recupero anche lo sconto applicato al listino base e lo configuro
        $infoReduction = explode(";", $this->calculateREduction(array($specific_prezzi_speciali->stringSconto, $this->getReductionListBaseGroupFromPrId($id_product, 1))));
        $specific_prezzi_speciali->reduction = $infoReduction[0];
        $originale_stringSconto = $specific_prezzi_speciali->stringSconto;
        $specific_prezzi_speciali->stringSconto = $infoReduction[1];
        $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 1, $from, $to);
        $specific_prezzi_speciali->stringSconto = $originale_stringSconto;
        $infoReduction = null;
      }
      // configuro lo sconto per tutti i customers
      // configuro il nuovo prezo speciale a seconda del listino
      $specific_prezzi_speciali->price = $this->getPriceListBaseGroupFromPrId($id_product, 3);
      // recupero anche lo sconto applicato al listino base e lo configuro
      $infoReduction = explode(";", $this->calculateREduction(array($specific_prezzi_speciali->stringSconto, $this->getReductionListBaseGroupFromPrId($id_product, 3))));
      $specific_prezzi_speciali->reduction = $infoReduction[0];
      $originale_stringSconto = $specific_prezzi_speciali->stringSconto;
      $specific_prezzi_speciali->stringSconto = $infoReduction[1];
      $this->insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, 3, $from, $to);
      $specific_prezzi_speciali->stringSconto = $originale_stringSconto;
      $infoReduction = null;
      $count++;
    }
    if (!$this->isTherePriceHighPriority($id_product, $id_customer))
    {
      if ($to >= date("yyyy-mm-dd HH:ii:ss"))
      {
        $this->insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $to);
      }
    }
  }

  function insertSpecificPriceFromRuleGroup($specific_prezzi_speciali, $id_product, $id_group, $from, $to) {
    $values = null;
    $values['id_specific_price_rule'] = MySQL::SQLValue(0);
    $values['id_cart'] = MySQL::SQLValue(0);
    $values['id_product'] = MySQL::SQLValue($id_product);

    $values['id_shop'] = MySQL::SQLValue(0);
    $values['id_shop_group'] = MySQL::SQLValue(0);
    $values['id_currency'] = MySQL::SQLValue(0);
    $values['id_country'] = MySQL::SQLValue(0);
    $values['id_group'] = MySQL::SQLValue($id_group);
    $values['id_customer'] = MySQL::SQLValue(0);
    $values['price'] = MySQL::SQLValue($specific_prezzi_speciali->price);
    if ($specific_prezzi_speciali->from_quantity == 0)
    {
      $specific_prezzi_speciali->from_quantity = 1;
    }
    $values['from_quantity'] = MySQL::SQLValue($specific_prezzi_speciali->from_quantity);
    $values['reduction'] = MySQL::SQLValue($specific_prezzi_speciali->reduction);
    $values['reduction_type'] = MySQL::SQLValue($specific_prezzi_speciali->reduction_type);
    $values['from'] = MySQL::SQLValue($from);
    $values['to'] = MySQL::SQLValue($to);
    $values['type'] = MySQL::SQLValue($specific_prezzi_speciali->type);
    $values['stringSconto'] = MySQL::SQLValue($specific_prezzi_speciali->stringSconto);

    $db = new MySQL();
    $last_id = $db->InsertRow("ps_specific_price", $values);
    $this->_GLOBAL_COUNTER++;

    return $last_id;
  }

  function insertSpecificPriceFromRule($specific_prezzi_speciali, $id_product, $id_customer, $from, $to) {
    $values = null;
    $values['id_specific_price_rule'] = MySQL::SQLValue(0);
    $values['id_cart'] = MySQL::SQLValue(0);
    $values['id_product'] = MySQL::SQLValue($id_product);

    $values['id_shop'] = MySQL::SQLValue(0);
    $values['id_shop_group'] = MySQL::SQLValue(0);
    $values['id_currency'] = MySQL::SQLValue(0);
    $values['id_country'] = MySQL::SQLValue(0);
    $values['id_group'] = MySQL::SQLValue(0);
    $values['id_customer'] = MySQL::SQLValue($id_customer);
    $values['price'] = MySQL::SQLValue($specific_prezzi_speciali->price);
    if ($specific_prezzi_speciali->from_quantity == 0)
    {
      $specific_prezzi_speciali->from_quantity = 1;
    }
    $values['from_quantity'] = MySQL::SQLValue($specific_prezzi_speciali->from_quantity);
    $values['reduction'] = MySQL::SQLValue($specific_prezzi_speciali->reduction);
    $values['reduction_type'] = MySQL::SQLValue($specific_prezzi_speciali->reduction_type);
    $values['from'] = MySQL::SQLValue($from);
    $values['to'] = MySQL::SQLValue($to);
    $values['type'] = MySQL::SQLValue($specific_prezzi_speciali->type);
    $values['stringSconto'] = MySQL::SQLValue($specific_prezzi_speciali->stringSconto);

    $db = new MySQL();
    $last_id = $db->InsertRow("ps_specific_price", $values);
    $this->_GLOBAL_COUNTER++;

    return $last_id;
  }

  function getPriceListBaseGroupFromPrId($id_product, $id_grp_listbase) {
    $sql = "SELECT * FROM ps_specific_price WHERE id_product = {$id_product} AND from_quantity = 1 AND id_group = {$id_grp_listbase}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->price;
    }
    else
    {
      return -1;
    }
  }

  function getReductionListBaseGroupFromPrId($id_product, $id_grp_listbase) {
    $sql = "SELECT * FROM ps_specific_price WHERE id_product = {$id_product} AND from_quantity = 1 AND id_group = {$id_grp_listbase}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->stringSconto;
    }
    else
    {
      return null;
    }
  }

  function getProductBasePrice($id_product) {
    $sql = "SELECT * FROM ps_product WHERE id_product = {$id_product}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->price;
    }
    else
    {
      return -1;
    }
  }

  function isTherePriceHighPriority($id_product, $id_customer) {
    $sql = "SELECT * FROM ps_specific_price WHERE id_customer = {$id_customer} AND id_product = {$id_product}";
    $db = new MySQL();
    $db->Query($sql);
    if ($db->RowCount() > 0)
    {
      return true;
    }
    else
    {
      return false;
    }
  }

  function insertCustomerGroup($id_customer, $id_group) {

    $db = new MySQL();
    $query = "INSERT INTO ps_customer_group VALUES ({$id_customer}, {$id_group})";
    $db->Query($query);
    $this->setDefaultGroup($id_customer, $id_group);
  }

  function setDefaultGroup($id_customer, $id_group) {
    $db = new MySQL();
    $query = "UPDATE ps_customer SET id_default_group = {$id_group} WHERE id_customer = {$id_customer}";
    $db->Query($query);
  }

  function enableScontiQt() {
    $sql_1 = "UPDATE ps_configuration SET `value` = '1' WHERE `name` = 'PS_SPECIFIC_PRICE_FEATURE_ACTIVE'";
    $db = new MySQL();
    $db->Query($sql_1);

    $sql = "UPDATE ps_configuration SET `value` = NULL WHERE `name` = 'PS_PACK_FEATURE_ACTIVE'";
    $db->Query($sql);
  }

}
