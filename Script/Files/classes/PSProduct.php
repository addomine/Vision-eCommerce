<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AIPSConnector;

/**
 * Nome file tracciato contenente le informazioni del prodotto
 */
define("FILE_PRODUCT", "TPD_PRODOTTI");
/**
 * Nome file tracciato contenente le traduzionii del prodotto
 */
define("FILE_PRODUCT_LINGUA", "TPD_PRODOTTI_LINGUA");
/**
 * Variabile che abilita o meno la gestione delle taglie
 */
define("ENABLE_TAGLIE", 0);
/**
 * Stringa che delimita le taglie nel codice del prodotto
 */
define("DELIMITATORE_TAGLIE", "èéà");
/**
 * Flag che abilita l'opzione di associare il prodotto a tutte le sottocategorie
 * della categoria principale padre
 */
define("FILL_PARENT_CATEGORY", 1);
/**
 * Percorso in cui vengono caricati gli alelgati ai prodotti
 */
define("FOLDER_ATTACHMENT", PATH_ABSOLUTE_AICONNECTOR . "/download");

/**
 * Description of PSProduct
 *
 * @author ALLINITSRLS
 */
class PSProduct extends PSObject {

  /**
   * Metodo per la gestione dei prodotti passati da ERP
   * 
   * @return type
   */
  function getProduct() {
    if (!file_exists(FOLDER_UNZIP . FILE_PRODUCT . ".txt"))
    {
      $this->fileLog("#6 ERROR FILE PRODOTTI NON PRESENTE");
      return;
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_PRODUCT . ".txt");
      $array_product = explode("\n", $content);
      $update = $this->checkInsertOrAppend(FILE_PRODUCT);
      $this->truncateTable("ps_specific_price_priority");
      if ($update == 0)
      {
        $this->truncateTable("ps_product");
        $this->truncateTable("ps_product_lang");
        $this->truncateTable("ps_product_shop");
        $this->truncateTable("ps_category_product");
        $this->truncateTable("ps_stock_available");
        $this->truncateTable("ps_image");
        $this->truncateTable("ps_image_lang");
        $this->truncateTable("ps_image_shop");
        $this->truncateTable("erp_image_group");
        $this->truncateTable("ps_attachment_lang");
        $this->truncateTable("ps_attachment");
        $this->truncateTable("ps_product_attachment");
        $this->truncateTable("ps_product_attribute");
        $this->truncateTable("ps_product_attribute_combination");
        $this->truncateTable("ps_product_attribute_shop");
        $this->truncateTable("ps_attribute");
        $this->truncateTable("ps_attribute_group");
        $this->truncateTable("ps_attribute_group_lang");
        $this->truncateTable("ps_attribute_group_shop");
        $this->truncateTable("ps_attribute_impact");
        $this->truncateTable("ps_attribute_lang");
        $this->truncateTable("ps_attribute_shop");
      }

      if (ENABLE_TAGLIE == 1)
      {
        $this->fileLog("#pre6: Controllo Gestione Taglie");
        $this->id_attr_group = $this->insertAttributeGroup();
        $this->insertAttributeGroupLang($this->id_attr_group);
        $this->insertAttributeGroupToShop($this->id_attr_group);
        $this->fileLog("#pre6: Gestione taglie ... done");
      }

      $arrLingua = $this->getProductLang();

      foreach ($array_product as $single_product)
      {
        $data = explode("§", $single_product);
        if (isset($data[0]) and ! empty($data[0]))
        {
          # faccio lo split in base al delimitatore per la gestione delle taglie
          $array_combination = explode(DELIMITATORE_TAGLIE, $data[0]);
          if (count($array_combination) > 1)
          {
            // vuol dire che è un prodotto con una combinazione
            $code_product = $array_combination[0];
            $first_attr = $array_combination[1];
            $has_combination = true;
          }
          else
          {
            # in questo caso nel codice del prodotto non è presente il delimitatore, di conseguenza il codice vero  tutto senza split
            $code_product = $data[0];
            $has_combination = false;
          }
          $id_product = $this->existsProduct($code_product);
          if ($id_product == 0)
          {
            $id_product = $this->insertProduct($data, $code_product, $has_combination);
            $this->insertProductLang($data, $id_product, $arrLingua[$data[0]]);
            $this->insertProductShop($data, $id_product);
            $this->insertStockProduct($data, $id_product);
            $this->insertAttachment(TRUE, $id_product, $data[7], $data[8]);
          }
          else
          {
            $this->updateProduct($data, $id_product, $code_product, $has_combination);
            $this->updateProductLang($data, $id_product, $arrLingua[$data[0]]);
            $this->updateProductShop($data, $id_product);
            $this->updateStockProduct($data, $id_product);
            $this->insertAttachment(FALSE, $id_product, $data[7], $data[8]);
          }

          if ($has_combination)
          {
            $id_attribute = $this->checkIfAttributeExists($first_attr);
            if ($id_attribute == 0)
            {
              $id_attribute = $this->insertAttribute($this->id_attr_group);
              $this->insertAttributeShop($id_attribute);
              $this->insertLangAttribute($first_attr, $id_attribute);
            }
            $id_product_attribute = $this->insertAttributeToProduct($id_product, $data);
            $this->insertValueAttributeShio($id_product_attribute, $data, $id_product);
            $this->insertStockAttrProduct($id_product_attribute, $data, $id_product);
            $this->insertProductCombination($id_attribute, $id_product_attribute);
            $this->setProductPriceZero($id_product);
          }

          $this->adjustSpecifiRule($id_product);
          if (!empty($data[3]))
          {
            $this->produttori[$code_product] = $data[3];
          }
          // cancello le vecchie immagini SE E SOLO SE
          //  1) nel tracciato dati non ci sono immagini allegate
          //  2) esiste almeno uno dei file fisici presenti nel tracciato
          if (empty($data[9]) and empty($data[11]) and empty($data[13]) and empty($data[15]) and empty($data[16]) and empty($data[17]) and empty($data[18]) and empty($data[19]))
          {
            $this->resetImages($id_product);
            $this->reinitProductImage($id_product);
          }
          else
          {
            if (
                    (file_exists(FOLDER_UNZIP . $data[9]) and ! empty($data[9])) or ( file_exists(FOLDER_UNZIP . $data[11]) and ! empty($data[11])) or ( file_exists(FOLDER_UNZIP . $data[13]) and ! empty($data[13])) or ( file_exists(FOLDER_UNZIP . $data[15]) and ! empty($data[15])) or ( file_exists(FOLDER_UNZIP . $data[16]) and ! empty($data[16])) or ( file_exists(FOLDER_UNZIP . $data[17]) and ! empty($data[17])) or ( file_exists(FOLDER_UNZIP . $data[18]) and ! empty($data[18])) or ( file_exists(FOLDER_UNZIP . $data[19]) and ! empty($data[19]))
            )
            {
              $this->resetImages($id_product);
              $this->reinitProductImage($id_product);
            }
          }
          if (!empty($data[9]) && file_exists(FOLDER_UNZIP . $data[9]))
          {

            $this->insertProductImage2($data[9], 1, $id_product);
          }
          if (!empty($data[11]) && file_exists(FOLDER_UNZIP . $data[11]))
          {

            $this->insertProductImage2($data[11], 2, $id_product);
          }
          if (!empty($data[13]) && file_exists(FOLDER_UNZIP . $data[13]))
          {

            $this->insertProductImage2($data[13], 3, $id_product);
          }
          if (!empty($data[15]) && file_exists(FOLDER_UNZIP . $data[15]))
          {

            $this->insertProductImage2($data[15], 4, $id_product);
          }
          if (!empty($data[16]) && file_exists(FOLDER_UNZIP . $data[16]))
          {

            $this->insertProductImage2($data[16], 5, $id_product);
          }
          if (!empty($data[17]) && file_exists(FOLDER_UNZIP . $data[17]))
          {

            $this->insertProductImage2($data[17], 6, $id_product);
          }
          if (!empty($data[18]) && file_exists(FOLDER_UNZIP . $data[18]))
          {

            $this->insertProductImage2($data[18], 7, $id_product);
          }
          if (!empty($data[19]) && file_exists(FOLDER_UNZIP . $data[19]))
          {

            $this->insertProductImage2($data[19], 8, $id_product);
          }
        }
      }
      $this->insertProduttori();
    }
  }

  function getRelatedProducts() {
    if (!file_exists(FOLDER_UNZIP . "TPD_PRODOTTI_PRODOTTI_REL.txt"))
    {
      $this->fileLog("#14 ERROR FILE PRODOTTI CORRELATI NON PRESENTE.");
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . "TPD_PRODOTTI_PRODOTTI_REL.txt");
      $arrayPaymentMethod = explode("\n", $content);
      if (!$this->checkInsertOrAppend("TPD_PRODOTTI_PRODOTTI_REL") == 1)
      {
        $this->truncateTable("ps_accessory");
        $this->truncateTable("ps_visioncrossselling");
      }
      foreach ($arrayPaymentMethod as $paymentMethod)
      {
        $data = explode("§", $paymentMethod);
        $this->insertRelatedProduct($data);
      }
    }
  }

  /**
   * Se non presente attributo taglie, inserisco attributo e recupero id per aggiornare le altre tabelle
   * 
   * @return Int Id attributo 
   */
  function insertAttributeGroup() {
    $id_attr_group = $this->checkAttributeGroupSet();
    if ($id_attr_group == 0)
    {
      $values = null;
      $values['is_color_group'] = MySQL::SQLValue(0);
      $values['position'] = MySQL::SQLValue(0);
      $db = new MySQL();
      $id_attrubte_group = $db->InsertRow("ps_attribute_group", $values);
      return $id_attrubte_group;
    }
    else
    {
      return $id_attr_group;
    }
  }

  /**
   * Controllo se già configurato attributo gruppo per la gestione delle taglie
   * 
   * @return int Id del gruppo
   */
  function checkAttributeGroupSet() {
    $sql = "SELECT * FROM ps_attribute_group WHERE is_color_group = 0 AND group_type = 'select' AND position = 0";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->id_attribute_group;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Inserisco note attributo per ogni lingua dell'ecommerce
   * 
   * @param type $id_attribute_group
   */
  function insertAttributeGroupLang($id_attribute_group) {
    foreach ($this->ecommerce_lang_by_erp as $iso_code => $id_lang)
    {
      $values = null;
      $values['id_attribute_group'] = MySQL::SQLValue($id_attribute_group);
      $values['id_lang'] = MySQL::SQLValue($id_lang);
      $values['name'] = MySQL::SQLValue("Misura");
      $values['public_name'] = MySQL::SQLValue("Misura");
      $db = new MySQL();
      $db->InsertRow('ps_attribute_group_lang', $values);
    }
  }

  /**
   * Metodo utilizzato per inserire gli attributi negli shop definiti in Prestashop
   * 
   * @param Int $id_attribute_group Id del gruppo
   */
  function insertAttributeGroupToShop($id_attribute_group) {
    $values = null;
    $values['id_attribute_group'] = MySQL::SQLValue($id_attribute_group);
    $values['id_shop'] = MySQL::SQLValue(1);
    $db = new MySQL();
    $db->InsertRow('ps_attribute_group_shop', $values);
  }

  /**
   * Metodo che recupera le etichette dei prodotti nelle varie lingue
   * 
   * @return Array Struttura contenente le traduzioni delle etichette dei prodotti
   */
  function getProductLang() {
    $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_PRODUCT_LINGUA . ".txt");
    $array_product_lang = explode("\n", $content);
    foreach ($array_product_lang as $prod_lang)
    {
      $data = explode("§", $prod_lang);
      $lang[$data[0]][$data[1]]['name'] = $data[2];
      $lang[$data[0]][$data[1]]['description'] = $data[3];
    }
    return $lang;
  }

  /**
   * Metodo utilizzato per recuperare se esiste o meno il prodotto
   * 
   * @param String $code Codice identificativo del prodotto
   * @return int Id del prodotto. Se 0 il prodotto non esiste
   */
  function existsProduct($code) {
    $query = "SELECT id_product FROM ps_product WHERE code = '{$code}'";
    $db = new MySQL();
    $result = $db->QuerySingleRow($query);
    if ($db->RowCount() > 0)
    {
      return $result->id_product;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Metodo per inserire un prodotto all'interno del database di Prestashop
   * 
   * @param Array $arrPrd Array dei dati del prodotto passati dal tracciato
   * @param String $code_product Codice del prodotto
   * @param Boolean $has_combination Flag che identifica se il prodotto ha combinazioni oppure no
   * @return Int Id del prodotto appena creato
   */
  function insertProduct($arrPrd, $code_product, $has_combination) {
    $values['code'] = MySQL::SQLValue($code_product);
    $values['id_supplier'] = MySQL::SQLValue(0);
    $values['id_manufacturer'] = MySQL::SQLValue(0);
    $values['id_shop_default'] = MySQL::SQLValue(1);
    $values['id_tax_rules_group'] = MySQL::SQLValue(1);
    $values['on_sale'] = MySQL::SQLValue(0);
    $values['online_only'] = MySQL::SQLValue(0);
    $values['ean13'] = MySQL::SQLValue(0);
    $values['upc'] = MySQL::SQLValue("");
    $values['ecotax'] = MySQL::SQLValue("0");
    $values['weight'] = MySQL::SQLValue(str_replace(",", ".", $arrPrd[22]));
    $values['wholesale_price'] = MySQL::SQLValue(0);
    if (isset($arrPrd[47]) and ! empty($arrPrd[47]))
    {
      $values['unity'] = MySQL::SQLValue($arrPrd[47]);
    }
    else
    {
      $values['unity'] = MySQL::SQLValue("");
    }
    $values['unit_price_ratio'] = MySQL::SQLValue(0);
    $values['additional_shipping_cost'] = MySQL::SQLValue(0);
    $values['reference'] = MySQL::SQLValue($code_product);
    $values['supplier_reference'] = MySQL::SQLValue("");
    $values['location'] = MySQL::SQLValue("");
    $values['qt_conf'] = MySQL::SQLValue($arrPrd[45]);
    if (!empty($arrPrd[46]) and is_numeric($arrPrd[46]))
    {
      $values['minimal_quantity'] = MySQL::SQLValue($arrPrd[46]);
    }
    else
    {
      $values['minimal_quantity'] = MySQL::SQLValue(0);
    }

    $values['width'] = MySQL::SQLValue(0);
    $values['height'] = MySQL::SQLValue(0);
    $values['depth'] = MySQL::SQLValue(0);
    $values['out_of_stock'] = MySQL::SQLValue(2);
    $values['quantity_discount'] = MySQL::SQLValue(0);
    $values['customizable'] = MySQL::SQLValue(0);
    $values['uploadable_files'] = MySQL::SQLValue(0);
    $values['text_fields'] = MySQL::SQLValue(0);
    $values['active'] = MySQL::SQLValue(1);
    $values['redirect_type'] = MySQL::SQLValue(" ");
    $values['id_product_redirected'] = MySQL::SQLValue(0);
    $values['available_for_order'] = MySQL::SQLValue(1);
    $values['available_date'] = MySQL::SQLValue("0000-00-00");
    $values['condition'] = MySQL::SQLValue("new");
    $values['show_price'] = MySQL::SQLValue(1);
    $values['indexed'] = MySQL::SQLValue(1);
    $values['visibility'] = MySQL::SQLValue("both");
    $values['cache_is_pack'] = MySQL::SQLValue(0);
    $values['cache_has_attachments'] = MySQL::SQLValue(1);
    $values['is_virtual'] = MySQL::SQLValue(0);
    $values['cache_default_attribute'] = MySQL::SQLValue(0);
    $values['advanced_stock_management'] = MySQL::SQLValue(0);
    $values['prGroup'] = MySQL::SQLValue($arrPrd[49]);
    $values['qt_multiple'] = MySQL::SQLValue($arrPrd[48]);
    if (isset($arrPrd[47]) and ! empty($arrPrd[47]))
    {
      $values['DSunitaMisura'] = MySQL::SQLValue($arrPrd[47]);
    }
    else
    {
      $values['DSunitaMisura'] = MySQL::SQLValue("");
    }

    if (empty($arrPrd[27]))
    {
      $values['offer'] = MySQL::SQLValue(0);
    }
    else
    {
      $values['offer'] = MySQL::SQLValue($arrPrd[27]);
    }
    if (empty($arrPrd[29]))
    {
      $values['discontinued'] = MySQL::SQLValue(0);
    }
    else
    {
      $values['discontinued'] = MySQL::SQLValue($arrPrd[29]);
    }
    if ($arrPrd[25] == "1")
    {
      $now = date("Y-m-d H:i:s");
    }
    else
    {
      $now = date('Y-m-d H:i:s', strtotime("-300 days"));
    }
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $id_category_default = $this->getCategoryIdFromCode(trim($arrPrd[1]));
    if (isset($id_category_default) and ! empty($id_category_default))
    {
      $values['id_category_default'] = MySQL::SQLValue($id_category_default);
    }
    else
    {
      $values['id_category_default'] = MySQL::SQLValue(0);
    }

    if (!empty($arrPrd[43]) and is_numeric($arrPrd[43]))
    {
      $values['quantity'] = MySQL::SQLValue($arrPrd[43]);
    }
    else
    {
      $values['quantity'] = MySQL::SQLValue(0);
    }
    if (!empty($arrPrd[50]))
    {
      $values['scorta_min'] = MySQL::SQLValue($arrPrd[50]);
    }
    else
    {
      $values['scorta_min'] = MySQL::SQLValue(0);
    }
    if ($has_combination)
    {
      $values['price'] = MySQL::SQLValue(0);
    }
    else
    {
      $values['price'] = MySQL::SQLValue(str_replace(",", ".", $arrPrd[20]));
    }

    $db = new MySQL();
    $last_id = $db->InsertRow("ps_product", $values);
    if (FILL_PARENT_CATEGORY == 1)
    {
      if (isset($id_category_default) and ! empty($id_category_default))
      {
        $this->insertCategoryInAllTree($last_id, $id_category_default);
      }
    }
    return $last_id;
  }

  /**
   * Restituisce id della categoria a partire dal CODE Vision
   * 
   * @param String $code Codice della categoria ERP
   * @return int Id della categoria
   */
  function getCategoryIdFromCode($code) {
    $db = new MySQL();
    $query = "SELECT id_category FROM ps_category WHERE code = '{$code}'";
    $result = $db->QuerySingleRow($query);
    if (isset($result->id_category))
    {
      return $result->id_category;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Metodo per inserire il prodotto in tutte le categorie e sottocategorie
   * 
   * @param Int $idProduct Id del prodotto da inserire nelle sotto categorie
   * @param Int $id_category_default Id della categoria genitore 
   */
  function insertCategoryInAllTree($idProduct, $id_category_default) {
    if ($id_category_default > 0)
    {
      $arrayCatParent = $this->getArrayCatParent($id_category_default);
      if (!empty($arrayCatParent))
      {
        foreach ($arrayCatParent as $catParent)
        {
          $this->insertCategoryProduct($idProduct, $catParent->id_category);
        }
      }
    }
  }

  /**
   * Metodo utilizzato per l'array delle categorie di apaprtenenza
   * 
   * @param Int $id_category Id della categoria
   * @return Array Array di Strutture in ps_category
   */
  function getArrayCatParent($id_category) {
    if ($id_category > 0)
    {
      $nLnR = $this->getNleftNrightCategory($id_category);
      if ($nLnR != 0)
      {
        $sql = "SELECT * FROM `ps_category` WHERE nleft < {$nLnR->nleft} and nright > {$nLnR->nright}";
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
      else
      {
        return 0;
      }
    }
  }

  /**
   * Metodo per inserire il prodotto in ogni categoria di appartenenza
   * 
   * @param Int $idProduct Id del prodotto
   * @param Int $idCategory Id della categoria
   */
  function insertCategoryProduct($idProduct, $idCategory) {
    $values['id_category'] = MySQL::SQLValue($idCategory);
    $values['id_product'] = MySQL::SQLValue($idProduct);
    $values['position'] = MySQL::SQLValue(0);
    $db = new MySQL();
    $db->InsertRow("ps_category_product", $values);
  }

  /**
   * Metodo utilizzato per recuperare nleft e nright di ogni categoria
   * 
   * @param Int $id_category Id della categoria
   * @return int 
   */
  function getNleftNrightCategory($id_category) {
    if ($id_category > 0)
    {
      $sql = "SELECT nleft, nright FROM ps_category WHERE id_category = {$id_category}";
      $db = new MySQL();
      $result = $db->QuerySingleRow($sql);
      if ($db->RowCount() > 0)
      {
        return $result;
      }
      else
      {
        return 0;
      }
    }
  }

  /**
   * Metodo utilizzato per inserire le etichette per i prodotti
   * 
   * @param Array $arrPrd Array dei prodotti passati dal tracciato ERP
   * @param Id $idProduct Id del prodotto inserito in ps_product
   * @param Array $lang Array associativo contenente le localizzazioni del prodotto
   */
  function insertProductLang($arrPrd, $idProduct, $lang) {
    foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
    {
      $values['id_product'] = MySQL::SQLValue($idProduct);
      $values['id_shop'] = MySQL::SQLValue(1);
      $values['id_lang'] = MySQL::SQLValue($ps_id_lang);

      $values['meta_description'] = MySQL::SQLValue("");
      $values['meta_keywords'] = MySQL::SQLValue($arrPrd[5]);

      if (!empty($arrPrd[6]))
      {
        $values['description_short'] = MySQL::SQLValue($arrPrd[6]);
      }
      else
      {
        $values['description_short'] = MySQL::SQLValue("");
      }

      if (!empty($lang[$erp_lang]))
      {
        if (!empty($lang[$erp_lang]['name']))
        {
          $values['meta_title'] = MySQL::SQLValue($lang[$erp_lang]['name']);
          $values['name'] = MySQL::SQLValue($lang[$erp_lang]['name']);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($lang[$erp_lang]['name']));
        }
        else if (!empty($arrPrd[2]))
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[2]);
          $values['name'] = MySQL::SQLValue($arrPrd[2]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[2]));
        }
        else
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[0]);
          $values['name'] = MySQL::SQLValue($arrPrd[0]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[0]));
        }
        if (!empty($lang[$erp_lang]['description']))
        {
          $values['description'] = MySQL::SQLValue($lang[$erp_lang]['description']);
        }
        else if (!empty($arrPrd[4]))
        {
          $values['description'] = MySQL::SQLValue($arrPrd[4]);
        }
        else
        {
          $values['description'] = MySQL::SQLValue("");
        }
      }
      else
      {
        if (!empty($arrPrd[2]))
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[2]);
          $values['name'] = MySQL::SQLValue($arrPrd[2]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[2]));
        }
        else
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[0]);
          $values['name'] = MySQL::SQLValue($arrPrd[0]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[0]));
        }
        if (!empty($arrPrd[4]))
        {
          $values['description'] = MySQL::SQLValue($arrPrd[4]);
        }
        else
        {
          $values['description'] = MySQL::SQLValue("");
        }
      }
      $values['available_now'] = MySQL::SQLValue("");
      $values['available_later'] = MySQL::SQLValue("");
      $db = new MySQL();
      $db->InsertRow("ps_product_lang", $values);
    }
  }

  /**
   * Metodo per inserire il prodotto all'interno degli shop configurati in Prestashop
   * 
   * @param Array $arrPrd Array prodotto dei dati del tracciato ERP
   * @param Int $idProduct Id del prodotto
   */
  function insertProductShop($arrPrd, $idProduct) {
    $id_category = $this->getCategoryIdFromCode(trim($arrPrd[1]));
    $values['id_product'] = MySQL::SQLValue($idProduct);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['id_category_default'] = MySQL::SQLValue($id_category);
    $values['id_tax_rules_group'] = MySQL::SQLValue(1);
    $values['on_sale'] = MySQL::SQLValue(0);
    $values['online_only'] = MySQL::SQLValue(0);
    $values['ecotax'] = MySQL::SQLValue("0");
    if (!empty($arrPrd[46]) and is_numeric($arrPrd[46]))
    {
      $values['minimal_quantity'] = MySQL::SQLValue($arrPrd[46]);
    }
    else
    {
      $values['minimal_quantity'] = MySQL::SQLValue(0);
    }
    $values['price'] = MySQL::SQLValue(str_replace(",", ".", $arrPrd[20]));
    $values['wholesale_price'] = MySQL::SQLValue(0);
    $values['unity'] = MySQL::SQLValue("");
    $values['unit_price_ratio'] = MySQL::SQLValue(0);
    $values['additional_shipping_cost'] = MySQL::SQLValue(0);
    $values['customizable'] = MySQL::SQLValue(0);
    $values['uploadable_files'] = MySQL::SQLValue(0);
    $values['text_fields'] = MySQL::SQLValue(0);
    $values['active'] = MySQL::SQLValue(1);
    $values['redirect_type'] = MySQL::SQLValue(" ");
    $values['id_product_redirected'] = MySQL::SQLValue(0);
    $values['available_for_order'] = MySQL::SQLValue(1);
    $values['available_date'] = MySQL::SQLValue("0000-00-00");
    $values['condition'] = MySQL::SQLValue("new");
    $values['show_price'] = MySQL::SQLValue(1);
    $values['indexed'] = MySQL::SQLValue(1);
    $values['visibility'] = MySQL::SQLValue("both");
    $values['cache_default_attribute'] = MySQL::SQLValue(0);
    $values['advanced_stock_management'] = MySQL::SQLValue(0);
    if ($arrPrd[25] == "1")
    {
      $now = date("Y-m-d H:i:s");
    }
    else
    {
      $now = date('Y-m-d H:i:s', strtotime("-300 days"));
    }
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);

    $db = new MySQL();
    $db->InsertRow("ps_product_shop", $values);
    $this->insertCategoryProduct($idProduct, $id_category);
    if ($arrPrd[28] == "1")
    {
      $this->insertCategoryProduct($idProduct, 2);
    }
  }

  /**
   * Metodo utilizzato per inserire il prodotto all'interno dello stock del negozio
   * 
   * @param Array $arrPrd Array con i dati del prodotto fornito dal tracciato ERP
   * @param int $idProduct Id del prodotto
   */
  function insertStockProduct($arrPrd, $idProduct) {
    $values['id_product'] = MySQL::SQLValue($idProduct);
    $values['id_product_attribute'] = MySQL::SQLValue(0);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['id_shop_group'] = MySQL::SQLValue(0);
    if (!empty($arrPrd[43]) and is_numeric($arrPrd[43]))
    {
      $values['quantity'] = MySQL::SQLValue($arrPrd[43]);
    }
    else
    {
      $values['quantity'] = MySQL::SQLValue(0);
    }
    $values['depends_on_stock'] = MySQL::SQLValue(0);
    $values['out_of_stock'] = MySQL::SQLValue(2);
    $db = new MySQL();
    $db->InsertRow("ps_stock_available", $values);
  }

  /**
   * Metodo utilizzato per gestione allegati secondo le specifiche
   * 
   * @param Boolean $new Se TRUE prodotto nuovo, FALSE prodotto esistente
   * @param int $id_product Id prodotto
   * @param String $FlschedaTecnica Nome file della scheda tecnica
   * @param String $DSschedaTecnica Descrizione del file scehda tecnica
   */
  function insertAttachment($new, $id_product, $FlschedaTecnica, $DSschedaTecnica) {
    // prodotto nuovo
    if ($new)
    {
      if (empty($FlschedaTecnica) and empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #1 Il nuovo prodotto non ha file allegato
      }
      else if (!empty($FlschedaTecnica) and empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #3 Non viene inserito nessun allegato, perchè non presente il FILE_FISICO
      }
      else if (empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #5 NOn viene inserito nessun allegato, perchè non presente il FILE_FISICO
      }
      else if (empty($FlschedaTecnica) and empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #7 IMPOSSIBILE: il nome del file fisico è dato dal tracciato (FlschedaTecnica) se non presente non può mai essere trovato il file fisico
      }
      else if (!empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #9: Non viene inserito nessun allegato, perchè non presente il FILE_FISICO
      }
      else if (!empty($FlschedaTecnica) and empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #11: Viene inserito file allegato => Nome = FlSchedaTecnica; Descrizione = Stringa vuota per tutte le lingue configurate nel connector
        $this->newAttachment($id_product, $FlschedaTecnica, "");
      }
      else if (empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #13: IMPOSSIBILE: il nome del file fisico è dato dal tracciato (FlschedaTecnica) se non presente non può mai essere trovato il file fisico.
      }
      else if (!empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #15: Viene inserito allegato: Nome = FlSchedaTecnica; Descrizione = DsschedaTecnica; per ogni lingua e caricato il file fisico.
        $this->newAttachment($id_product, $FlschedaTecnica, $DSschedaTecnica);
      }
    }
    else
    {
      // prodotto in update
      if (empty($FlschedaTecnica) and empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #2 Se il prodotto già esistente aveva un file allegato, viene cancellato
        $this->deletePrecAttachment($id_product);
      }
      else if (!empty($FlschedaTecnica) and empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #4 Viene aggiornato il nome del file allegato. Per nome si intende non il nome del file fisico scaricato, ma il nome visualizzato nelal scheda del prodotto
        $this->updateNameAttachment($id_product, $FlschedaTecnica);
      }
      else if (empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #6 Viene aggiornata la descrizione del file allegato al prodotto per tutte le lingue
        $this->updateDescriptionAttachment($id_product, $DSschedaTecnica);
      }
      else if (empty($FlschedaTecnica) and empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #8 IMPOSSIBILE: il nome del file fisico è dato dal tracciato (FlschedaTecnica) se non presente non può mai essere trovato il file fisico
      }
      else if (!empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and ! file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #10: Vengono aggiornati il nome del file fisico e la descrizione del file allegato
        $this->updateNameAttachment($id_product, $FlschedaTecnica);
        $this->updateDescriptionAttachment($id_product, $DSschedaTecnica);
      }
      else if (!empty($FlschedaTecnica) and empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #12: Viene aggiornato o inserito il file allegato: Nome = FlSchedaTecnica; Descrizione = Stringa vuota; AGGIORNO/INSERISCO FILE_FISICO
        $this->checkAttachment($id_product, $FlschedaTecnica, "");
      }
      else if (empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #14: IMPOSSIBILE: il nome del file fisico è dato dal tracciato (FlschedaTecnica) se non presente non può mai essere trovato il file fisico.
      }
      else if (!empty($FlschedaTecnica) and ! empty($DSschedaTecnica) and file_exists(FOLDER_UNZIP . $FlschedaTecnica))
      {
        // caso #16: Viene aggiornato o inserito il file allegato: Nome = FlSchedaTecnica; Descrizione = DsschedaTecnica; per ogni lingua e inserito / aggioranto il file fisico.
        $this->checkAttachment($id_product, $FlschedaTecnica, $DSschedaTecnica);
      }
    }
  }

  /**
   * Aggiungo un nuovo allegato al prodotto ed elimino eventuali collegamenti precedenti
   * 
   * @param int $id_product Id del prodotto
   * @param String $file_name Nome del file allegato
   * @param String $description Descrizione del file allegato
   */
  function newAttachment($id_product, $file_name, $description) {
    // inserisco l'attachment
    $file = md5($id_product . $file_name);
    $db_1 = new MySQL();
    $values = null;
    $values["file"] = MySQL::SQLValue($file);
    $values["file_name"] = MySQL::SQLValue($file_name);
    $values["mime"] = MySQL::SQLValue($this->getMimeFile($file_name));
    $id_attachment = $db_1->InsertRow("ps_attachment", $values);
    // inserisco per ogni linuga dell'ecommerce le descrizioni e i nomi file
    foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
    {
      $db = new MySQL();
      $values_lang = null;
      $values_lang["id_attachment"] = MySQL::SQLValue($id_attachment);
      $values_lang["id_lang"] = MySQL::SQLValue($ps_id_lang);
      $values_lang["name"] = MySQL::SQLValue($file_name);
      $values_lang["description"] = MySQL::SQLValue($description);
      $db->InsertRow("ps_attachment_lang", $values_lang);
    }
    // elimino eventuali collegamenti precedenti
    $sql_delete = "DELETE FROM ps_product_attachment WHERE id_product = {$id_product} AND id_attachment = {$id_attachment}";
    $db = new MySQL();
    $db->Query($sql_delete);
    // collego l'allegato al prodotto
    $values_prd = null;
    $values_prd["id_product"] = MySQL::SQLValue($id_product);
    $values_prd["id_attachment"] = MySQL::SQLValue($id_attachment);
    $db->InsertRow("ps_product_attachment", $values_prd);
    // copio il FILE FISICO nella posizione corretta
    copy(FOLDER_UNZIP . $file_name, FOLDER_ATTACHMENT . "" . $file);
  }

  /**
   * Cancellazione file allegato al prodotto
   * 
   * @param INT $id_product Id del prodotto
   */
  function deletePrecAttachment($id_product) {
    $sql = "SELECT * FROM ps_product_attachment WHERE id_product = {$id_product}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      $id_attachment = $result->id_attachment;
      // recupero nome FILE FISICO di PRESTASHOP per poi cancellarlo
      $sql_query_fs = "SELECT * FROM ps_attachment WHERE `id_attachment` = {$id_attachment} ";
      $db_fs = new MySQL();
      $result_fs = $db_fs->QuerySingleRow($sql_query_fs);
      if ($db_fs->RowCount() > 0)
      {
        $file_fisico = $result_fs->file;
        if (file_exists(FOLDER_ATTACHMENT . $file_fisico))
        {
          unlink(FOLDER_ATTACHMENT . $file_fisico);
        }
      }
      // è presente un record quindi procedo alla cancellazione
      $sql_first_delete = "DELETE FROM ps_attachment WHERE id_attachment = {$id_attachment}";
      $db_1 = new MySQL();
      $db_1->Query($sql_first_delete);
      $sql_second_delete = "DELETE FROM ps_attachment_lang WHERE id_attachment = {$id_attachment}";
      $db_2 = new MySQL();
      $db_2->Query($sql_second_delete);
      $sql_third_delete = "DELETE FROM ps_product_attachment WHERE id_attachment = {$id_attachment}";
      $db = new MySQL();
      $db->Query($sql_third_delete);
    }
  }

  /**
   * Update SOLO nome file allegato
   * 
   * @param int $id_product Id del prodotto
   * @param String $file_name Nome file allegato
   */
  function updateNameAttachment($id_product, $file_name) {
    $sql = "SELECT * FROM ps_product_attachment WHERE id_product = {$id_product}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      $id_attachment = $result->id_attachment;
      // aggiorno il nome in ps_attachment
      $values_1 = null;
      $values_1["file_name"] = MySQL::SQLValue($file_name);
      $where_1["id_attachment"] = MySQL::SQLValue($id_attachment);
      $db_u1 = new MySQL();
      $db_u1->UpdateRows("ps_attachment", $values_1, $where_1);
      // aggiorno il nome nelle lingue
      $values_2 = null;
      $values_2["name"] = MySQL::SQLValue($file_name);
      $where_2["id_attachment"] = MySQL::SQLValue($id_attachment);
      $db_u = new MySQL();
      $db_u->UpdateRows("ps_attachment_lang", $values_2, $where_2);
    }
  }

  /**
   * Update SOLO descrizione file allegato
   * 
   * @param int $id_product Id del prodotto
   * @param String $description Descrizione file allegato da aggiornare
   */
  function updateDescriptionAttachment($id_product, $description) {
    $sql = "SELECT * FROM ps_product_attachment WHERE id_product = {$id_product}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      $id_attachment = $result->id_attachment;
      // aggiorno la descrizione nelle lingue
      $values_2 = null;
      $values_2["description"] = MySQL::SQLValue($description);
      $where_2["id_attachment"] = MySQL::SQLValue($id_attachment);
      $db_u = new MySQL();
      $db_u->UpdateRows("ps_attachment_lang", $values_2, $where_2);
    }
  }

  /**
   * Faccio il check per capire se c'era o meno il file allegato da aggiornare
   * 
   * @param int $id_product Id del prodotto
   * @param String $file_name Nome del file allegato
   * @param String $description Descrizione del file allegato
   */
  function checkAttachment($id_product, $file_name, $description) {
    $sql = "SELECT * FROM ps_product_attachment WHERE id_product = {$id_product}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      $id_attachment = $result->id_attachment;
      // recupero il nome del file fisico per aggiornare il file da scaricare
      $sql_query_fs = "SELECT * FROM ps_attachment WHERE `id_attachment` = {$id_attachment} ";
      $db_fs = new MySQL();
      $result_fs = $db_fs->QuerySingleRow($sql_query_fs);
      if ($db_fs->RowCount() > 0)
      {
        $file_fisico = $result_fs->file;
        // copio il nuovo file sul vecchio
        // se il fiel esiste già viene sovrascritto (come da documentazione PHP)
        copy(FOLDER_UNZIP . $file_name, FOLDER_ATTACHMENT . "" . $file_fisico);
      }
      // aggiorno nome file
      $this->updateNameAttachment($id_product, $file_name);
      // aggiorno la descrizione
      $this->updateDescriptionAttachment($id_product, $description);
    }
    else
    {
      // il prodotto non aveva nessun file allegato, quindi lo aggiungo
      $this->newAttachment($id_product, $file_name, $description);
    }
  }

  /**
   * Metodo che aggiorna prodotto esistente
   * 
   * @param Array $arrPrd Array contenente i dati del prodotto dal tracciato ERP
   * @param int $id_product Id del prodotto
   * @param String $code_product Codice del prodotto
   * @return int Id del prodotto aggiornato
   */
  function updateProduct($arrPrd, $id_product, $code_product, $has_combination) {
    $values['code'] = MySQL::SQLValue($code_product);
    $values['id_supplier'] = MySQL::SQLValue(0);
    $values['id_manufacturer'] = MySQL::SQLValue(0);
    $values['id_shop_default'] = MySQL::SQLValue(1);
    $values['id_tax_rules_group'] = MySQL::SQLValue(1);
    $values['on_sale'] = MySQL::SQLValue(0);
    $values['online_only'] = MySQL::SQLValue(0);
    $values['ean13'] = MySQL::SQLValue(0);
    $values['upc'] = MySQL::SQLValue("");
    $values['ecotax'] = MySQL::SQLValue("0");
    $values['prGroup'] = MySQL::SQLValue($arrPrd[49]);
    if (!empty($arrPrd[46]) and is_numeric($arrPrd[46]))
    {
      $values['minimal_quantity'] = MySQL::SQLValue($arrPrd[46]);
    }
    else
    {
      $values['minimal_quantity'] = MySQL::SQLValue(0);
    }
    $values['wholesale_price'] = MySQL::SQLValue(0);
    $values['unity'] = MySQL::SQLValue("");
    $values['unit_price_ratio'] = MySQL::SQLValue(0);
    $values['additional_shipping_cost'] = MySQL::SQLValue(0);
    $values['reference'] = MySQL::SQLValue($code_product);
    $values['supplier_reference'] = MySQL::SQLValue("");
    $values['location'] = MySQL::SQLValue("");
    $values['width'] = MySQL::SQLValue(0);
    $values['height'] = MySQL::SQLValue(0);
    $values['depth'] = MySQL::SQLValue(0);
    $values['out_of_stock'] = MySQL::SQLValue(2);
    $values['quantity_discount'] = MySQL::SQLValue(0);
    $values['customizable'] = MySQL::SQLValue(0);
    $values['uploadable_files'] = MySQL::SQLValue(0);
    $values['text_fields'] = MySQL::SQLValue(0);
    $values['active'] = MySQL::SQLValue(1);
    $values['redirect_type'] = MySQL::SQLValue(" ");
    $values['id_product_redirected'] = MySQL::SQLValue(0);
    $values['available_for_order'] = MySQL::SQLValue(1);
    $values['available_date'] = MySQL::SQLValue("0000-00-00");
    $values['condition'] = MySQL::SQLValue("new");
    $values['show_price'] = MySQL::SQLValue(1);
    $values['indexed'] = MySQL::SQLValue(1);
    $values['visibility'] = MySQL::SQLValue("both");
    $values['cache_is_pack'] = MySQL::SQLValue(0);
    $values['cache_has_attachments'] = MySQL::SQLValue(1);
    $values['is_virtual'] = MySQL::SQLValue(0);
    $values['weight'] = MySQL::SQLValue(str_replace(",", ".", $arrPrd[22]));
    $values['cache_default_attribute'] = MySQL::SQLValue(0);
    $values['advanced_stock_management'] = MySQL::SQLValue(0);
    if ($arrPrd[25] == "1")
    {
      $now = date("Y-m-d H:i:s");
    }
    else
    {
      $now = date('Y-m-d H:i:s', strtotime("-300 days"));
    }
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $id_category_default = $this->getCategoryIdFromCode(trim($arrPrd[1]));
    if (isset($id_category_default) and ! empty($id_category_default))
    {
      $values['id_category_default'] = MySQL::SQLValue($id_category_default);
    }
    else
    {
      $this->fileLog("#6 ERROR CODICE CATEGORIA DEL PRODOTTO NON CORRETTO (" . $arrPrd[0] . ")");
      $values['id_category_default'] = MySQL::SQLValue(0);
    }

    if (!empty($arrPrd[43]) and is_numeric($arrPrd[43]))
    {
      $values['quantity'] = MySQL::SQLValue($arrPrd[43]);
    }
    else
    {
      $this->fileLog("#6 ERROR QUANTITA' NON CORRETTA VUOTA O NON INTERO (" . $arrPrd[0] . ")");
      $values['quantity'] = MySQL::SQLValue(0);
    }
    if (!empty($arrPrd[50]))
    {
      $values['scorta_min'] = MySQL::SQLValue($arrPrd[50]);
    }
    else
    {
      $this->fileLog("#6 ERROR SCORTA MINIMA NON CORRETTA VUOTA O NON INTERO (" . $arrPrd[0] . ") VALORE RILAVATO = " . $arrPrd[50]);
      $values['scorta_min'] = MySQL::SQLValue(0);
    }
    if ($has_combination)
    {
      $values['price'] = MySQL::SQLValue(0);
    }
    else
    {
      $values['price'] = MySQL::SQLValue(str_replace(",", ".", $arrPrd[20]));
    }
    $values['qt_conf'] = MySQL::SQLValue($arrPrd[45]);
    $values['qt_multiple'] = MySQL::SQLValue($arrPrd[48]);
    $where['id_product'] = MySQL::SQLValue($id_product);
    if (isset($arrPrd[47]) and ! empty($arrPrd[47]))
    {
      $values['DSunitaMisura'] = MySQL::SQLValue($arrPrd[47]);
    }
    else
    {
      $values['DSunitaMisura'] = MySQL::SQLValue("");
    }
    $db = new MySQL();
    $last_id = $db->UpdateRows("ps_product", $values, $where);

    return $last_id;
  }

  /**
   * Metodo che aggiorna etichette di un prodotto esistente
   * 
   * @param Array $arrPrd Array dei dati del prodoto da aggiornare dal tracciato ERP
   * @param Int $idProduct Id del prodotto
   * @param Array $lang Array associativo delle lingue del prodotto
   */
  function updateProductLang($arrPrd, $idProduct, $lang) {
    foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
    {
      $where['id_product'] = MySQL::SQLValue($idProduct);
      $where['id_shop'] = MySQL::SQLValue(1);
      $where['id_lang'] = MySQL::SQLValue($ps_id_lang);

      $values['meta_description'] = MySQL::SQLValue("");
      $values['meta_keywords'] = MySQL::SQLValue($arrPrd[5]);

      if (!empty($arrPrd[6]))
      {
        $values['description_short'] = MySQL::SQLValue($arrPrd[6]);
      }
      else
      {
        $values['description_short'] = MySQL::SQLValue("");
      }

      if (!empty($lang[$erp_lang]))
      {
        if (!empty($lang[$erp_lang]['name']))
        {
          $values['meta_title'] = MySQL::SQLValue($lang[$erp_lang]['name']);
          $values['name'] = MySQL::SQLValue($lang[$erp_lang]['name']);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($lang[$erp_lang]['name']));
        }
        else if (!empty($arrPrd[2]))
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[2]);
          $values['name'] = MySQL::SQLValue($arrPrd[2]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[2]));
        }
        else
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[0]);
          $values['name'] = MySQL::SQLValue($arrPrd[0]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[0]));
        }
        if (!empty($lang[$erp_lang]['description']))
        {
          $values['description'] = MySQL::SQLValue($lang[$erp_lang]['description']);
        }
        else if (!empty($arrPrd[4]))
        {
          $values['description'] = MySQL::SQLValue($arrPrd[4]);
        }
        else
        {
          $values['description'] = MySQL::SQLValue("");
        }
      }
      else
      {
        if (!empty($arrPrd[2]))
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[2]);
          $values['name'] = MySQL::SQLValue($arrPrd[2]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[2]));
        }
        else
        {
          $values['meta_title'] = MySQL::SQLValue($arrPrd[0]);
          $values['name'] = MySQL::SQLValue($arrPrd[0]);
          $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrPrd[0]));
        }
        if (!empty($arrPrd[4]))
        {
          $values['description'] = MySQL::SQLValue($arrPrd[4]);
        }
        else
        {
          $values['description'] = MySQL::SQLValue("");
        }
      }
      $values['available_now'] = MySQL::SQLValue("");
      $values['available_later'] = MySQL::SQLValue("");
      $db = new MySQL();
      $db->UpdateRows("ps_product_lang", $values, $where);
    }
  }

  /**
   * Metodo per aggiornare il prodotto all'interno degli shop configurati nell'ecommerce
   * 
   * @param Array $arrPrd Array di dati del prodotto dal tracciato ERP
   * @param Int $idProduct Id del prodotto
   */
  function updateProductShop($arrPrd, $idProduct) {
    $id_category = $this->getCategoryIdFromCode(trim($arrPrd[1]));
    $values['id_category_default'] = MySQL::SQLValue($id_category);
    $values['id_tax_rules_group'] = MySQL::SQLValue(1);
    $values['on_sale'] = MySQL::SQLValue(0);
    $values['online_only'] = MySQL::SQLValue(0);
    $values['ecotax'] = MySQL::SQLValue("0");
    if (!empty($arrPrd[46]) and is_numeric($arrPrd[46]))
    {
      $values['minimal_quantity'] = MySQL::SQLValue($arrPrd[46]);
    }
    else
    {
      $values['minimal_quantity'] = MySQL::SQLValue(0);
    }
    $values['price'] = MySQL::SQLValue(str_replace(",", ".", $arrPrd[20]));
    $values['wholesale_price'] = MySQL::SQLValue(0);
    $values['unity'] = MySQL::SQLValue("");
    $values['unit_price_ratio'] = MySQL::SQLValue(0);
    $values['additional_shipping_cost'] = MySQL::SQLValue(0);
    $values['customizable'] = MySQL::SQLValue(0);
    $values['uploadable_files'] = MySQL::SQLValue(0);
    $values['text_fields'] = MySQL::SQLValue(0);
    $values['active'] = MySQL::SQLValue(1);
    $values['redirect_type'] = MySQL::SQLValue(" ");
    $values['id_product_redirected'] = MySQL::SQLValue(0);
    $values['available_for_order'] = MySQL::SQLValue(1);
    $values['available_date'] = MySQL::SQLValue("0000-00-00");
    $values['condition'] = MySQL::SQLValue("new");
    $values['show_price'] = MySQL::SQLValue(1);
    $values['indexed'] = MySQL::SQLValue(1);
    $values['visibility'] = MySQL::SQLValue("both");
    $values['cache_default_attribute'] = MySQL::SQLValue(0);
    $values['advanced_stock_management'] = MySQL::SQLValue(0);
    if ($arrPrd[25] == "1")
    {
      $now = date("Y-m-d H:i:s");
    }
    else
    {
      $now = date('Y-m-d H:i:s', strtotime("-300 days"));
    }
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $db = new MySQL();
    $where['id_product'] = MySQL::SQLValue($idProduct);
    $where['id_shop'] = MySQL::SQLValue(1);
    $db->UpdateRows("ps_product_shop", $values, $where);
    if ($arrPrd[28] == "1")
    {
      $this->insertCategoryProduct($idProduct, 2);
    }
  }

  /**
   * Metodo per aggiornare il prodotto nello stock del negozio
   * 
   * @param Array $arrPrd Array dei dati del prodotto dal tracciato ERP
   * @param Int $idProduct Id del prodotto
   */
  function updateStockProduct($arrPrd, $idProduct) {
    $values['id_product_attribute'] = MySQL::SQLValue(0);
    $values['id_shop_group'] = MySQL::SQLValue(0);
    if (!empty($arrPrd[43]) and is_numeric($arrPrd[43]))
    {
      $values['quantity'] = MySQL::SQLValue($arrPrd[43]);
    }
    else
    {
      $values['quantity'] = MySQL::SQLValue(0);
    }
    $values['depends_on_stock'] = MySQL::SQLValue(0);
    $values['out_of_stock'] = MySQL::SQLValue(2);
    $db = new MySQL();
    $where['id_product'] = MySQL::SQLValue($idProduct);
    $where['id_shop'] = MySQL::SQLValue(1);
    $db->UpdateRows("ps_stock_available", $values, $where);
  }

  /**
   * Controllo se già esiste attributo, non creo doppione
   * 
   * @param String $value Nome dell'attributo
   * @return int Id attributo se già presente, 0 se non presente
   */
  function checkIfAttributeExists($value) {
    $sql = "SELECT * FROM ps_attribute_lang WHERE `name` = '{$value}'";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->id_attribute;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Inserisce attributo nello shop
   * 
   * @param Int $id_attribute_group Id del gruppo dell'attributo
   * @return Int Id dell'attributo inserito
   */
  function insertAttribute($id_attribute_group) {
    $values = null;
    $values['id_attribute_group'] = MySQL::SQLValue($id_attribute_group);
    $values['position'] = MySQL::SQLValue(($this->getLastAttributePosition() + 1));
    $db = new MySQL();
    return $db->InsertRow('ps_attribute', $values);
  }

  /**
   * Collego valore attributo al negozio
   * 
   * @param Int $id_attribute Id dell'attributo
   */
  function insertAttributeShop($id_attribute) {
    $values = null;
    $values['id_attribute'] = MySQL::SQLValue($id_attribute);
    $values['id_shop'] = MySQL::SQLValue(1);
    $db = new MySQL();
    $db->InsertRow('ps_attribute_shop', $values);
  }

  /**
   * Inserisco valore attributo per ogni lingua
   * 
   * @param String $value Nome dell'attributo da inserire
   * @param Int $id_attribute ID attributo da inserire
   */
  function insertLangAttribute($value, $id_attribute) {
    foreach ($this->ecommerce_lang_by_erp as $iso_code => $id_lang)
    {
      $values = null;
      $values['id_attribute'] = MySQL::SQLValue($id_attribute);
      $values['id_lang'] = MySQL::SQLValue($id_lang);
      $values['name'] = MySQL::SQLValue($value);
      $db = new MySQL();
      $db->InsertRow('ps_attribute_lang', $values);
    }
  }

  /**
   * Inserisco impatto valore attributo sul prodotto
   * 
   * @param Int $id_product_parent Id del prodotto genitore
   * @param Array $data Dati del prodotto da inserire dal tracciato dati ERP
   * @return Int Id del prodotto Inserito / aggiornato
   */
  function insertAttributeToProduct($id_product_parent, $data) {
    $price_child = str_replace(",", ".", $data[20]);
    $values = null;
    $values['id_product'] = MySQL::SQLValue($id_product_parent);
    $values['reference'] = MySQL::SQLValue($data[0]);
    $values['wholesale_price'] = MySQL::SQLValue(0);
    $values['price'] = MySQL::SQLValue($price_child);
    $values['ecotax'] = MySQL::SQLValue(0);
    if (!empty($data[43]) and is_numeric($data[43]))
    {
      $values['quantity'] = MySQL::SQLValue($data[43]);
    }
    else
    {
      $values['quantity'] = MySQL::SQLValue(0);
    }
    if (!empty($data[46]) and is_numeric($data[46]))
    {
      $values['minimal_quantity'] = MySQL::SQLValue($data[46]);
    }
    else
    {
      $values['minimal_quantity'] = MySQL::SQLValue(0);
    }
    $values['weight'] = MySQL::SQLValue(str_replace(",", ".", $data[22]));
    $values['unit_price_impact'] = MySQL::SQLValue(0);
    $values['default_on'] = MySQL::SQLValue(1);
    $values['available_date'] = MySQL::SQLValue("0000-00-00");
    $id_product_attribute = $this->checkIfValueAttrTProd($id_product_parent, $id_product_attribute);
    if ($id_product_attribute > 0)
    {
      $where['id_product_attribute'] = MySQL::SQLValue($id_product_attribute);
      $db = new MySQL();
      return $db->UpdateRows('ps_product_attribute', $values, $where);
    }
    else
    {
      $db = new MySQL();
      return $db->InsertRow('ps_product_attribute', $values);
    }
  }

  /**
   * Inserisco valore attributo nel negozio
   * 
   * @param Int $id_product_attribute Id dell'attributo del prodotto
   * @param Array $data Dati del prodotto dal tracciato ERP
   * @param Int $id_product Id del prodotto
   */
  function insertValueAttributeShio($id_product_attribute, $data, $id_product) {
    $price_child = str_replace(",", ".", $data[20]);
    $values = null;
    $values['id_product_attribute'] = MySQL::SQLValue($id_product_attribute);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['wholesale_price'] = MySQL::SQLValue(0);
    $values['price'] = MySQL::SQLValue($price_child);
    $values['ecotax'] = MySQL::SQLValue(0);
    if (!empty($data[46]) and is_numeric($data[46]))
    {
      $values['minimal_quantity'] = MySQL::SQLValue($data[46]);
    }
    else
    {
      $values['minimal_quantity'] = MySQL::SQLValue(0);
    }
    $values['weight'] = MySQL::SQLValue(str_replace(",", ".", $data[22]));
    $values['unit_price_impact'] = MySQL::SQLValue(0);
    if (!$this->checkIfAttrAlreadyDefaultOn($id_product))
    {
      $values['default_on'] = MySQL::SQLValue(1);
    }
    else
    {
      $values['default_on'] = MySQL::SQLValue(0);
    }

    $values['available_date'] = MySQL::SQLValue("0000-00-00");
    if ($this->checkIfExistsAttributeValueShop($id_product_attribute))
    {
      $where['id_product_attribute'] = MySQL::SQLValue($id_product_attribute);
      $db = new MySQL();
      $db->UpdateRows('ps_product_attribute_shop', $values, $where);
    }
    else
    {
      $db = new MySQL();
      $db->InsertRow('ps_product_attribute_shop', $values);
    }
  }

  /**
   * Metodo per inserire attributo del prodotto nello stock dello Shop
   * 
   * @param Int $id_product_attribute Id attributo nel prodotto
   * @param Array $data Array dei dati del prodotto dal tracciato ERP
   * @param Int $id_product Id del prodotto
   */
  function insertStockAttrProduct($id_product_attribute, $data, $id_product) {
    $values = null;
    $values['id_product'] = MySQL::SQLValue($id_product);
    $values['id_product_attribute'] = MySQL::SQLValue($id_product_attribute);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['id_shop_group'] = MySQL::SQLValue(0);
    if (!empty($data[43]) and is_numeric($data[43]))
    {
      $values['quantity'] = MySQL::SQLValue($data[43]);
    }
    else
    {
      $values['quantity'] = MySQL::SQLValue(0);
    }
    $values['depends_on_stock'] = MySQL::SQLValue(0);
    $values['out_of_stock'] = MySQL::SQLValue(2);
    if ($this->checkIfAttributeStockExists($id_product_attribute))
    {
      $where['id_product_attribute'] = MySQL::SQLValue($id_product_attribute);
      $db = new MySQL();
      $db->UpdateRows('ps_stock_available', $values, $where);
    }
    else
    {
      $db = new MySQL();
      $db->InsertRow('ps_stock_available', $values);
    }
  }

  /**
   * Controllo se in stock è già presente attributo
   * 
   * @param Int $id_product_attribute Id attributo del prodotto
   * @return boolean TRUE se attributo esiste, FALSE non presente
   */
  function checkIfAttributeStockExists($id_product_attribute) {
    $sql = "SELECT * FROM ps_stock_available WHERE id_product_attribute = {$id_product_attribute}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return true;
    }
    else
    {
      return false;
    }
  }

  /**
   * Inserisco combinazione valore attributo
   * 
   * @param int $id_attribute Id attributo
   * @param int $id_product_attribute Id del prodotto
   */
  function insertProductCombination($id_attribute, $id_product_attribute) {
    $values = null;
    $values['id_attribute'] = MySQL::SQLValue($id_attribute);
    $values['id_product_attribute'] = MySQL::SQLValue($id_product_attribute);
    $db = new MySQL();
    $db->InsertRow('ps_product_attribute_combination', $values);
  }

  /**
   * Forzo il prezzo a zero
   * 
   * @param Int $id_product Id del prodotto
   */
  function setProductPriceZero($id_product) {
    $sql = "UPDATE ps_product SET price = '0' WHERE id_product = {$id_product}";
    $sql2 = "UPDATE ps_product_shop SET price = '0' WHERE id_product = {$id_product}";
    $db = new MySQL();
    $db->Query($sql);
    $db->Query($sql2);
  }

  /**
   * Metodo per configurare regola da utilizzare in ps_specific_price_priority
   * 
   * @param Int $id_product Id del prodotto
   */
  function adjustSpecifiRule($id_product) {
    $values = null;
    $values['id_product'] = MySQL::SQLValue($id_product);
    $values['priority'] = MySQL::SQLValue("id_shop;id_currency;id_country;id_group");
    $db = new MySQL();
    $db->InsertRow('ps_specific_price_priority', $values);
  }

  /**
   * Resetta le immagini del prodotto
   * 
   * @param int $id_product Id del prodotto
   */
  function resetImages($id_product) {
    $sql = "SELECT id_image FROM ps_image WHERE id_product = $id_product";
    $db = new MySQL();
    $db->Query($sql);
    $row = NULL;
    if ($db->RowCount() > 0)
    {
      while (!$db->EndOfSeek())
      {
        $row[] = $db->Row();
      }
      foreach ($row as $appoRow)
      {
        $this->reinitImageLang($appoRow->id_image);
        $this->reinitImageShop($appoRow->id_image);
      }
    }
  }

  /**
   * Cancella da db la vecchia immagine del prodotto
   * 
   * @param int $id_image ID immagine
   */
  function reinitImageLang($id_image) {
    $sql = "DELETE FROM ps_image_lang WHERE id_image = $id_image";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Cancella da db shop immagine del prodotto
   * 
   * @param int $id_image Id immagine
   */
  function reinitImageShop($id_image) {
    $sql = "DELETE FROM ps_image_shop WHERE id_image = $id_image";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Metodo per inserire immagine al prodotto
   * 
   * @param String $img_1 Nome dell'immagine del file
   * @param int $position position immagine
   * @param int $id_product Id del prodotto
   */
  function insertProductImage2($img_1, $position, $id_product) {
    if (file_exists(FOLDER_UNZIP . $img_1))
    {
      $values['id_product'] = MySQL::SQLValue($id_product);
      $values['position'] = MySQL::SQLValue($position);
      if ($position > 1)
      {
        $values['cover'] = MySQL::SQLValue(0);
      }
      else
      {
        $values['cover'] = MySQL::SQLValue(1);
      }
      $db = new MySQL();
      $first_img_id = $db->InsertRow("ps_image", $values);
      foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
      {
        $values_img['id_image'] = MySQL::SQLValue($first_img_id);
        $values_img['id_lang'] = MySQL::SQLValue($ps_id_lang);
        $values_img['legend'] = MySQL::SQLValue(NULL);
        $db->InsertRow("ps_image_lang", $values_img);
      }
      $values_shop['id_image'] = MySQL::SQLValue($first_img_id);
      $values_shop['id_shop'] = MySQL::SQLValue(1);
      if ($position > 1)
      {
        $values_shop['cover'] = MySQL::SQLValue(0);
      }
      else
      {
        $values_shop['cover'] = MySQL::SQLValue(1);
      }
      $db->InsertRow("ps_image_shop", $values_shop);
      $char_1 = $this->addImageToFolder($first_img_id);
      @mkdir(FOLDER_PS_IMAGE . $char_1, 0777, TRUE);
      $array_extension = explode(".", $img_1);
      $this->unlinkImageProduct(FOLDER_PS_IMAGE . $char_1 . "/" . $first_img_id);
      copy(FOLDER_UNZIP . $img_1, FOLDER_PS_IMAGE . $char_1 . "/" . $first_img_id . ".jpg");
      if (defined('PRODUCTS_GROUP_BY_IMAGE') and ( PRODUCTS_GROUP_BY_IMAGE == 1))
      {
        if ($position == 1)
        {
          $values_image_group = null;
          $values_image_group['id_product'] = MySQL::SQLValue($id_product);
          $values_image_group['image_name'] = MySQL::SQLValue($img_1);
          $values_image_group['parent'] = MySQL::SQLValue($this->checkIfParentExits($img_1));
          if ($this->checkIfAlreayExistsGroupPrID($id_product))
          {
            $where['id_product'] = MySQL::SQLValue($id_product_);
            $values_update_image_group['image_name'] = MySQL::SQLValue($img_1);
            $db_image_group = new MySQL();
            $db_image_group->UpdateRows("erp_image_group", $values_update_image_group, $where);
          }
          else
          {
            $db_image_group = new MySQL();
            $db_image_group->InsertRow("erp_image_group", $values_image_group);
          }
        }
      }
    }
  }

  /**
   * Metodo per generare la stringa dove copiare le immagini 
   * 
   * @param int $idImage Id immagine
   * @return String Stringa generata della cartella secondo la logica di Prestashop
   */
  function addImageToFolder($idImage) {
    $arrayImage = str_split($idImage);
    $string_folder = implode("/", $arrayImage);
    return $string_folder;
  }

  /**
   * Cancello le immagini precedenti
   * 
   * @param String $img_prd_pathdefault Cartella relativa dell'immagine
   */
  function unlinkImageProduct($img_prd_pathdefault) {
    if (file_exists($img_prd_pathdefault . "-home_default.jpg"))
    {
      unlink($img_prd_pathdefault . "-home_default.jpg");
    }
    if (file_exists($img_prd_pathdefault . "-large_default.jpg"))
    {
      unlink($img_prd_pathdefault . "-large_default.jpg");
    }
    if (file_exists($img_prd_pathdefault . "-medium_default.jpg"))
    {
      unlink($img_prd_pathdefault . "-medium_default.jpg");
    }
    if (file_exists($img_prd_pathdefault . "-small_default.jpg"))
    {
      unlink($img_prd_pathdefault . "-small_default.jpg");
    }
    if (file_exists($img_prd_pathdefault . "-thickbox_default.jpg"))
    {
      unlink($img_prd_pathdefault . "-thickbox_default.jpg");
    }
    if (file_exists($img_prd_pathdefault . "-watermark.jpg"))
    {
      unlink($img_prd_pathdefault . "-watermark.jpg");
    }
    if (file_exists($img_prd_pathdefault . ".jpg"))
    {
      unlink($img_prd_pathdefault . ".jpg");
    }
  }

  /**
   * Metodo per controllare se esiste immagine genitore del prodotto
   * secondo la logica del raggrupamento per immagini
   * 
   * @param String $imgName Nome immagine
   * @return int 1 esiste immagine padre, 0 è un'immagine padre
   */
  function checkIfParentExits($imgName) {
    $sql = "SELECT * FROM erp_image_group WHERE image_name = '{$imgName}'";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return 1;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Metodo se già esiste il raggrupamento per immagine
   * 
   * @param int $id_product Id del prodotto
   * @return boolean TRUE se esiste, FALSE non c'è
   */
  function checkIfAlreayExistsGroupPrID($id_product) {
    $sql = "SELECT * FROM erp_image_group WHERE id_product = {$id_product}";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
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
   * Metodo utilizzato per inserire i produttori ricavati dal tracciato dei prodotti di ERP
   */
  function insertProduttori() {
    $this->truncateTable("ps_manufacturer");
    $this->truncateTable("ps_manufacturer_lang");
    $this->truncateTable("ps_manufacturer_shop");
    $arrKey = null;
    foreach ($this->produttori as $key => $produttore)
    {
      if (empty($arrKey) or ! in_array($produttore, $arrKey))
      {

        $arrKey[] = $produttore;
        $values = null;
        $values['name'] = MySQL::SQLValue($produttore);
        $values['active'] = MySQL::SQLValue(1);
        $db = new MySQL();
        $id_produt = $db->InsertRow("ps_manufacturer", $values);
        $values_lang = null;
        $values_lang['id_manufacturer'] = MySQL::SQLValue($id_produt);
        $values_lang['id_lang'] = MySQL::SQLValue(1);
        $db_1 = new MySQL();
        $db_1->InsertRow("ps_manufacturer_lang", $values_lang);
        $values_shop = null;
        $values_shop['id_manufacturer'] = MySQL::SQLValue($id_produt);
        $values_shop['id_shop'] = MySQL::SQLValue(1);
        $db_2 = new MySQL();
        $db_2->InsertRow("ps_manufacturer_shop", $values_shop);
        $updateProduct[$key] = $id_produt;
      }
      else
      {
        $updateProduct[$key] = $this->getIDManufacturer($produttore);
      }
    }

    foreach ($updateProduct as $prod_code => $id_manufacturer)
    {
      $this->updateManufacturInsideProduct($prod_code, $id_manufacturer);
    }
  }

  /**
   * Metodo utilizzato per recuperare id del produttore a partire dal nome
   * 
   * @param String $produttore Nome del produttore
   * @return Int Id del produttore presente nel db
   */
  function getIDManufacturer($produttore) {
    $sql = "SELECT id_manufacturer FROM ps_manufacturer WHERE name = '{$produttore}'";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    return $result->id_manufacturer;
  }

  /**
   * Metodo per associare prodotto al produttore 
   * 
   * @param String $prod_code Codice del prodotto
   * @param int $id_manufacturer Id produttore
   */
  function updateManufacturInsideProduct($prod_code, $id_manufacturer) {
    $sql = "UPDATE ps_product SET id_manufacturer = {$id_manufacturer} WHERE code = '{$prod_code}'";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Metodo per cancellare immagini al prodotto associata
   * 
   * @param int $id_product Id del prodotto
   */
  function reinitProductImage($id_product) {
    $sql = "DELETE FROM ps_image WHERE id_product = $id_product";
    $db = new MySQL();
    $db->Query($sql);
  }

  function insertRelatedProduct($data) {
    /*
     Idassociazione	int			si	Codice univoco dell'associazione
     Cdprodotto		char(50)	no	Codice del prodotto
     Cdaccessorio	char(50)	no	Codice del prodotto da associare al primo
     Dsaccessorio	char(1000)	no	Descrizione breve del prodotto associato
     flagAccessorio 	bool		Manca: se � = 1 e' un prodotto accessorio, altrimenti prod correlato.
     */
     /* DEVO CONTROLLARE CHE IL RECORD NON SIA APPARTENENTE ALAL GESTIONE DELLe TAGLIE */
    # faccio lo split in base al delimitatore per la gestione delle taglie
    $array_combination = explode(DELIMITATORE_TAGLIE, $data[1]);
    if (count($array_combination) > 1) {
      $data[1] = $array_combination[0];
    }

    $array_combination2 = explode(DELIMITATORE_TAGLIE, $data[2]);
    if (count($array_combination2) > 1) {
      $data[2] = $array_combination2[0];
    }

    /* Parte prodotti accessori */
    $prod1 = $this -> getIdProductFromCode($data[1]);
    $prod2 = $this -> getIdProductFromCode($data[2]);

    $db = new MySQL();
    //FIXME:
    if ((isset($data[4])) and (!empty($data[4])) and ($data[4] != 1)) {
      // e' un prodotto sostitutivo

      $crossProduct['id_product_1'] = MySQL::SQLValue($prod1);
      $crossProduct['id_product_2'] = MySQL::SQLValue($prod2);
      $crossProduct['description'] = MySQL::SQLValue($data[3]);
      $db -> InsertRow("ps_visioncrossselling", $crossProduct);
    } else {
      // e' un prodotto accessorio
      $accessoryProduct['id_product_1'] = MySQL::SQLValue($prod1);
      $accessoryProduct['id_product_2'] = MySQL::SQLValue($prod2);
      $db -> InsertRow("ps_accessory", $accessoryProduct);

    }
  }
}
