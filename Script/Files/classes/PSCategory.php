<?php

/**
 * Gestione Categorie di Prestashop
 *
 * Classe per la gestione delel categorie di Prestashop
 * Compatibile solo con versioni di prestashop 1.5.x
 *
 * @author AllIniT Srls
 * @version 2.0.0
 * @copyright
 * @package PSCategory
 *
 */

namespace AIPSConnector;

/**
 * Nome del file del tracciato di ERP per le Catogorie
 */
define('FILE_CATEGORY', 'TPD_PRODOTTI_CATEGORIE');
/**
 * Nome del file del tracciato lingue di ERP per le Categorie
 */
define('FILE_CATEGORY_LANG', 'TPD_PRODOTTI_LINGUA_CATEGORIE');
/**
 * Cartelal contenente le immagini delle categorie di Prestashop
 */
define('FOLDER_CATEGORY_IMG', PATH_ABSOLUTE_AICONNECTOR . "/img/c/");
/**
 * Variabile indicata per abilitare o meno la popolazione automatica del menù TOP
 */
define("TOPMENU_ENABLECAT", "1");

/**
 *
 */
class PSCategory extends PSObject {

  /**
   * Metodo richiamato per la gestione delle Categorie dal connector PHP
   *
   */
  function getCategory() {
    if (!file_exists(FOLDER_UNZIP . FILE_CATEGORY . ".txt"))
    {
      $this->fileLog("#5 ERROR FILE CATEGORIE NON PRESENTE. SCRIPT TERMINATO");
      return;
    }
    else
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_CATEGORY . ".txt");
      $array_category = explode("\n", $content);
      if ($this->checkInsertOrAppend(FILE_CATEGORY) == 0)
      {
        $this->resetCategory();
      }
      $topmenucat = null;
      $arrLang = $this->getCategoryLang();
      foreach ($array_category as $single_category)
      {
        $data = explode("§", $single_category);
        if ($data[1] == "")
        {
          $this->fileLog("#5 ERRORE RIGA FILE CATEGORIE NON CORRETTO - Cdcategoria NON PRESENTE : (" . $single_category . ")");
        }
        else
        {
          if ($this->getCategoryIdFromCode($data[1]) != 0)
          {
            if (TOPMENU_ENABLECAT == "1" and $data[0] == "-1")
            {
              $topmenucat[] = "CAT" . $this->getCategoryIdFromCode($data[1]);
            }
            if (isset($arrLang[$data[1]]))
            {
              $this->updateCategoryLang($data, $this->getCategoryIdFromCode($data[1]), $arrLang[$data[1]]);
            }
            else
            {
              $this->updateCategoryLang($data, $this->getCategoryIdFromCode($data[1]), "");
            }
          }
          else
          {
            $id_category = $this->insertCategory($data);
            if (TOPMENU_ENABLECAT == "1" and $data[0] == "-1")
            {
              $topmenucat[] = "CAT" . $id_category;
            }
            $this->insertCategoryGroup($id_category);
            $this->insertCategoryShop($id_category);

            if (isset($arrLang[$data[1]]))
            {
              $this->insertCategoryLang($data, $id_category, $arrLang[$data[1]]);
            }
            else
            {
              $this->insertCategoryLang($data, $id_category, "");
            }
          }
        }
      }
      if (TOPMENU_ENABLECAT == "1")
      {
        $this->updateTopMenuCat($topmenucat);
      }
      if (isset($data[3]) and ! empty($data[3]))
      {
        $this->insertCategoryImage(trim($data[3]), $this->getCategoryIdFromCode($data[1]), $data[1]);
      }
      else
      {
        $this->unlinkCategoryImage($this->getCategoryIdFromCode($data[1]));
      }
    }
  }

  /**
   * Metodo richiamato in caso di azzera e ricarica per fare il reset delle
   * categorie
   *
   */
  function resetCategory() {

    $this->truncateTable("ps_category");
    $this->truncateTable("ps_category_group");
    $this->truncateTable("ps_category_lang");
    $this->truncateTable("ps_category_shop");
    $db = new MySQL();
    $sql_1 = "INSERT ps_category SELECT * FROM " . DB_NAME . ".init_ps_category";
    $db->Query($sql_1);
    $sql_2 = "INSERT ps_category_group SELECT * FROM " . DB_NAME . ".init_ps_category_group";
    $db->Query($sql_2);
    $sql_3 = "INSERT ps_category_lang SELECT * FROM " . DB_NAME . ".init_ps_category_lang";
    $db->Query($sql_3);
    $sql_5 = "INSERT ps_category_shop SELECT * FROM " . DB_NAME . ".init_ps_category_shop";
    $db->Query($sql_5);
    $this->truncateTable("ps_category_product");
  }

  /**
   * Metodo utilizzato per recuperare array di lingue a partire dal file passato
   * da ERP
   */
  function getCategoryLang() {
    $lang = null;
    if (file_exists(FOLDER_UNZIP . FILE_CATEGORY_LANG . ".txt"))
    {
      $content = $this->file_get_contents_utf8(FOLDER_UNZIP . FILE_CATEGORY_LANG . ".txt");
      $array_cat_lang = explode("\n", $content);
      foreach ($array_cat_lang as $cat_lang)
      {
        $data = explode("§", $cat_lang);
        $lang[$data[0]][$data[1]] = $data[2];
      }
    }
    return $lang;
  }

  /**
   * Restituisce id della categoria a partire dal CODE Vision
   *
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
   * Metodo per aggiornare le etichette di una categoria giÃ  esistente
   * Parametri
   * @param $arraCat Array della categoria passato da ERP
   * @param $id_category Id della categoria giÃ  presente nel DB di Prestashop
   * @param $lang Array contenente le etichette per la categoria
   *
   */
  function updateCategoryLang($arraCat, $id_category, $lang) {
    foreach ($this->commerce_lang_by_erp as $erp_lang => $ps_id_lang)
    {
      $values = NULL;
      $values['id_shop'] = MySQL::SQLValue(1);
      $values['id_lang'] = MySQL::SQLValue($ps_id_lang);
      if (!empty($lang[$erp_lang]))
      {
        $values['name'] = MySQL::SQLValue(str_replace("â€™", "'", $lang[$erp_lang]));
      }
      else
      {
        // recupero il valore di default della lingua che deve essere usato
        if (!empty($lang[$this->ecommerce_lang_by_erp[$this->default_language_ecommerce]]))
        {

          $values['name'] = MySQL::SQLValue(str_replace("â€™", "'", $lang[$this->ecommerce_lang_by_erp[$this->default_language_ecommerce]]));
        }
        else
        {
          if (isset($arraCat[2]) and ! empty($arraCat[2]))
          {
            $values['name'] = MySQL::SQLValue(str_replace("â€™", "'", html_entity_decode(str_replace("Ã ", "&agrave;", str_replace("&nbsp;", " ", str_replace("&Atilde;", "&agrave;", htmlentities(str_replace("Ã ", "&agrave;", $arraCat[2]))))))));
          }
          else
          {
            $values['name'] = MySQL::SQLValue("-Categoria Senza Nome-");
          }
        }
      }

      if (isset($arraCat[12]) and ! empty($arraCat[12]))
      {
        $values['description'] = MySQL::SQLValue(mb_convert_encoding($arraCat[12], "HTML-ENTITIES", "UTF-8"));
      }
      else
      {
        if (isset($arraCat[2]) and ! empty($arraCat[2]))
        {
          $values['description'] = MySQL::SQLValue($arraCat[2]);
        }
        else
        {
          $values['description'] = MySQL::SQLValue("-Categoria Senza Descrizione-");
        }
      }

      $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arraCat[2]));

      $values['meta_title'] = MySQL::SQLValue(html_entity_decode(str_replace("&nbsp;", " ", str_replace("&Atilde;", "&agrave;", htmlentities($arraCat[2])))));
      $values['meta_keywords'] = MySQL::SQLValue(" ");
      $values['meta_description'] = MySQL::SQLValue(" ");
      $db = new MySQL();
      $where['id_category'] = MySQL::SQLValue($id_category);
      $db->UpdateRows("ps_category_lang", $values, $where);
    }
  }

  /**
   * DEPRECATED
   * Riabilita le categorie precedentemente disattivate
   * @param $id_Category id Prestashop della categoria da abilitare
   *
   */
  function reenableCategory($id_Category) {
    $sql = "UPDATE ps_category SET active = 1 WHERE id_Category = {$id_Category}";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Metodo utilizzato per inserire oggetto Categoria passato da ERP all'interno di Prestashop
   * @param $arrCat Array delel categorie recuperato dal file del tracciato passato da ERP
   *
   */
  function insertCategory($arrCat) {
    $values['id_shop_default'] = MySQL::SQLValue(1);
    $values['active'] = MySQL::SQLValue(1);
    $values['position'] = MySQL::SQLValue(0);
    $values['is_root_category'] = MySQL::SQLValue(0);
    if ($arrCat[0] === "-1")
    {
      $values['id_parent'] = MySQL::SQLValue(2);
      $values['level_depth'] = MySQL::SQLValue(2);
      $nleft = $this->getLeftParent(2);
    }
    else
    {
      $info_parent = $this->getIdCategoryParentFromCode($arrCat[0]);
      $values['id_parent'] = MySQL::SQLValue($info_parent->id_category);
      $values['level_depth'] = MySQL::SQLValue(($info_parent->level_depth + 1));
      $nleft = $this->getLeftParent($info_parent->id_category);
    }
    $this->preUpdateStructure($nleft);

    $values['nleft'] = MySQL::SQLValue($nleft + 1);
    $values['nright'] = MySQL::SQLValue($nleft + 2);
    $now = date("Y-m-d H:i:s");
    $values['date_add'] = MySQL::SQLValue($now);
    $values['date_upd'] = MySQL::SQLValue($now);
    $values['code'] = MySQL::SQLValue($arrCat[1]);
    $this->updateCategoryNright($nleft);
    $db = new MySQL();
    return $db->InsertRow("ps_category", $values);
  }

  /**
   * Metodo utlizzato per restiuire nleft della categoria padre
   * 
   * @param int $parent id della categoria genitore
   * @return int nleft della categoria genitore
   */
  function getLeftParent($parent) {
    $sql = "SELECT nleft FROM ps_category WHERE id_category = {$parent}";

    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return $result->nleft;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Metodo che restuitisce il level_depth e id category a partire dal codice della categoria
   * @param type $code Codice ERp della categoria
   * @return Object Struttura custom contente level_depth e idcategory della categoria selezionata
   */
  function getIdCategoryParentFromCode($code) {
    $db = new MySQL();
    $query = "SELECT id_category,level_depth FROM ps_category WHERE code = '{$code}'";
    $result = $db->QuerySingleRow($query);
    return $result;
  }

  /**
   * Metodo utilizzato per riallineare la struttura delle categorie 
   * secondo la logica nleft / nright "nested set model"
   * 
   * @param int $rgt nright per riallineare la struttura delle categorie
   */
  function preUpdateStructure($rgt) {
    $sql_lft = "UPDATE ps_category SET nleft = nleft + 2 WHERE nleft > {$rgt}";

    $db = new MySQL();
    $db->Query($sql_lft);
    $sql_rgt = "UPDATE ps_category SET nright = nright +2 WHERE nright > {$rgt}";

    $db->Query($sql_rgt);
  }

  /**
   * Merodo utilizzato per riallieneare la struttura delel categorie secondo la 
   * logica "nested set model"
   * @param int $nleft Intero nleft
   * 
   */
  function updateCategoryNright($nleft) {
    $db = new MySQL();
    $sql = "UPDATE ps_category SET nright = nright + 2 WHERE nright >= {$nleft}";
    $db->Query($sql);
  }

  /**
   * Metodo per inserire la categoria nel gruppo corrispondente (ps_category_group)
   * @param int $id_category Id della categoria
   */
  function insertCategoryGroup($id_category) {
    $groups_id = $this->getGroupId();
    foreach ($groups_id as $grid)
    {
      $db = new MySQL();
      $values = NULL;
      $values['id_category'] = MySQL::SQLValue($id_category);
      $values['id_group'] = MySQL::SQLValue($grid->id_group);
      $db->InsertRow("ps_category_group", $values);
    }
  }

  /**
   * Metodo che seleziona i gruppi dalla tabelal ps_group
   * @return Object Lista di strutture contenente id_group per ogni gruppo
   */
  function getGroupId() {
    $db = new MySQL();
    $sql = "SELECT id_group FROM ps_group";
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

  /**
   * Metodo utilizzato per inserire la categoria all'interno della tabella ps_category_shop
   * @param int $id_category Id della categoria
   */
  function insertCategoryShop($id_category) {
    $values['id_category'] = MySQL::SQLValue($id_category);
    $values['id_shop'] = MySQL::SQLValue(1);
    $values['position'] = MySQL::SQLValue($this->getCategoryShopLastPosition());
    $db = new MySQL();
    $db->InsertRow("ps_category_shop", $values);
  }

  /**
   * Metodo utilizzato per recuperare ultimo valore di position all'interno della tabella ps_category_shop
   * 
   * @return int $position Ultimo valore di position presente
   */
  function getCategoryShopLastPosition() {
    $db = new MySQL();
    $sql = "SELECT position FROM ps_category_shop WHERE id_category > 2 ORDER BY position DESC";
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0)
    {
      return ($result->position + 1);
    }
    else
    {
      return 1;
    }
  }

  /**
   * Metodo utilizzato per valorizzare le etichette delle categorie in base alle lingue dell'ecommerce e i tracciati passati da ERP
   * 
   * @param type $arrCat Array contenente i dati della categoria ricevuti dal tracciato ERP
   * @param type $id_category Id della categoria inserita in Prestashop
   * @param type $lang Array delle lingue ricevuto dal tracciato ERP (Se empty vengono utilizzati i valori del tracciato di default)
   */
  function insertCategoryLang($arrCat, $id_category, $lang) {
    foreach ($this->ecommerce_lang_by_erp as $erp_lang => $ps_id_lang)
    {

      $values = NULL;
      $values['id_category'] = MySQL::SQLValue($id_category);
      $values['id_shop'] = MySQL::SQLValue(1);
      $values['id_lang'] = MySQL::SQLValue($ps_id_lang);
      if (!empty($lang[$erp_lang]))
      {
        $values['name'] = MySQL::SQLValue(str_replace("’", "'", $lang[$erp_lang]));
      }
      else
      {
        // recupero il valore di default della lingua che deve essere usato
        if (!empty($lang[$this->ecommerce_lang_by_erp[$this->default_language_ecommerce]]))
        {
          $values['name'] = MySQL::SQLValue(str_replace("’", "'", $lang[$this->ecommerce_lang_by_erp[$this->default_language_ecommerce]]));
        }
        else
        {
          if (isset($arrCat[2]) and ! empty($arrCat[2]))
          {
            $values['name'] = MySQL::SQLValue(html_entity_decode(str_replace("’", "'", str_replace("à", "&agrave;", str_replace("&nbsp;", " ", str_replace("&Atilde;", "&agrave;", htmlentities(str_replace("à", "&agrave;", $arrCat[2]))))))));
          }
          else
          {
            $values['name'] = MySQL::SQLValue("-Categoria Senza Nome-");
          }
        }
      }
      if (isset($arrCat[12]) and ! empty($arrCat[12]))
      {
        $values['description'] = MySQL::SQLValue(mb_convert_encoding($arrCat[12], "HTML-ENTITIES", "UTF-8"));
      }
      else
      {
        if (isset($arrCat[2]) and ! empty($arrCat[2]))
        {
          $values['description'] = MySQL::SQLValue($arrCat[2]);
        }
        else
        {
          $values['description'] = MySQL::SQLValue("-Categoria Senza Descrizione-");
        }
      }

      $values['link_rewrite'] = MySQL::SQLValue($this->slugify($arrCat[2]));
      $values['meta_title'] = MySQL::SQLValue(html_entity_decode(str_replace("&nbsp;", " ", str_replace("&Atilde;", "&agrave;", htmlentities($arrCat[2])))));
      $values['meta_keywords'] = MySQL::SQLValue(" ");
      $values['meta_description'] = MySQL::SQLValue(" ");
      $db = new MySQL();
      $db->InsertRow("ps_category_lang", $values);
    }
  }

  /**
   * Metodo utilizzato per popolare in automatico il modulo TOPMENU_HORIZONTAL di Prestashop
   * 
   * @param Array $categories Array contente gli id delle categorie da mostrare 
   * come voci principali nel top menu horizontal modulo di Prestashop
   */
  function updateTopMenuCat($categories) {
    $sql = "UPDATE ps_configuration SET `value` = '" . implode(",", $categories) . "' WHERE `name` = 'MOD_BLOCKTOPMENU_ITEMS'";
    $db = new MySQL();
    $db->Query($sql);
  }

  /**
   * Metodo utilizzato per inserire l'immagine della categoria se pasata dal tracciato ERP
   * 
   * @param String $img nome immagine da associare alla categoria
   * @param Int $id_category Id della categoria
   * @param String $code Codice delal categoria passato da ERP
   */
  function insertCategoryImage($img, $id_category, $code) {
    if (file_exists(FOLDER_UNZIP . $img))
    {
      $this->unlinkCategoryImage($id_category);
      copy(FOLDER_UNZIP . $img, $this->folderImgCat . "" . $id_category . ".jpg");
    }
    else
    {
      
    }
  }

  /**
   * Metodo utilizzato per cancellare le imamgini già presenti per la categoria
   * 
   * @param Int $id_category Id della categoria
   */
  function unlinkCategoryImage($id_category) {
    $img_cat_defaultpath = $this->folderImgCat . "" . $id_category;
    if (file_exists($img_cat_defaultpath . "-category_default.jpg"))
    {
      //$this -> fileLog(" ---- UNLINK" . unlink($img_cat_defaultpath . "-category_default.jpg"));
    }
    if (file_exists($img_cat_defaultpath . "-large_default.jpg"))
    {
      unlink($img_cat_defaultpath . "-large_default.jpg");
    }
    if (file_exists($img_cat_defaultpath . "-medium_default.jpg"))
    {
      unlink($img_cat_defaultpath . "-medium_default.jpg");
    }
    if (file_exists($img_cat_defaultpath . "-small_default.jpg"))
    {
      unlink($img_cat_defaultpath . "-small_default.jpg");
    }
    if (file_exists($img_cat_defaultpath . ".jpg"))
    {
      unlink($img_cat_defaultpath . ".jpg");
    }
  }

}
