<?php

ini_set('max_execution_time', 3000);
ini_set('memory_limit', '512M');
define('VERSION_PS2ERP', "1.0.30");

require_once 'conf/config.connector.php';
require_once 'lib/mysql.class.php';
require_once 'lib/class.phpmailer.php';
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class VisionEcommerceConnector extends VisionConnectorConfig {
  #region DEBUG

  var $file_version_importer = "CONN_VER";
  var $file_info = "TPD_INFO";
  var $file_category = "TPD_PRODOTTI_CATEGORIE";
  var $file_category_lang = "TPD_PRODOTTI_LINGUA_CATEGORIE";
  var $file_product = "TPD_PRODOTTI";
  var $file_product_lang = "TPD_PRODOTTI_LINGUA";
  var $file_customer = "TSD_UTENTI";
  var $file_anagrafiche = "TSD_ANAGRAFICHE";
  var $file_listini_base = "TPD_LISTINI_BASE";
  var $file_listini = "TPD_LISTINI";
  var $last_ordine_id = 0;
  

  #endregion FILE TO READ
  #

  #

  #endregion GLOBAL CONFIGURATION

  #

  /**
   * Construttore della classe PS2ERP per esportare i dati necessari da PRESTASHOP verso VISIONERP
   */
  function __construct() {
    $this -> emailLogTo = EMAIL_LOG_TO;
    $this -> fileLogname = "log_import_" . date("YmdHis") . ".txt";
    $this -> folderUnzip = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/tmp_data/";
    $this -> folderImg = PATH_ABSOLUTE_AICONNECTOR . "/img/p/";
    $this -> folderRoot = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/";
    $this -> fileDone = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/complete.done";
    $this -> fileFWDOCTES = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/FWDOCTES.txt";
    $this -> fileFWDOCRIG = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/FWDOCRIG.txt";
    $this -> fileFWCLIENTI = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/FWCLIENTI.txt";
    
    /**************************************************************************************/
    /*	MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (1/3)
    /**************************************************************************************/
    $this -> fileFWINDIRIZZI = PATH_ABSOLUTE_AICONNECTOR . "/visionImport/FWINDIRIZZI.txt";
    unlink($this -> fileFWINDIRIZZI);
    /**************************************************************************************/
    /*	FINE MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (1/3)
    /**************************************************************************************/
    
    $this -> last_ordine_id = $this -> getLastOrdineId();
    unlink($this -> fileDone);
    unlink($this -> fileFWCLIENTI);
    unlink($this -> fileFWDOCRIG);
    unlink($this -> fileFWDOCTES);

  }
  
  function getLastOrdineId() {
    $sql = "SELECT `value` FROM `vsecommerce_info` WHERE `field_name` = 'INDICE_ORDINE'";
    $db = new MySQL();
    $result = $db->QuerySingleRow($sql);
    if ($db->RowCount() > 0) {
      return $result -> value;
    } else {
      return 0;
    }
  }

  /**
   * Metodo utilizzato per il recupero degli ordini su Prestashop
   * Utilizza il flag erp_diff della tabella ps_orders. Tutti gli ordini che hanno erp_diff = 0 vengono esportati nel tracciato del file FWDOCTES.txt
   */
  function getOrdini() {
  
    $query = "SELECT * FROM ps_orders WHERE id_order > " . $this -> last_ordine_id;
    $db = new MySQL();
    $db -> Query($query);
    $row = NULL;
    if ($db -> RowCount() > 0) {

      while (!$db -> EndOfSeek()) {

        $row[] = $db -> Row();
      }
    }
    //var_dump($row);
    foreach ($row as $ordine) {
	
      $appo_date = split(' ', $ordine -> date_add);
      $stringa = null;
      $stringa[] = $ordine -> id_order;
      // CDordine [0]
      
      $stringa[] = $this -> getCodAnaFromId($ordine -> id_customer);
      // CDanag   [1]
      
      $stringa[] = "";
      // CDuteMod [2]
      
      $stringa[] = $appo_date[0];
      // dataIns  [3]
      
      $stringa[] = $ordine -> reference;
      // CDordine [4]
      
      $stringa[] = str_replace(".", ",", $ordine -> total_shipping_tax_excl);
      // NRspeseSpedizione [5]
      
      
      /************************************************************************************
       * Modalit� Pagamento (1/1)
       ***********************************************************************************/
      // CDmodPagamento [6]
      
      //$stringa[] = "";
      
      if ((!empty($ordine->cdmodalitapagamento)) && ($ordine->cdmodalitapagamento != null) && (strlen($ordine->cdmodalitapagamento)>0)) {
      	$stringa[] = $ordine->cdmodalitapagamento;
      }
      elseif ((!empty($ordine->payment)) && ($ordine->payment != null)) {
      	$stringa[] = $ordine->payment;
      }
      
      /************************************************************************************
       * Fine Modalit� Pagamento (1/1)
      ***********************************************************************************/
      
      // Dsporto [7]
      $stringa[] = "";
      
      // null [8]
      $stringa[] = "";
      
      // null [9]
      $stringa[] = "";
      
      // null [10]
      $stringa[] = "";
      
      // MMcommentoUte [11]
      $stringa[] = "";
      
      // sconto [12]
      //$stringa[] = "";
      

      if ((!empty($ordine->cdmodalitapagamento)) && (strlen($ordine->cdmodalitapagamento)>0)) {
	    $query = "SELECT * FROM ps_webshop_modpagamento WHERE LOWER(cdmodalitapagamento) = '" .strtolower($ordine->cdmodalitapagamento)."'";
		$db = new MySQL();
		$db -> Query($query);
		$scontiModPagamento = NULL;
		if ($db -> RowCount() > 0) {

			while (!$db -> EndOfSeek()) {

			$scontiModPagamento[] = $db -> Row();
			}
		}
		$sconto = 0;
			foreach ($scontiModPagamento as $scontoModPagamento) {
			$sconto = $scontoModPagamento->dssconto;
		}
		
      	$stringa[] = str_replace(".", ",", $sconto);
      }
      else {
      	$stringa[] = "0";
      }
      file_put_contents($this -> fileFWDOCTES, iconv(mb_detect_encoding(implode("§", $stringa), mb_detect_order(), true), "UTF-8", implode("§", $stringa)) . "\r\n", FILE_APPEND);
      //$this -> unselectOrder($ordine -> id_order);
    }
	
  }

  /**
   * Metodo per recuperare il codice di un'anagrafica (identificativo di VisionERP) a partire dall'ID di Prestashop della tabella ps_customer
   *
   * @param int $id Id customer Prestashop della tabella ps_customer
   * @return CdAnag Restituisce il codice dell'anagrafica univoco per VisionERP
   */
  function getCodAnaFromId($id) {
    $sql = "SELECT * FROM ps_customer WHERE id_customer = $id";
    $db = new MySQL();
    $result = $db -> QuerySingleRow($sql);
    if (!empty($result -> code)) {
      return $result -> code;
    } else {
      return $this->fillCodeClientiWithZero($this -> getCodeClienti(), $result -> id_customer);
    }

  }

  /**
   * Metodo per deselzionare ordine appena esportato dalla lista.
   *
   * Serve a non far restituire due volte lo stesso valore a VisionERP
   *
   * @param int $id ID dell'ordine di Prestashop della tabella ps_orders
   *
   */
  function unselectOrder($id) {
    $query = "UPDATE ps_orders SET erp_diff = 1 WHERE id_order = $id";
    $db = new MySQL();
    $db -> Query($query);
  }

  /**
   * Metodo per recuperare i dettagli degli ordini processati
   *
   */
  function getSingleOrdini() {
    $query = "SELECT * FROM ps_order_detail WHERE id_order > " . $this -> last_ordine_id;
    $db = new MySQL();
    $db -> Query($query);
    $row = NULL;
    if ($db -> RowCount() > 0) {

      while (!$db -> EndOfSeek()) {

        $row[] = $db -> Row();
      }
    }
    foreach ($row as $ordine) {
      $appo_date = split(' ', $this -> getOrderDataFromID($ordine -> id_order));
      $stringa = null;
      $stringa[] = $ordine -> id_order;
      $stringa[] = $this -> getCodProdFromID($ordine -> product_id);
      $stringa[] = "";
      $stringa[] = $ordine -> product_quantity;
      $stringa[] = number_format($ordine -> product_price, 2, ',');
      $stringa[] = "";
      $stringa[] = "";
      $stringa[] = "";
      $stringa[] = "";
      $stringa[] = $appo_date[0];
      $stringa[] = str_replace(".", ",", $ordine -> unit_price_tax_excl);
      $stringa[] = "";
      file_put_contents($this -> fileFWDOCRIG, iconv(mb_detect_encoding(implode("§", $stringa), mb_detect_order(), true), "UTF-8", implode("§", $stringa)) . "\r\n", FILE_APPEND);
      $this -> unselectSingleOrder($ordine -> id_order_detail);
    }
  }

  /**
   * Metodo per restituire il codice del prodotto VisionERP dal Prestashop dalla tabella ps_product
   *
   * @param int $id Id dell'ordine
   * @return Code Codice VisioERP del prodotto
   */
  function getCodProdFromID($id) {
    $sql = "SELECT * FROM ps_product WHERE id_product = $id";
    $db = new MySQL();
    $result = $db -> QuerySingleRow($sql);
    return $result -> code;
  }

  /**
   * Metodo per recuperare la data di inserimento dell'ordine in Prestashop
   *
   * @param int $id ID dell'ordine PRestashop
   * @return date_add Data e Ora inserimento ordine in Prestashop
   */
  function getOrderDataFromID($id) {
    $sql = "SELECT date_add FROM ps_orders WHERE id_order = $id";
    $db = new MySQL();
    $result = $db -> QuerySingleRow($sql);
    return $result -> date_add;
  }

  /**
   * Metodo per deselzionare dal successivo export verso VisionERP il dettaglio dell'ordine già esporrtato
   *
   * @param int $id Id ordine di Prestashop
   *
   */
  function unselectSingleOrder($id) {
    $query = "UPDATE ps_order_detail SET erp_diff = 1 WHERE id_order_detail = $id";
    $db = new MySQL();
    $db -> Query($query);
  }

  /**
   * Metodo per il recupero delle anagrafiche presenti dentro Prestashop e la creazione del file FWCLIENTI.txt
   */
  function getAnagrafERP() {
    $query = "SELECT * FROM ps_customer WHERE code IS NULL";
    $db = new MySQL();
    $db -> Query($query);
    $row = NULL;
    if ($db -> RowCount() > 0) {

      while (!$db -> EndOfSeek()) {

        $row[] = $db -> Row();
      }
    }
    //var_dump($row);
    foreach ($row as $anag) {
      $stringa = null;
      $stringa[] = $this->fillCodeClientiWithZero($this -> getCodeClienti(), $anag -> id_customer);
      // CDute [0]
      $appo = null;
      $appo = $this -> getSIngleAnagFromId($anag -> id_customer);
      if (!empty($appo -> company)) {
        $stringa[] = $appo -> company;
        // DSRaso [1]
      } else {
        $stringa[] = "-Senza Ragione Sociale-";

      }
      if (!empty($anag -> lastname)) {
        $stringa[] = $anag -> lastname;
        // DSCognome [2]
      } else {
        $stringa[] = "-Senza Cognome-";
      }
      if (!empty($anag -> firstname)) {
        $stringa[] = $anag -> firstname;
        // DSnome [3]
      } else {
        $stringa[] = "-Senza Nome-";
      }
      if (!empty($appo -> vat_number)) {
        $stringa[] = $appo -> vat_number;
        // DSpiva [4]
      } else {
        $stringa[] = "-Senza Partita Iva-";
      }
      if (!empty($appo -> dni)) {
        $stringa[] = $appo -> dni;
        // DScod_fis [5]
      } else {
        $stringa[] = "-Senza Codice Fiscale-";
      }

      $stringa[] = $appo -> address1;
      // DSindi [6]
      $stringa[] = $appo -> postcode;
      // DScap [7]
      $stringa[] = $appo -> city;
      // DSloca [8]
      $stringa[] = $this -> getStateFromId($appo -> id_state);
      //$stringa[] = "";                                                              // DSprov [9]
      $stringa[] = $appo -> phone;
      // DStel [10]
      $stringa[] = $appo -> phone_mobile;
      // DScell [11]
      $stringa[] = "";
      // DSfax [12]
      $stringa[] = $anag -> email;
      // DSemail [13]
      $stringa[] = "";
      // null [14]
      $stringa[] = "";
      // null [15]
      $stringa[] = "IT";
      // DSnazione [16]
      $stringa[] = "";
      // MMNota [17]
      $stringa[] = $anag -> email;
      // DSlogin [18]
      $stringa[] = "";
      // DSpwd [19]
      file_put_contents($this -> fileFWCLIENTI, iconv(mb_detect_encoding(implode("§", $stringa), mb_detect_order(), true), "UTF-8", implode("§", $stringa)) . "\r\n", FILE_APPEND);
      $this -> unselectSingleAnag($anag -> id_customer, $this->fillCodeClientiWithZero($this -> getCodeClienti(), $anag -> id_customer));
    }
  }

  /**
   * Metodo per il PREFISSO impostato da VisionERP, per identificare i clienti WEB.
   *
   * Il valore viene passato nel tracciato nella fase di import da VisionERP a Prestashop nella tabella vsecommerce_info con flag CODICECLI
   *
   * @return CODICECLI Codice Cliente da associare ai clienti che si registrano da Web
   */
  function getCodeClienti() {
    $sql = "SELECT * FROM vsecommerce_info WHERE field_name='CODICECLI'";
    $db = new MySQL();
    $result = $db -> QuerySingleRow($sql);
    return $result -> value;
  }
  
  function fillCodeClientiWithZero($prefix, $id_cliente) {
    $max_chars = 6;
    $len_perfix = strlen($prefix);
    $s = sprintf("%06s", $id_cliente);
    return $prefix . "" . substr($s, $len_perfix);
  }

  /**
   * Metodo per recuperare intera Anagrafica (indirizzo ps_address) da Prestashop
   *
   * @param int $id ID del customer Prestashop
   * @return ANAGRAFICA Record della tabella ps_address Prestashop
   *
   */

  function getSIngleAnagFromId($id) {
    $query = "SELECT * FROM ps_address WHERE id_customer = $id";
    $db = new MySQL();
    $result = $db -> QuerySingleRow($query);
    return $result;
  }

  /**
   * Metodo per recuperare la stringa ISO_CODE della provincia del customer Prestashop
   *
   * @param int $id_state Identificativo della provincia associata al customer
   * @return ISO_CODE Sigla della provincia del customer PRestashop
   *
   */
  function getStateFromId($id_state) {
    $query = "SELECT * FROM ps_state WHERE id_state = $id_state";
    $db = new MySQL();
    $result = $db -> QuerySingleRow($query);
    return $result -> iso_code;
  }

  /**
   * Metodo per deselezionare la singola anagrafica esportata precedentemente da Prestashop verso VisionERP
   *
   * Nel momento dell'unselect viene anche configurato il CdAnag WEB
   *
   * @param int $id ID del customer Pestashop
   * @param string $code CdAnag del customer
   *
   */
  function unselectSingleAnag($id, $code) {
    $query = "UPDATE ps_customer SET code = '$code' WHERE id_customer = $id";
    $db = new MySQL();
    $db -> Query($query);
  }

  /**
   * Metodo per a creazione del file di fine operazione Prestasop TO VisionERP complete.done
   */
  function createDoneFile() {
    file_put_contents($this -> fileDone, " ");
  }

  
  
  /**************************************************************************************/
  /*	MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (2/3)
  /**************************************************************************************/
  
   /**
   * Metodo per a creazione del file di indirizzi (indirizzi fatturazione - spedizione)
   */
  function getIndirizzi() {
  	
  	/*
  	 * La richiesta � quella di gestire anche un 4� file, denominato FWINDIRIZZI, che contenga tutti gli indirizzi di spedizione.
  	 * 
  	 * 
  	*/  	
  	
  	$query = "SELECT 
		id_address,
	    customer_code,
		company,
		firstname,
		lastname,
		CONCAT(address1, ' ', address2) as full_address,
		postcode,
		city,
		state_name,
		phone,
		phone_mobile,
		vat_number
		
		FROM ps_address 
		LEFT JOIN (SELECT code as customer_code, id_customer from ps_customer) AS ps_customer_joined  ON ps_customer_joined.id_customer = ps_address.id_customer
		LEFT JOIN (SELECT name as state_name, id_state from ps_state) as ps_state_joined ON ps_state_joined.id_state = ps_address.id_state
		
		WHERE ((synch = 0)AND(NOT(alias LIKE 'fatturazione')))";
  	
  	$db = new MySQL();
  	$db -> Query($query);
  	$row = NULL;
  	if ($db -> RowCount() > 0) {
  		while (!$db -> EndOfSeek()) {
  			$row[] = $db -> Row();
  		}
  	}
  	
  	foreach ($row as $anag) {
  		
  		/* DOC: 2.1) TABELLA FWINDIRIZZI (1)
		  		La tabella FWINDIRIZZI potr� avere un tracciato record ricavato da quello della tabella �ps_address� di Presta Shop:
		  		Campo	Valori	Chiave	Descrizione campo
		  		ID_Address	int	si	Numero univoco indirizzo
		  		CdCustomer	char(50)	no	Codice del cliente, ricavato dalla tabella ps_customer
		  		Company	char(64)	no	Ragione sociale
		  		Cognome	char(32)	no	Cognome, da concatenare con Nome e importare in DestDiv.Des2Dest
		  		Nome	char(32)	no	Vedi sopra
		  		Address	char(128)	no	Indirizzo
		  		Zip	char(12)	no	Codice Avviamento Postale
		  		City	char(64)	no	Localit�
NB. -> ripetizione. elimino =>		City	char(64)	no	Localit�
		  		Prov	char(5)	no	Sigla Provincia, ricavato da ID_State o ID_Country ?
		  		Phone	char(32)	no	Telefono
		  		PhoneMob	char(32)	no	Cellulare, da importare in Contatto
		  		VATnum	char(32)	no	Partita IVA
  		*/
  		
  		$stringa = null;
  		$stringa[] = $anag -> id_address;
  		$stringa[] = $anag -> customer_code;
  		
  		$stringa[] = (!empty($anag -> company)) ? $anag -> company : "-Senza Ragione Sociale-";
  		$stringa[] = (!empty($anag -> lastname)) ? $anag -> lastname : "-Senza Cognome-";
  		$stringa[] = (!empty($anag -> firstname)) ? $anag -> firstname : "-Senza Nome-";
  		$stringa[] = (!empty($anag -> full_address)) ? $anag -> full_address : "-Senza Indirizzo-";
  		$stringa[] = $anag -> postcode;
  		$stringa[] = $anag -> city;
  		$stringa[] = $anag -> state_name;
  		$stringa[] = $anag -> phone;
  		$stringa[] = $anag -> phone_mobile;
  		
  		$stringa[] = (!empty($anag -> vat_number)) ? $anag -> vat_number : "-Senza Partita Iva-";
  		file_put_contents($this -> fileFWINDIRIZZI, iconv(mb_detect_encoding(implode("§", $stringa), mb_detect_order(), true), "UTF-8", implode("§", $stringa)) . "\r\n", FILE_APPEND);
  		$this->setAnagraficaSynchronized($anag->id_address);
  		
  		/*
  		$stringa = null;
  		$stringa[] = $this->fillCodeClientiWithZero($this -> getCodeClienti(), $anag -> id_customer);
  		// CDute [0]
  		$appo = null;
  		$appo = $this -> getSIngleAnagFromId($anag -> id_customer);
  		if (!empty($appo -> company)) {
  			$stringa[] = $appo -> company;
  			// DSRaso [1]
  		} else {
  			$stringa[] = "-Senza Ragione Sociale-";
  	
  		}
  		if (!empty($anag -> lastname)) {
  			$stringa[] = $anag -> lastname;
  			// DSCognome [2]
  		} else {
  			$stringa[] = "-Senza Cognome-";
  		}
  		if (!empty($anag -> firstname)) {
  			$stringa[] = $anag -> firstname;
  			// DSnome [3]
  		} else {
  			$stringa[] = "-Senza Nome-";
  		}
  		if (!empty($appo -> vat_number)) {
  			$stringa[] = $appo -> vat_number;
  			// DSpiva [4]
  		} else {
  			$stringa[] = "-Senza Partita Iva-";
  		}
  		if (!empty($appo -> dni)) {
  			$stringa[] = $appo -> dni;
  			// DScod_fis [5]
  		} else {
  			$stringa[] = "-Senza Codice Fiscale-";
  		}
  	
  		$stringa[] = $appo -> address1;
  		// DSindi [6]
  		$stringa[] = $appo -> postcode;
  		// DScap [7]
  		$stringa[] = $appo -> city;
  		// DSloca [8]
  		$stringa[] = $this -> getStateFromId($appo -> id_state);
  		//$stringa[] = "";                                                              // DSprov [9]
  		$stringa[] = $appo -> phone;
  		// DStel [10]
  		$stringa[] = $appo -> phone_mobile;
  		// DScell [11]
  		$stringa[] = "";
  		// DSfax [12]
  		$stringa[] = $anag -> email;
  		// DSemail [13]
  		$stringa[] = "";
  		// null [14]
  		$stringa[] = "";
  		// null [15]
  		$stringa[] = "IT";
  		// DSnazione [16]
  		$stringa[] = "";
  		// MMNota [17]
  		$stringa[] = $anag -> email;
  		// DSlogin [18]
  		$stringa[] = "";
  		// DSpwd [19]
  		 * 
  		
  	
  	
  		file_put_contents($this -> fileFWINDIRIZZI, iconv(mb_detect_encoding(implode("§", $stringa), mb_detect_order(), true), "UTF-8", implode("§", $stringa)) . "\r\n", FILE_APPEND);
  		$this -> unselectSingleAnag($anag -> id_customer, $this->fillCodeClientiWithZero($this -> getCodeClienti(), $anag -> id_customer));
  	 */
	  	
  	}
  }
  
 	function setAnagraficaSynchronized($idAnagrafica) {
 		$query = "UPDATE ps_address SET synch = 1 WHERE id_address = $idAnagrafica";
 		$db = new MySQL();
 		$db -> Query($query);
 	}
  
  /**************************************************************************************/
  /*	FINE MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (2/3)
  /**************************************************************************************/
  
  
}

$vConn = new VisionEcommerceConnector();

$vConn -> fileLog("#1: Creazione file FWDOCTES.txt");
$vConn -> fileLog("#1: ....");
$vConn -> fileLog("#1: ....");
$vConn -> fileLog("#1: ....");
$vConn -> fileLog("#1: ....");
$vConn -> fileLog("#1: ....");
$vConn -> getOrdini();
$vConn -> fileLog("#1: Creazione file FWDOCTES.txt DONE");

$vConn -> fileLog("#2: Creazione file  FWDOCRIG.txt");
$vConn -> fileLog("#2: ....");
$vConn -> fileLog("#2: ....");
$vConn -> fileLog("#2: ....");
$vConn -> fileLog("#2: ....");
$vConn -> fileLog("#2: ....");
$vConn -> getSingleOrdini();
$vConn -> fileLog("#2: Creazione file  FWDOCRIG.txt DONE");

$vConn -> fileLog("#3: Creazione file FWCLIENTI.txt");
$vConn -> fileLog("#3: ....");
$vConn -> fileLog("#3: ....");
$vConn -> getAnagrafERP();
$vConn -> fileLog("#3: Creazione file FWCLIENTI.txt DONE");

/**************************************************************************************/
/*	MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (3/3)
 /**************************************************************************************/
$vConn -> fileLog("#4: Creazione file FWINDIRIZZI.txt");
$vConn -> fileLog("#4: ....");
$vConn -> fileLog("#4: ....");
$vConn -> getIndirizzi();
$vConn -> fileLog("#4: Creazione file FWINDIRIZZI.txt DONE");
/**************************************************************************************/
/*	FINE MODIFICA GESTIONE INDIRIZZI FATTURAZIONE - SPEDIZIONE (3/3)
/**************************************************************************************/



$vConn -> sendMailLog();
$vConn -> createDoneFile();
?>