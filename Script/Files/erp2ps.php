<?php

ini_set('max_execution_time', 7200);
ini_set('memory_limit', '2048M');

/**
 * Definizione della versione del Connector PHP
 */
define('VISION_IMPORT_VERSION', "2.0.0");

require_once 'conf/config.connector.php';
require_once 'lib/mysql.class.php';
require_once 'lib/class.phpmailer.php';
require_once 'classes/PSObject.php';
require_once 'classes/PSCategory.php';
require_once 'classes/PSCustomer.php';
require_once 'classes/PSFeature.php';
require_once 'classes/PSListini.php';
require_once 'classes/PSOrder.php';
require_once 'classes/PSPayment.php';
require_once 'classes/PSProduct.php';
require_once 'classes/PSScadenze.php';

require_once PATH_ABSOLUTE_AICONNECTOR . '/config/config.inc.php';

$ps_category = new \AIPSConnector\PSCategory();

$ps_category->fileLog("VisionEcommerce Import Version: " . VISION_IMPORT_VERSION);

$ps_category->fileLog("#1: Init Folder ...");
$ps_category->initImportFolder();
$ps_category->fileLog("#1: Init Folder ... DONE");

$ps_category->fileLog("#1: Estrazione zip nella cartella ");
if (file_exists(FOLDER_IMPORT_ROOT . FILE_ERP_DATA))
{
  $ps_category->extractFileIntoFolder();
}
else
{
  $ps_category->fileLog("#2: ERRORE " . FILE_ERP_DATA . " NON PRESENTE");
  $ps_category->fileLog("#2: SCRIPT TERMINATO");
  $ps_category->sendMailLog();
  exit();
}

if (file_exists(FOLDER_IMPORT_ROOT . FILE_ERP_IMG))
{
  $ps_category->fileLog("#2: " . FILE_ERP_IMG . " PRESENTE");
}
else
{
  $ps_category->fileLog("#2: " . FILE_ERP_IMG . " NON PRESENTE");
}
$ps_category->fileLog("#2: Estrazione zip nella cartella completata");

$ps_category->fileLog("#3: Check compatibilità versione tracciati ...");
if ($ps_category->checkVersionImporter())
{
  $ps_category->fileLog("#3: VERSIONE CORRETTA");
}
else
{
  $ps_category->fileLog("#3: VERSIONE ERRATA");
  $ps_category->sendMailLog();
  exit();
}
$ps_category->fileLog("Versione tracciati corretta.");

$ps_category->fileLog("#4: Recupero informazioni ERP");
$ps_category->readInfoEcommerce();
$ps_category->fileLog("#4: Recupero informazioni ERP completata");

$ps_category->fileLog("#5: Controllo CATEGORIE");
$ps_category->getCategory();
$ps_category->fileLog("#5: Controllo CATEGORIE completato");

$ps_product = new AIPSConnector\PSProduct();

$ps_product->fileLog("#6: Controllo PRODOTTI");
$ps_product->getProduct();
$ps_product->fileLog("#6: Controllo PRODOTTI terminato");


if ($ps_product->checkIfThereAreImages())
{
  $ps_product->fileLog("#6: Thumbnail rigenerazione in corso");
  $ps_product->regenerateThumbnail();
  $ps_product->fileLog("#6: Thumbnail rigenerazione terminata");
}

$ps_customer = new \AIPSConnector\PSCustomer();

$ps_customer->fileLog("#7: Check customer");
$ps_customer->getCustomer();
$ps_customer->fileLog("#7: Check Customer ... COMPLETATO");

$ps_customer->fileLog("#8: Check Anagrafiche");
$ps_customer->getAnagrafiche();
$ps_customer->fileLog("#8: Check Anagrafiche ... COMPLETATO");

$ps_listini = new AIPSConnector\PSListini();

$ps_listini->fileLog("#8a: Check Listini");
$ps_listini->insertGestioneListini();
$ps_listini->fileLog("#8: Check Listini ... COMPLETATO");

$ps_feature = new \AIPSConnector\PSFeature();

$ps_feature->fileLog("#12: Gestione caratteristiche");
$ps_feature->getCaratteristiche();
$ps_feature->realoadForCaratteristiche();
$ps_feature->fileLog("#12: Gestione caratteristiche ... COMPLETATA");


$ps_payment = new \AIPSConnector\PSPayment();

$ps_payment->fileLog("#13: Gestione dei metodi di Pagamento");
$ps_payment->getPaymentMethod();
$ps_payment->fileLog("#13: Gestione dei metodi di Pagamento ... COMPLETATA");

$ps_product->fileLog("#14: Gestione Prodotti Correlati");
$ps_product->getRelatedProducts();
$ps_product->fileLog("#14: Gestione Prodotti Correlati... COMPLETATA");

$ps_orders = new AIPSConnector\PSOrder();

$ps_orders->fileLog("#15: Gestione Documenti Ordini Bolle Fatture");

$ps_orders->fileLog("#15: Import Testate Documenti");
$ps_orders->getOrderDocuments();
$ps_orders->fileLog("#15: Import Testate Documenti ... COMPLETATO");

$ps_orders->fileLog("#15: Import Righe Documenti");
$ps_orders->getOrderDocumentsDetails();
$ps_orders->fileLog("#15: Import Righe Documenti ... COMPLETATO");

$ps_orders->fileLog("#15: Import PDF Documenti");
$ps_orders->insertOrderDocumentsPdf();
$ps_orders->fileLog("#15: Import PDF Documenti ... COMPLETATO");

$ps_orders->fileLog("#15: Gestione Documenti Ordini Bolle Fatture ... COMPLETATA");

$ps_scadenze = new AIPSConnector\PSScadenze();

$ps_scadenze->fileLog("#16: Gestione Scadenze");
$ps_scadenze->getScadenze();
$ps_scadenze->fileLog("#16: Gestione Scadenze ... COMPLETATA");

$ps_listini->enableScontiQt();

$ps_listini->fileLog("#17: Pulizia Cache Smarty in corso ...");
$ps_listini->clearPSCache();
$ps_listini->fileLog("#17: Pulizia Cache Smarty in corso ... COMPLETATA");

$ps_listini->fileLog("######################################");
$ps_listini->fileLog("PROCESSO EXPORT DA ERP TERMINATO");

$ps_listini->sendMailLog();
