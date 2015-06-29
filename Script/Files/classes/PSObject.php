<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

$FILE_LOG_NAME = "log_import_" . date("YmdHis") . ".txt";

/**
 * File contenente le informazioni sulla versione del connector windows usata
 */
define("FILE_VERSION_ERP", "CONN_VER");

/**
 * File INFO ecommerce
 */
define("FILE_INFO", "TPD_INFO");

/**
 * Description of PSObject
 *
 * @author ALLINITSRLS
 */
class PSObject extends VisionConnectorConfig {

  /**
   * Metodo utilizzato prima di ogni import per eliminare i file
   * del tracciato e le immagini temporanee generate
   */
  function initImportFolder() {
    $files_data = glob(FOLDER_UNZIP . "*");
    foreach ($files_data as $file)
    {
      if (is_file($file))
      {
        unlink($file);
      }
    }
    $files_img = glob(FOLDER_TMP_IMG . "*");
    foreach ($files_img as $file)
    {
      if (is_file($file))
      {
        unlink($file);
      }
    }
  }

  /**
   * Metodo utilizzato per estrarre i tracciati dati e immagini 
   * nella cartella import
   */
  function extractFileIntoFolder() {
    $zip = new \ZipArchive();
    $res = $zip->open(FOLDER_IMPORT_ROOT . FILE_ERP_DATA);
    if ($res === TRUE)
    {
      $zip->extractTo(FOLDER_UNZIP);
      $zip->close();
    }

    $res2 = $zip->open(FOLDER_IMPORT_ROOT . FILE_ERP_IMG);
    if ($res2 === TRUE)
    {
      $zip->extractTo(FOLDER_UNZIP);
      $zip->close();
    }
  }

  /**
   * Metodo utilizzato per fare il check della versione dei tracciati inviati da 
   * ERP
   * 
   * @return boolean Se TRUE versione valida, FALSE versione non corretta
   */
  function checkVersionImporter() {
    $stringVersion = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_VERSION_ERP . ".txt");
    $arrayVersion = explode(".", $stringVersion);
    $this->fileLog("#3: Read String -" . $stringVersion . "-");
    $YY = $arrayVersion[1];
    $this->fileLog("#3: FILE DATA VERSION -" . $YY . "-");
    $this->fileLog("#3: SCRIPT DATA VERSION -" . WIN_CONNECTOR_VER . "-");
    if ($YY !== WIN_CONNECTOR_VER)
    {
      return FALSE;
    }
    else
    {
      return TRUE;
    }
  }

  /**
   * Metodo utilizzato per rendere il contenuto di un tracciato ricevuto da ERP
   * utilizzabile dal connector PHP
   * 
   * @param String $fn Percorso del file da leggere
   * @return String Contenuto del file appena letto in UTF-8 senza BOM
   */
  function file_get_contents_utf8($fn) {
    $content = file_get_contents($fn);

    $content_nobom = $this->removeBOM($content);

    return trim($content_nobom);
  }

  /**
   * Metodo utilizzato per la creazione del file di log
   * 
   * @param String $message Stringa da inserire nel file di log
   */
  function fileLog($message) {
    $str_to_log = "[" . date("d/m/Y H:i:s") . "] " . $message;
    file_put_contents(PATH_ABSOLUTE_AICONNECTOR . "/visionImport/logs/" . $GLOBALS['FILE_LOG_NAME'], $str_to_log . "\r\n", FILE_APPEND);
  }

  /**
   * Metodo utilizzato per inviare email
   */
  function sendMailLog() {
    $mail = new \PHPMailer();


    $mail->AddAddress(EMAIL_LOG_TO);
    $mail->AddAddress(EMAIL_LOG_TO_2);

    $mail->From = EMAIL_LOG_FROM;
    $mail->FromName = EMAIL_LOG_SUBJECT;
    $mail->Subject = EMAIL_LOG_SUBJECT;
    $mail->Body = "Caro Amministratore,\r\ndi seguito il link del report dell'esportazione dati da VisionERP.\r\n" . SHOP_URL . "/visionImport/logs/" . $GLOBALS['FILE_LOG_NAME'] . "\r\n";

    $mail->Send();
  }

  /**
   * Metodo utilizzato per capire se fare AZZERA E RICARICA
   * sul file (oggetto) in questione
   * 
   * @param String $file_check Nome del file da analizzare
   * @return int Se 0 AZZERA E RICARICA, 1 in APPEND MODE
   */
  function checkInsertOrAppend($file_check) {
    $content = file_get_contents(FOLDER_UNZIP . $file_check . ".par");
    $par = explode(" ", $content);
    if ($par[1] === "1")
    {
      return 0;
    }
    else
    {
      return 1;
    }
  }

  /**
   * Metodo utilizzato per leggere le informazioni da ERP
   * e inserirle nella tabella vsecommerce_info
   */
  function readInfoEcommerce() {
    $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_INFO . ".txt");
    $array_field = explode("\n", $content);
    if ($this->checkInsertOrAppend(FILE_INFO) == 0)
    {
      $this->truncateTable("vsecommerce_info");
    }
    foreach ($array_field as $field_comb)
    {
      $data = explode("§", $field_comb);

      if (!$this->checkInfoExists(trim($data[0])))
      {
        $values['field_name'] = MySQL::SQLValue(trim($data[0]));
        $values['value'] = MySQL::SQLValue(trim($data[1]));
        $db = new MySQL();
        $db->InsertRow("vsecommerce_info", $values);
      }
      else
      {
        $where['field_name'] = MySQL::SQLValue(trim($data[0]));
        $values['value'] = MySQL::SQLValue(trim($data[1]));
        $db = new MySQL();
        $db->UpdateRows("vsecommerce_info", $values, $where);
      }
    }
  }

  /**
   * Metodo utilizzato per controllare se il campo è da aggiornare o inserire
   * nella tabella vsecommerce_info 
   * 
   * @param String $field_name Nome del parametro
   * @return boolean Se TRUE il campo field_name esiste nella cartella vsecommerce_info, falso il campo non esiste
   */
  function checkInfoExists($field_name) {
    $sql = "SELECT * FROM vsecommerce_info WHERE field_name = '{$field_name}'";
    $db = new MySQL();
    $db->Query($sql);
    if ($db->RowCount() > 0)
    {
      return TRUE;
    }
    else
    {
      return FALSE;
    }
  }

  /**
   * Metodo per fare il TRUNCATE della tabella passato come parametro
   * 
   * @param String $tablename Nome della tabella 
   */
  function truncateTable($tablename) {
    $db = new MySQL();
    $sql = "TRUNCATE `" . $tablename . "`";
    $db->Query($sql);
  }

  /**
   * Metodo per rimuovere il carattere di BOM da fine stringa
   * 
   * @param String $str Stringa a cui rimuovere
   * @return String Stringa rimossa del BOM
   */
  function removeBOM($str = "") {
    if (substr($str, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf))
    {
      $str = substr($str, 3);
    }
    return $str;
  }

  /**
   * Metodo per codificare una stringa in url corretta
   * 
   * @param String $text Stringa da codificare in url
   * @return string Stringa in modalità slugify
   */
  public function slugify($text) {
    $text_1 = strtolower($text);
    // replace non letter or digits by -
    $text_2 = preg_replace('~[^\\pL\d]+~u', '-', $text_1);

    // trim
    $text_3 = trim($text_2, '_');

    // transliterate
    $text_4 = iconv('utf-8', 'us-ascii//TRANSLIT', $text_3);

    // lowercase
    //$text = strtolower($text);
    // remove unwanted characters
    $text_5 = preg_replace('~[^-\w]+~', '', $text_4);

    if (empty($text_5))
    {
      return 'n-a';
    }

    return $text_5;
  }

  /**
   * Metodo utilizzato per rigenrare tutte le immagini degli oggetti di Prestahop
   * Utilizzato override interno di classe protected di Prestashop in AdminImagesController
   */
  function regenerateThumbnail() {
    Context::getContext()->shop->setContext(Shop::CONTEXT_ALL);
    $ic = new \AdminImagesController();
    $ic->rigeneraAllIniTThumb();
    exec('/usr/bin/wget -O - -q -t 1 "' . URL_TO_WGET . '" >/dev/null 2>&1');
  }

  /**
   * Metodo per controllare se sono presenti immagini 
   * 
   * @return boolean Se TRUE immagini presenti, FALSE non ci sono immagini
   */
  function checkIfThereAreImages() {
    $directory = FOLDER_UNZIP;
    $files = glob($directory . '*.JPG');

    if ($files !== false)
    {
      $filecount = count($files);
      if ($filecount > 0)
      {
        return true;
      }
      else
      {
        return false;
      }
    }
    else
    {
      return false;
    }
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

  function clearPSCache() {
    shell_exec('php cleanPSCache.php  > /dev/null 2>/dev/null &');
  }

}
