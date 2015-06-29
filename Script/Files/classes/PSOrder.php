<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

define("FOLDER_PDF", PATH_ABSOLUTE_AICONNECTOR . "/img/p/pdf/");

define("FOLDER_TMP_PDF", PATH_ABSOLUTE_AICONNECTOR . "/img/pdftmp/");

/**
 * Description of PSOrder
 *
 * @author ALLINITSRLS
 */
class PSOrder extends PSObject {

  function getOrderDocuments() {
    if (!file_exists(FOLDER_UNZIP . "TSD_DOCTES.txt"))
    {
      $this->fileLog("#15 ERROR FILE ORDER DOCUMENTS NON PRESENTE.");
    }
    else
    {
      $appendOrderDocuments = true;
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSD_DOCTES.txt");
      $orderDocuments = explode("\n", $content);
      try
      {
        $appendOrderDocuments = ($this->checkInsertOrAppend("TSD_DOCTES") == 1);
      }
      catch (Exception $e)
      {
        $this->fileLog("#15 ERROR FILE PAR DOCUMENTS NON PRESENTE.");
      }
      if (!$appendOrderDocuments)
      {
        $this->truncateTable("vs_orderdocuments");
        $this->truncateTable("vs_orderdocuments_details");
        $this->truncateTable("vs_orderdocuments_attachments");
      }
      foreach ($orderDocuments as $orderDocument)
      {
        $data = explode("§", $orderDocument);
        $this->insertOrderDocuments($data);
      }
    }
  }

  function getOrderDocumentsDetails() {
    if (!file_exists(FOLDER_UNZIP . "TSD_DOCRIG.txt"))
    {
      $this->fileLog("#15 ERROR FILE ORDER DOCUMENTS RIGHE NON PRESENTE.");
    }
    else
    {
      $appendOrderDocumentsDetails = true;
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSD_DOCRIG.txt");
      $orderDocumentsDetails = explode("\n", $content);
      try
      {
        $appendOrderDocumentsDetails = ($this->checkInsertOrAppend("TSD_DOCRIG") == 1);
      }
      catch (Exception $e)
      {
        $this->fileLog("#15 ERROR FILE PAR DOCUMENTS RIGHE NON PRESENTE.");
      }
      if (!$appendOrderDocumentsDetails)
      {
        $this->truncateTable("vs_orderdocuments_details");
      }
      foreach ($orderDocumentsDetails as $orderDocumentDetail)
      {
        $data = explode("§", $orderDocumentDetail);
        $this->insertOrderDocumentsDetails($data);
      }
    }
  }

  function insertOrderDocuments($data) {

    $db = new MySQL();
    //$this -> fileLog("#15  Inserimento documento ordine " . $data[0]);
    $orderDocument['id_doc'] = MySQL::SQLValue($data[0]);
    $orderDocument['data_doc'] = MySQL::SQLValue($data[1]);
    $orderDocument['tipo_doc'] = MySQL::SQLValue($data[2]);
    $orderDocument['cod_doc'] = MySQL::SQLValue($data[3]);
    $orderDocument['num_doc'] = MySQL::SQLValue($data[4]);
    $orderDocument['cod_cli'] = MySQL::SQLValue($data[5]);
    $orderDocument['cod_age'] = MySQL::SQLValue($data[6]);
    $orderDocument['des_rsoc'] = MySQL::SQLValue($data[7]);
    $orderDocument['des_indi'] = MySQL::SQLValue($data[8]);
    $orderDocument['des_cap'] = MySQL::SQLValue($data[9]);
    $orderDocument['des_loca'] = MySQL::SQLValue($data[10]);
    $orderDocument['des_prov'] = MySQL::SQLValue($data[11]);
    $orderDocument['tot_imp'] = MySQL::SQLValue($data[12]);
    $orderDocument['tot_iva'] = MySQL::SQLValue($data[13]);
    $orderDocument['cod_valuta'] = MySQL::SQLValue($data[14]);
    $orderDocument['des_trasp'] = MySQL::SQLValue($data[15]);
    $orderDocument['des_porto'] = MySQL::SQLValue($data[16]);
    $orderDocument['peso_netto'] = MySQL::SQLValue($data[17]);
    $orderDocument['peso_lordo'] = MySQL::SQLValue($data[18]);
    $db->InsertRow("vs_orderdocuments", $orderDocument);
  }

  function insertOrderDocumentsDetails($data) {
    $db = new MySQL();
    $orderDocumentDetails['id_riga'] = MySQL::SQLValue($data[0]);
    $orderDocumentDetails['id_doc'] = MySQL::SQLValue($data[1]);
    $orderDocumentDetails['num_riga'] = MySQL::SQLValue($data[2]);
    $orderDocumentDetails['cod_art'] = MySQL::SQLValue($data[3]);
    $orderDocumentDetails['des_riga'] = MySQL::SQLValue($data[4]);
    $orderDocumentDetails['qta'] = MySQL::SQLValue($data[5]);
    $orderDocumentDetails['prezzo'] = MySQL::SQLValue($data[6]);
    $orderDocumentDetails['sconti'] = MySQL::SQLValue($data[7]);
    $orderDocumentDetails['importo'] = MySQL::SQLValue($data[8]);
    $orderDocumentDetails['id_rig_da'] = MySQL::SQLValue($data[9]);
    $db->InsertRow("vs_orderdocuments_details", $orderDocumentDetails);
  }

  function insertOrderDocumentsPdf() {
    /* Init directories e files */
    if (!is_dir(FOLDER_PDF))
    {
      mkdir(FOLDER_PDF, 0777);
    }
    if (!is_dir(FOLDER_TMP_PDF))
    {
      //$this -> fileLog("#15. Creazione directory temporanea per i PDF: " . $this -> folderTmpPdf);
      mkdir(FOLDER_TMP_PDF, 0777);
    }
    $files = glob(FOLDER_TMP_PDF . "*");
    // get all file names
    foreach ($files as $file)
    {
      if (is_file($file))
        unlink($file);
    }
    $zip = new \ZipArchive;
    $res = $zip->open(FOLDER_IMPORT_ROOT . "oi_b2b_allegati.zip");
    if ($res === TRUE)
    {
      $zip->extractTo(FOLDER_TMP_PDF);
      $zip->close();
    }
    else
    {
      
    }
    /* Fine Init directories e files */

    $filesPdf = glob(FOLDER_TMP_PDF . "*");
    $db = new MySQL();
    // get all file names
    foreach ($filesPdf as $filePdf)
    {
      $fullPath = $filePdf;
      $basename = basename($filePdf);
      $filename = $basename;
      $extension = "";
      $document_id = -1;
      $document_file_name = "";

      if (strpos($basename, '.') !== false)
      {
        $indexPoint = strrpos($basename, ".");
        $filename = substr($basename, 0, (strlen($basename) - (strlen($basename) - $indexPoint)));
        if (($indexPoint + 1) < strlen($basename))
        {
          $extension = substr($basename, $indexPoint + 1);
        }
      }
      if ((strtolower($extension) == "pdf") && (strpos($filename, '_') !== false))
      {
        $indexUnderscore = strpos($filename, "_");
        $document_id_string = substr($filename, 0, (strlen($filename) - (strlen($filename) - $indexUnderscore)));
        $document_file_name = "order" . $document_id_string . "t" . time();
        if (($indexUnderscore + 1) < strlen($filename))
        {
          $document_file_name = substr($filename, $indexUnderscore + 1);
        }
        $document_id = intval($document_id_string);
      }

      if ($document_id > 0)
      {
        $basenameDestination = $document_id . "_" . $document_file_name . ".pdf";
        $fullPathDestination = FOLDER_PDF . $basenameDestination;
        $fullPathSource = $fullPath;
        copy($fullPathSource, $fullPathDestination);
        $orderDocumentPdf['id_doc'] = MySQL::SQLValue($document_id);
        $orderDocumentPdf['filename'] = MySQL::SQLValue($basenameDestination);
        $orderDocumentPdf['uri'] = MySQL::SQLValue("/img/p/pdf/" . $basenameDestination);
        $db->InsertRow("vs_orderdocuments_attachments", $orderDocumentPdf);
      }
    }
  }

}
