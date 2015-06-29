<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

/**
 * Description of PSScadenze
 *
 * @author ALLINITSRLS
 */
class PSScadenze extends PSObject {

  function getScadenze() {
    if (!file_exists(FOLDER_UNZIP . "TSD_SCAD.txt"))
    {
      $this->fileLog("#16 ERROR FILE SCADENZE NON PRESENTE.");
    }
    else
    {
      $appendScadenze = true;
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSD_SCAD.txt");
      $scadenze = explode("\n", $content);
      try
      {
        $appendScadenze = ($this->checkInsertOrAppend("TSD_SCAD") == 1);
      }
      catch (Exception $e)
      {
        $this->fileLog("#16 ERROR FILE PAR SCADENZE NON PRESENTE.");
      }
      if (!$appendScadenze)
      {
        $this->truncateTable("vs_tsd_scad");
        $this->truncateTable("vs_tsa_tiposcad");
        $this->truncateTable("vs_tsa_tipoeff");
      }
      foreach ($scadenze as $scadenza)
      {
        $data = explode("§", $scadenza);
        $this->insertScadenza($data);
      }
      $this->getTipiScadenze();
      $this->getTipiEffetto();
    }
  }

  function getTipiScadenze() {
    if (!file_exists(FOLDER_UNZIP . "TSA_TIPOSCAD.txt"))
    {
      $this->fileLog("#16 ERROR FILE TIPI SCADENZE NON PRESENTE.");
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSA_TIPOSCAD.txt");
      $tipiScadenze = explode("\n", $content);

      foreach ($tipiScadenze as $tipoScadenza)
      {
        $data = explode("§", $tipoScadenza);
        $this->insertTipoScadenza($data);
      }
    }
  }

  function getTipiEffetto() {
    if (!file_exists(FOLDER_UNZIP . "TSA_TIPOEFF.txt"))
    {
      $this->fileLog("#16 ERROR FILE TIPI EFFETTI SCADENZE NON PRESENTE.");
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TSA_TIPOEFF.txt");
      $tipiEffettiScadenze = explode("\n", $content);

      foreach ($tipiEffettiScadenze as $tipoEffettoScadenza)
      {
        $data = explode("§", $tipoEffettoScadenza);
        $this->insertTipoEffettoScadenza($data);
      }
    }
  }

  function insertScadenza($data) {
    $db = new MySQL();
    $scad['id_tpe'] = MySQL::SQLValue($data[0]);
    $scad['id_num'] = MySQL::SQLValue($data[1]);
    $scad['cod_cli'] = MySQL::SQLValue($data[2]);
    $scad['cod_ute'] = MySQL::SQLValue($data[3]);
    $scad['cd_tpf'] = MySQL::SQLValue($data[4]);
    $scad['cd_tps'] = MySQL::SQLValue($data[5]);
    $scad['id_num_fat'] = MySQL::SQLValue($data[6]);
    $scad['dt_fat'] = MySQL::SQLValue($data[7]);
    $scad['dt_scad'] = MySQL::SQLValue($data[8]);
    $scad['cd_divisa'] = MySQL::SQLValue($data[9]);
    $scad['id_sol'] = MySQL::SQLValue($data[10]);
    $scad['dt_sol'] = MySQL::SQLValue($data[11]);
    $scad['mm_note'] = MySQL::SQLValue($data[12]);
    $scad['mr_residuo'] = MySQL::SQLValue($data[13]);
    $scad['nr_giorni_ritardo'] = MySQL::SQLValue($data[14]);
    $scad['nr_prov'] = MySQL::SQLValue($data[15]);
    $scad['dt_liquidazione'] = MySQL::SQLValue($data[16]);
    $db->InsertRow("vs_tsd_scad", $scad);
  }

  function insertTipoScadenza($data) {
    $db = new MySQL();
    $tipoScad['cd_tps'] = MySQL::SQLValue($data[0]);
    $tipoScad['ds_tps'] = MySQL::SQLValue($data[1]);
    $tipoScad['ds_colore'] = MySQL::SQLValue($data[2]);

    $db->InsertRow("vs_tsa_tiposcad", $tipoScad);
  }

  function insertTipoEffettoScadenza($data) {
    $db = new MySQL();
    $tipoEff['id_tpe'] = MySQL::SQLValue($data[0]);
    $tipoEff['ds_tpe'] = MySQL::SQLValue($data[1]);

    $db->InsertRow("vs_tsa_tipoeff", $tipoEff);
  }

}
