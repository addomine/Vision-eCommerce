<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

/**
 * Description of PSPayment
 *
 * @author ALLINITSRLS
 */
class PSPayment extends PSObject {

  function getPaymentMethod() {

    if (!file_exists(FOLDER_UNZIP . "TPD_PAGAMENTI.txt"))
    {
      $this->fileLog("#13 ERROR FILE METODI PAGAMENTO NON PRESENTE.");
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TPD_PAGAMENTI.txt");
      $arrayPaymentMethod = explode("\n", $content);
      if (!$this->checkInsertOrAppend("TPD_PAGAMENTI") == 0)
      {
        $this->truncateTable("ps_webshop_modpagamento");
      }
      foreach ($arrayPaymentMethod as $paymentMethod)
      {
        $data = explode("§", $paymentMethod);
        $this->insertPaymentMethod($data);
      }
    }
  }

  function insertPaymentMethod($data) {
    $db = new MySQL();
    $paymentMethod['cdmodalitapagamento'] = MySQL::SQLValue($data[0]);
    $paymentMethod['dsmodalitapagamento'] = MySQL::SQLValue($data[1]);
    if ((!empty($data[2])) && (strtolower($data[2]) == "true"))
    {
      $paymentMethod['cdvisibilita'] = MySQL::SQLValue(1);
    }
    else
    {
      $paymentMethod['cdvisibilita'] = MySQL::SQLValue(2);
    }

    if (empty($data[3]))
    {
      $paymentMethod['dssconto'] = MySQL::SQLValue("0");
    }
    else
    {
      $paymentMethod['dssconto'] = MySQL::SQLValue($data[3]);
    }
    $db->InsertRow("ps_webshop_modpagamento", $paymentMethod);
  }

}
