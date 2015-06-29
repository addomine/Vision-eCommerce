<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

define('URL_WGET_RELOAD_ATTRIBUTE', "http://devps.all-init.it/modules/blocklayered/blocklayered-attribute-indexer.php?token=f550d3b96e");
define('URL_WGET_RELOAD_ATTRIBUTE_URL', "http://devps.all-init.it/modules/blocklayered/blocklayered-url-indexer.php?token=f550d3b96e&truncate=1");

/**
 * Description of PSFeature
 *
 * @author ALLINITSRLS
 */
class PSFeature extends PSObject {

  function getCaratteristiche() {

    if (!file_exists(FOLDER_UNZIP . "TPD_CARATT_DEF.txt"))
    {
      $this->fileLog("#12 ERROR FILE DESCRIZIONE CARATTERISTICHE NON PRESENTE.");
      return;
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TPD_CARATT_DEF.txt");
      $arrayCaratteristiche = explode("\n", $content);
      if ($this->checkInsertOrAppend("TPD_CARATT_DEF") == 1)
      {
        
      }
      else
      {
        $this->truncateTable("ps_layered_filter");
        $this->truncateTable("ps_feature");
        $this->truncateTable("ps_feature_lang");
        $this->truncateTable("ps_feature_shop");
      }

      // Inserisco le intestazioni delle caratteristiche nelle tabelle feature - feature_lang - feature_shop
      $position = 1;
      $featuresGrouped = Array();

      foreach ($arrayCaratteristiche as $caratteristica)
      {
        $data = explode("§", $caratteristica);
        //$this -> insertAttributeGroup($data);
        $featuresGrouped = $this->insertFeature($data, $position, $featuresGrouped);
        $position++;
      }

      // ora inserisco i valori per questi attributi, e le associazioni prodotti - attributi
      if (!file_exists(FOLDER_UNZIP . "TPD_CARATT_VAL.txt"))
      {
        $this->fileLog("#12 ERROR FILES VALORI CARATTERISTICHE NON PRESENTE.");
      }
      else
      {
        // ora inserisco i valori per questi attributi, e le associazioni prodotti - attributi
        $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TPD_CARATT_VAL.txt");
        $arrayCaratteristicheProdotti = explode("\n", $content);

        if (!$this->checkInsertOrAppend("TPD_CARATT_VAL") == 1)
        {
          $this->truncateTable("ps_layered_filter");
          $this->truncateTable("ps_feature_product");
          $this->truncateTable("ps_feature_value");
          $this->truncateTable("ps_feature_value_lang");
        }

        $result_template = $this->getLayeredFilter();
        $precTemplateString = unserialize($result_template->filters);

        $idValueCarat = array();
        $indice = 1;
        foreach ($arrayCaratteristicheProdotti as $caratteristicaProdotto)
        {

          $data = explode("§", $caratteristicaProdotto);
          $data[1] = $featuresGrouped[$data[1]];
          $idCarat = $data[1];
          $idProduct = $this->getIdProductFromCode($data[2]);
          $valueCarat = trim($data[3]);

          //costruisco una struttura dati che contenga [idcaratteristica[valorecaratteristica => un valore univoco che poi diventerà l'id feature_lang]
          if (!isset($idValueCarat[$idCarat][$valueCarat]))
          {
            $idValueCarat[$idCarat][$valueCarat] = $indice;
            $indice++;
          }
          //Lancio l'insert per le righe dalle quali sono riuscito ad estrrarre l'id
          if (isset($idProduct) && !empty($idProduct))
          {
            $this->insertFeatureProduct($idProduct, $data, $idValueCarat[$idCarat][$valueCarat]);
          }
          else
          {
            $this->fileLog("#12: errore INSERT Caratteristiche prodotto, caratteristica id " . $data[0] . ": il prodotto " . $data[2] . " non esiste");
            continue;
          }
        }
        //Uso la struttura dati precedentemente create per riempire ps_feature_value e ps_features_value_lang
        foreach ($idValueCarat as $idCarat => $arrIdCart)
        {
          foreach ($idValueCarat[$idCarat] as $caratVal => $idVal)
          {
            $this->insertFeatureValue($idCarat, $idVal, $featuresGrouped);
            $this->insertFeatureValueLang($idVal, $caratVal);
          }
        }

        //Creazione Template Per il BlockLayeredNavigation
        $categoryArray = $this->getCategoryStringForLayeredTemplate();
        $templateString = array('categories' => $categoryArray, 'shop_list' => array(0 => 1,));
        foreach ($idValueCarat as $idCarat => $arrIdCart)
        {
          $templateString['layered_selection_feat_' . $idCarat] = $precTemplateString['layered_selection_feat_' . $idCarat];
        }

        foreach ($templateString as $key => $value)
        {
          if (!isset($precTemplateString[$key]))
          {
            $precTemplateString[$key] = $value;
          }
        }

        $serializedString = serialize($precTemplateString);
        $this->createLayeredFilter($serializedString, count($precTemplateString['categories']));
        if ($this->checkInsertOrAppend("TPD_CARATT_VAL") == 0)
        {
          //Riempimento tabella layered_category
          $this->truncateTable("ps_layered_category");
          foreach ($categoryArray as $category)
          {
            $this->insertLayeredCategory($category, $idValueCarat);
          }
        }
      }
    }
  }

  function realoadForCaratteristiche() {
    exec('/usr/bin/wget -O - -q -t 1 "' . URL_WGET_RELOAD_ATTRIBUTE . '" >/dev/null 2>&1');
    exec('/usr/bin/wget -O - -q -t 1 "' . URL_WGET_RELOAD_ATTRIBUTE_URL . '" >/dev/null 2>&1');
  }

  function insertFeatureValue($idCarat, $idVal, $featuresGrouped) {
    $db = new MySQL();
    $attributeCaratteristicaValue['id_feature_value'] = MySQL::SQLValue($idVal);
    $attributeCaratteristicaValue['id_feature'] = MySQL::SQLValue($featuresGrouped[$idCarat]);
    $attributeCaratteristicaValue['custom'] = MySQL::SQLValue(0);
    $db->InsertRow("ps_feature_value", $attributeCaratteristicaValue);
  }

  function insertFeatureValueLang($idVal, $caratVal) {
    foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
    {
      $db = new MySQL();
      $attributeCaratteristicaValueLang['id_feature_value'] = MySQL::SQLValue($idVal);
      $attributeCaratteristicaValueLang['value'] = MySQL::SQLValue($caratVal);
      $attributeCaratteristicaValueLang['id_lang'] = MySQL::SQLValue($ps_id_lang);
      $db->InsertRow("ps_feature_value_lang", $attributeCaratteristicaValueLang);
    }
  }

  function insertFeatureProduct($productID, $data, $id_value) {
    $db = new MySQL();
    $attributeCaratteristicaProd['id_feature'] = MySQL::SQLValue($data[1]);
    $attributeCaratteristicaProd['id_product'] = MySQL::SQLValue($productID);
    $attributeCaratteristicaProd['id_feature_value'] = MySQL::SQLValue($id_value);
    $db->InsertRow("ps_feature_product", $attributeCaratteristicaProd);
  }

  function insertFeature($data, $position, $caratteristicheInserite) {

    $db = new MySQL();

    $sql = "SELECT id_feature FROM ps_feature_lang WHERE lower(name) = '" . trim(strtolower($data[2])) . "'";

    $result = $db->QuerySingleRow($sql);
    if ((empty($result)) || (empty($result->id_feature)))
    {

      $attributeCategoria['id_feature'] = MySQL::SQLValue($data[0]);
      $attributeCategoria['position'] = MySQL::SQLValue($position);
      $db->InsertRow("ps_feature", $attributeCategoria);

      foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
      {
        $db = new MySQL();
        $attributeCategoriaLang['id_feature'] = MySQL::SQLValue($data[0]);
        $attributeCategoriaLang['id_lang'] = MySQL::SQLValue($ps_id_lang);
        $attributeCategoriaLang['name'] = MySQL::SQLValue($data[2]);
        $db->InsertRow("ps_feature_lang", $attributeCategoriaLang);
      }
      $db = new MySQL();
      $attributeCategoriaShop['id_feature'] = MySQL::SQLValue($data[0]);
      $attributeCategoriaShop['id_shop'] = MySQL::SQLValue(1);
      $db->InsertRow("ps_feature_shop", $attributeCategoriaShop);

      $caratteristicheInserite[$data[0]] = $data[0];
    }
    else
    {
      $caratteristicheInserite[$data[0]] = $result->id_feature;
    }
    return $caratteristicheInserite;
  }

  function getLayeredFilter() {
    $sql = "SELECT filters FROM ps_layered_filter WHERE name = 'VisionFilter'";
    $db = new MySQL();
    return $db->QuerySingleRow($sql);
  }

  function getCategoryStringForLayeredTemplate() {
    $db = new MySQL();
    $sql = "SELECT id_category FROM ps_category WHERE id_category > 1";
    $result = $db->Query($sql);
    while (!$db->EndOfSeek())
    {
      $row[] = $db->Row();
    }
    $categoryArray = array();
    foreach ($row as $id_category)
    {
      $categoryArray[] = $id_category->id_category;
    }
    return $categoryArray;
  }

  function insertLayeredCategory($category, $idValueCarat) {
    $db = new MySQL();
    foreach ($idValueCarat as $idCarat => $arrIdCart)
    {
      $attributeLayeredCategory['id_shop'] = MySQL::SQLValue(1);
      $attributeLayeredCategory['id_category'] = MySQL::SQLValue($category);
      $attributeLayeredCategory['id_value'] = MySQL::SQLValue($idCarat);
      $attributeLayeredCategory['type'] = MySQL::SQLValue("id_feature");
      $attributeLayeredCategory['position'] = MySQL::SQLValue($idCarat);
      $attributeLayeredCategory['filter_type'] = MySQL::SQLValue(0);
      $attributeLayeredCategory['filter_show_limit'] = MySQL::SQLValue(0);
      $db->InsertRow("ps_layered_category", $attributeLayeredCategory);
    }
  }

}
