<?php

class ERP_TSD_ANAGRAFICHE {
  
  var $_0_CDanag; /* E la chiave primaria della tabella e il codice che rappresenta l'anagrafica del cliente */
  var $_1_Cdtipo_anag; /* E un codice che referenzia il tipo dell'anagrafica,può essere lasciato a null */
  var $_2_Cdute; /* E il codice dell'utente che è associato all'anagrafica */
  var $_3_Dsraso; /* Ragione sociale */
  var $_4_Dscognome; /* Cognome */
  var $_5_Dsnome; /* Nome */
  var $_6_Dsindi; /* Indirizzo */
  var $_7_Dsloca; /* Città */
  var $_8_Dscap; /* CAP */
  var $_9_Dsprov; /* Codice provincia */
  var $_10_Dsnazione; /* Nazione */
  var $_11_Dstel; /* Telefono */
  var $_12_Dsfax; /* Fax */
  var $_13_Dscell; /* Cellulare */
  var $_14_Dsemail; /* Email */
  var $_15_Dssito; /* Sito */
  var $_16_Dspiva; /* Partita iva */
  var $_17_Dscod_fisc; /* Codice fiscale */
  var $_18_Dsbanca; /* Banca */
  var $_19_Dsabi; /* ABI */
  var $_20_Dscab; /* CAB */
  var $_21_Cdzona; /* Codice zona */
  var $_22_Dsfonte; /* Codice fonte */
  var $_23_Mmnota; /* Nota sull'anagrafica */
  var $_24_Fgprivacy; /* Privacy */
  var $_25_Cdstato_anag; /* Stato dell'anagrafica */
  var $_26_Cdfonte; /* Codice fonte */
  var $_27_Fgfiglio; /* Flag anagrafica figlia */
  var $_28_Fgfittizio; /* Flag prodotto fittizio(non usato) */
  var $_29_Dtins; /* Data inserimento,nel formato yyyymmdd */
  var $_30_Cdute_ins; /* Codice dell'utente inseritore */
  var $_31_Dtmod; /* Data ultima modifica */
  var $_32_Cdute_mod; /* Codice dell'utente modificatore */
  var $_33_Fgeliminato; /* Flag eliminato */
  var $_34_Dsluogonascita; /* Luogo di nascita */
  var $_35_Dssesso; /* Sesso */
  var $_36_Fginvia_sms; /* Abilitato alle funzioni di invio sms */
  var $_37_Fginvia_email; /* Abilitato alle funzioni di invio mail */
  var $_38_Dtnascita; /* Data nascita, formato yyyymmdd */
  var $_39_Dssconto; /* Stringa degli sconti per questo cliente */
  var $_40_CDcatCliente; /* Codice categoria del cliente */
  var $_41_Cdiva; /* Codice di riferimento per impostare l'iva a questo cliente */
  var $_42_Nrfido; /* Fido del cliente */
  var $_43_Nresposizione; /* Esposizione del cliente */
  var $_44_Dsblocco; /* Descrizione del motivo del blocco del cliente, se null cliente non bloccato */
  var $_45_Cdlistino; /* Codice che fa riferimento al listino base di questo cliente */
  var $_46_FGspeseSped; /* Attiva o disattiva la gestione personalizzata delle spese di spedizione */
  var $_47_NRsogliaSpeseSped; /* Valore che discrimina l'applicazione di un costo percentuale o fisso */
  var $_48_NRcostoFissoSpeseSped; /* Il costo fisso delle spese di spedizione */
  var $_49_NRcostoPercSpeseSped; /* Il costo percentuale delle spese di spedizione */
  var $_50_CDdest_pre; /* codice destinazione predefinita - collega tab TSD_ANAGRAFICHE.CDanag */
  var $_51_CDsped_pre; /* codice sped predefinita - collega tab TSA_VETTORI.CDvettore */
  var $_52_CDpag_pre; /* codice pagamento predefinito - collega tab TSD_WEBSHOP_MODPAGAMENTO.CDmodalitaPagamento */

}

class ERP_TSD_UTENTI {
  
  var $_0_Cdute; /* Codice dell'utente */
  var $_1_Dsdesc_ute; /* Descrizione dell'utente */
  var $_2_Dslogin; /* Login utente */
  var $_3_DSpwd; /* Password utente */
  var $_4_Idprof; /* Codice profilo associato all'utente */
  var $_5_Cdlingua; /* Codice lingua in uso per l'utente */
  var $_6_Cddivisa; /* Codive divisa */
  var $_7_Dsemail_ut; /* Email dell'utente */
  var $_8_DSusr_email_ute; /* User dell'email utente */
  var $_9_DSpwd_email_ute; /* Password dell'email utente */
  var $_10_DStel_ute; /* Telefono dell'utente */
  var $_11_FGstato_gen; /* Utente abilitato */
  var $_12_IDpag_homepage; /* Determina l'homepage dell'utente nell'area riservata */
  var $_13_CDstato_gen; /* Codice riferito allo stato dell'utente */
  var $_14_FGconfermato; /* Flag che determina se l'utente è attivo o meno */
  var $_15_MMnote; /* Note relative all'utente */
 
}

class ERP_TPD_LISTINI_BASE {
  
  var $_0_Idlistino; /* Identificativo numerico del listino */
  var $_1_Cdprodotto; /* Codice prodotto a cui va applicato il listino */
  var $_2_CDvaluta; /* Codice valuta */
  var $_3_NRprezzo; /* Prezzo del prodotto se è attivo questo listino */
  var $_4_DSsconto; /* Sconto del prodotto se è attivo il listino */
  var $_5_CDlistino; /* Codice alfanumerico del listino */  
  
}

class ERP_TPD_PRODOTTI {
  
  var $_0_Cdprodotto; /* Codice del prodotto */
  var $_1_Cdmenu; /* Collegamento del prodotto alla posizione di menu */
  var $_2_DSnomeProdotto; /* Nome descrittivo del prodotto */
  var $_3_Cdmarca; /* Codice marca del prodotto */
  var $_4_Mmdescrizione; /* Descrizione estesa del prodotto */
  var $_5_Dskeywords; /* Keywords del prodotto */
  var $_6_Dsdescription; /* Descrizione breve */
  var $_7_FLschedaTecnica; /* Immagine della scheda tecnica */
  var $_8_DSschedaTecnica; /* Descrizione scheda tecnica */
  var $_9_FLimgDettaglio; /* Immagine del dettaglio */
  var $_10_DSimgDettaglio; /* Didascalia immagine del dettaglio */
  var $_11_FlimgGrande; /* Immagine grande */
  var $_12_DSimgGrande; /* Didascalia immagine grande */
  var $_13_FLimgPiccola; /* Immagine piccola */
  var $_14_DSimgPiccola; /* Didascalia immagine piccola */
  var $_15_FLimgAggiuntiva1; /* Immagine aggiuntiva per la gallery */
  var $_16_FLimgAggiuntiva2; /* Immagine aggiuntiva per la gallery */
  var $_17_FLimgAggiuntiva3; /* Immagine aggiuntiva per la gallery */
  var $_18_FLimgAggiuntiva4; /* Immagine aggiuntiva per la gallery */
  var $_19_FLimgAggiuntiva5; /* Immagine aggiuntiva per la gallery */
  var $_20_NRprezzoListino; /* prezzo base del prodotto */
  var $_21_NRprezzoScontato; /* prezzo base scontato(non utilizzato) */
  var $_22_Nrpeso; /* peso del prodotto */
  var $_23_NRordineVis; /* Ordine di visualizzazione */
  var $_24_CdstatoProdotto; /* Codice relativo allo stato del prodotto */
  var $_25_Fgnovita; /* Flag novità */
  var $_26_Fgdisponibile; /* Flag disponibilità(non utilizzato) */
  var $_27_Fgofferta; /* Flag offerta */
  var $_28_Fgvetrina; /* Flag vetrina(non utilizzato) */
  var $_29_Fgfineserie; /* Flag fine serie */
  var $_30_Fgomaggio; /* Flag omaggio(non utilizzato) */
  var $_31_FGrichiestaPreventivo; /* Flag preventivo(non utilizzato) */
  var $_32_DSurlRewrite; /* Url per il rewrite(non utilizzato) */
  var $_33_DSurlYoutube; /* Url per il link a youtube(non utilizzato) */
  var $_34_Dtins; /* Data inserimento,nel formato yyyymmdd */
  var $_35_Cdute_ins; /* Codice dell'utente inseritore */
  var $_36_Dtmod; /* Data ultima modifica */
  var $_37_Cdute_mod; /* Codice dell'utente modificatore */
  var $_38_CDuteDel; /*  */
  var $_39_Dtdel; /*  */
  var $_40_Fgdel; /*  */
  var $_41_TTlastUpdate; /*  */
  var $_42_Dssconto; /* Stringa degli sconti applicati a questo prodotto */
  var $_43_Nrqta; /* Quantita disponibile del prodotto,viene usata anche per controllare la disponibilità */
  var $_44_Cdiva; /* Codice di riferimento per l'iva da applicare a questo prodotto */
  var $_45_NRqtaConfezione; /* quantità per confezione del prodotto */
  var $_46_NRqtaMinOrdinabile; /* quantità minima ordinabile */
  var $_47_DSunitaMisura; /* unità di misura del prodotto */
  var $_48_NRqtaMultiplo; /* quantità multiplo del prodotto */
  var $_49_CDcategoria; /* Codice della categoria prodotto per listini e sconti */
  
}

class PS_specific_price {
  var $id_product;
  var $id_customer;
  var $price;
  var $from_quantity;
  var $reduction;
  var $reduction_type;
  var $from;
  var $to;
  var $type;
  var $sconto;
  var $prezzoNonScontato;
}


class ERP_TSD_SCONTI_SCAGLIONE {
  
  var $_0_Id; /* codice intero univoco per identificare la riga nella tabella(serve ai fini dell'importazione) */
  var $_1_CDprodotto; /* codice del prodotto */
  var $_2_NRqtaLimite; /* quantità limite per la quale si applica lo sconto */
  var $_3_CDanag; /* codice anagrafica */
  var $_4_NRsconto; /* sconto da applicare in percentuale al calcolo */
  
}

class ERP_TPD_LISTINI {
  var $_0_Idlistino; /* Codice numerico del listino da associare ad un cliente */
  var $_1_Cdprodotto; /* Codice del prodotto sul quale applicare il listino */
  var $_2_Cdanag; /* Codice dell'anagrafiche alla quale associare il listino */
  var $_3_Cdcategoria; /* Codice cartegoria di prodotti alla quale appalicare il listino */
  var $_4_CDcatCliente; /* Codice della categoria di cliente alla quale applicare il listino */
  var $_5_Nrprezzo; /* Prezzo del prodotto per questo listino */
  var $_6_Dssconto; /* Sconto del prodotto per questo listino */
  var $_7_Dtinizio; /* Data di inizio del listino */
  var $_8_Dtfine; /* Data di fine del listino */  
}
?>