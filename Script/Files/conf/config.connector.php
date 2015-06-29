<?php

/*
 * File di configurazione.
 * All'interno sono presenti le variabili generiche utilizzate in tutte le classi del connettore
 * 
 */

namespace AIPSConnector;

/*
 * HOST database
 */
define("DB_HOST", "localhost");
/*
 * Username database
 */
define('DB_USER', 'gfranchitto');
/*
 * Password databse
 */
define('DB_PASS', 'crekepispi');
/*
 * Nome database
 */
define("DB_NAME", "devps");

/*
 * Percorso assoluto sul server linux della cartella home di Prestashop
 */
define("PATH_ABSOLUTE_AICONNECTOR", "/home/gfranchitto/domains/devps.all-init.it/public_html");

/*
 * Folder in cui vengono estratti i tracciati passati da ERP
 */
define("FOLDER_UNZIP", PATH_ABSOLUTE_AICONNECTOR . "/visionImport/tmp_data/");

/*
 * Folder contenente immagini di Prestashop
 */
define("FOLDER_PS_IMAGE", PATH_ABSOLUTE_AICONNECTOR . "/img/p/");

/*
 * Folder immagini temporanee
 */
define("FOLDER_TMP_IMG", PATH_ABSOLUTE_AICONNECTOR . "/img/tmp/");

/*
 * Folder root di Import
 */
define("FOLDER_IMPORT_ROOT", PATH_ABSOLUTE_AICONNECTOR . "/visionImport/");

/*
 * Nome file dei tracciati dati ERP
 */
define("FILE_ERP_DATA", "d_oi_b2b.zip");

/*
 * Nome file dei tracciati immagini ERP
 */
define("FILE_ERP_IMG", "oi_b2b_files.zip");

/*
 * Versione connector Windows ERP
 */
define("WIN_CONNECTOR_VER", "04");

/*
 * Salt Password di Prestashop
 */
define("SALT_PASSWORD", "3vLbIAfx0jM8WBkFuaMRmtwkgdJktPgfHFU9G0vBka783cOtiFMb10KW");

/*
 * Nome cartella amministrazione di Prestashop
 */
define("ADMIN_FOLDER", "admin4675");

/*
 * Persorso assoluto cartella amministrazione di Prestashop
 * N.B. non modificare viene costruita in automatico
 */
define('_PS_ADMIN_DIR_', PATH_ABSOLUTE_AICONNECTOR . '/' . ADMIN_FOLDER);

//define("EMAIL_LOG_TO", "notifiche@vsh.it");
//define("EMAIL_LOG_TO_2", "slancerotto@polatoferramenta.com");
define("EMAIL_LOG_TO", "gfranchitto@all-init.it");
define("EMAIL_LOG_TO_2", "gfranchitto2@all-init.it");
define("EMAIL_LOG_FROM", "notifiche@all-init.it");
define("EMAIL_LOG_SUBJECT", "[DEVPS - REPORT] Export Product to Ecommerce");
define("LANG_NUMBER", 1);

define('SHOP_NAME', 'DEVPS Ecommerce');
define('SHOP_URL', "http://devps.all-init.it/");
define('SHOP_LOGO', "http://devps.all-init.it/img/logo.jpg");
define('SUBJ_ACTIVATEEMAIL', "[DEVPS ALLINIT] - Attivazione account");
define('FROM_ACTIVATEEMAIL', "devps@all-init.it");

#define('URL_TO_WGET', 'http://10.100.54.12/polato/admin3905/searchcron.php?full=1&token=U9G0vBka');
#define('URL_WGET_RELOAD_ATTRIBUTE', "http://10.100.54.12/polato/modules/blocklayered/blocklayered-attribute-indexer.php?token=f550d3b96e");
#define('URL_WGET_RELOAD_ATTRIBUTE_URL', "http://10.100.54.12/polato/modules/blocklayered/blocklayered-url-indexer.php?token=f550d3b96e&truncate=1");
#define("FILL_PARENT_CATEGORY", 0);
#define("PRODUCTS_GROUP_BY_IMAGE", 1);
#define("LIST_BASE_NUMBER", 2);
#define('DELIMITATORE_TAGLIE', "èèè°");
#define("ENABLE_TAGLE", 0);
#define("PROPAPAGAZIONE_LISTINI_CLIENTI", 0);

class VisionConnectorConfig {

  var $ecommerce_lang_by_erp = array("IT" => 1);
  var $ecomemrce_lang_by_ps = array(1 => "IT");
  var $default_language_ecommerce = "IT";

}

