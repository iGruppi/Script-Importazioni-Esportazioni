<?php
/**
 * PROCEDURA DI ESPORTAZIONE ORDINE PER PRODUTTORE "NOI E LA NATURA"
 * @version 0.3
 * 
 * Valido per la versione di iGruppi 2.0
 */
    // Define path to application directory
    defined('APPLICATION_PATH')
        || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../'));

    // Define application environment
    defined('APPLICATION_ENV')
        || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

    // Ensure library/ is on include_path
    set_include_path(implode(PATH_SEPARATOR, array(
        realpath(APPLICATION_PATH),
        realpath(APPLICATION_PATH . '/library'),
        realpath(APPLICATION_PATH . '/resources')
    )));

    // include and start autoloader
    include_once("MyFw/Autoloader.php");
    Autoloader::Register();
    // Get Configuration file
    $appConfig = new Zend_Config_Ini(APPLICATION_PATH . '/resources/Config/application.ini', APPLICATION_ENV);
    Zend_Registry::set('appConfig', $appConfig);
    // SET LOCALE
    date_default_timezone_set('Europe/Rome');
    setlocale(LC_TIME, 'it_IT');

    
    // START DB
    $db = new MyFw_DB();
    
    // Ragione sociale (FISSO per NOI E LA NATURA)
    $ragione = "Noi e la Natura";
  

    /***
     * Idordine da passare come parametro URL
     *    -> /get_csv_noielanatura.php?idordine=xx&idgroup=xx
     */
    if(!isset($_REQUEST["idordine"])) {
        die("'idordine' parameter NOT exists!");
    }
    $idordine = $_REQUEST["idordine"];

    if(!isset($_REQUEST["idgroup"])) {
        die("'idgroup' parameter NOT exists!");
    }
    $idgroup = $_REQUEST["idgroup"];
  
  
    // check if ORDINE exists and GET DATA
    $q1 = "  SELECT g.nome, og.idordine
             FROM ordini_groups AS og
             JOIN groups AS g ON og.idgroup_slave= g.idgroup
             WHERE og.idgroup_slave= :idgroup AND og.idordine= :idordine";

    $sth = $db->prepare($q1);
    $sth->execute(array('idordine' => $idordine, 'idgroup' => $idgroup ));
    if (!$res = $sth->fetch(PDO::FETCH_OBJ)) {
      die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
    }  
    
    // set nome del Gruppo
    $descrizioneGroup = $res->nome;


    // query sui soci
    $query1 = "
    SELECT
      u.iduser AS idsocio,
      u.cognome,
      u.nome,
      sum(op.costo_ordine * oup.qta_reale) as prezzo
    FROM
      users       u,
      ordini_user_prodotti    oup,
      ordini_prodotti        op,
      users_group ug
    WHERE    
      u.iduser = oup.iduser AND
      u.iduser = ug.iduser AND
      oup.idprodotto = op.idprodotto AND oup.idordine = op.idordine AND oup.idlistino=op.idlistino AND
      op.idordine = :idordine AND ug.idgroup= :idgroup
    group by
      u.iduser
    order by
      u.iduser
    " ;

    $statement = $db->prepare($query1) ; 
    if (!$statement) {
      die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
    }
    $statement->execute(array('idordine' => $idordine, 'idgroup' => $idgroup ));
    $resSoci = $statement->fetchAll(PDO::FETCH_OBJ);

    // costruisco l'header e l'elenco soci
    $header = "Articolo;Descrizione;Confezione;Prezzo;Cartone;" ;
    $elenco_soci = array();
    $elenco_totali = array();
    foreach($resSoci AS $socio) {
      $header .= $socio->cognome . " ".$socio->nome.";";
      $elenco_soci[$socio->idsocio] = 0;
      $elenco_totali[$socio->idsocio] = $socio->prezzo;
    }
    $header .= "Gruppo";

    // stampa l'intestazione
    // header('Content-Type: text/plain');
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary"); 
    header("Content-disposition: attachment; filename=\"".strtolower(preg_replace("/[^a-zA-Z]/","",$descrizioneGroup))."-NOIELANATURA-$idordine.csv");

    echo "$descrizioneGroup - ORDINE N. $idordine DEL ". date("d-m-Y H:i:s")."\n" ; 
    echo "\n" ;
    echo "$header\n" ;

    // query sugli item
    $query2 = "
    SELECT
      p.idprodotto,
      p.codice,
      p.descrizione,
      p.udm AS tipoprezzo,
      op.costo_ordine AS prezzo,
      oup.iduser AS idsocio,
      oup.qta as quantita
    FROM
      ordini_user_prodotti AS oup
      JOIN users_group AS ug ON oup.iduser=ug.iduser
      JOIN ordini_prodotti op ON oup.idprodotto = op.idprodotto AND oup.idordine=op.idordine
      JOIN prodotti p ON oup.idprodotto = p.idprodotto
    WHERE  
      oup.idordine = :idordine AND ug.idgroup= :idgroup
    GROUP BY
      oup.iduser, oup.idprodotto    
    ORDER BY
      oup.idprodotto, oup.iduser
    " ;
    $statement2 = $db->prepare($query2) ; 
    if (!$statement2) {
      die ("Prepare statement failed: errno=".$db->errno.", error=".$db->error);
    }
    $statement2->execute(array('idordine' => $idordine, 'idgroup' => $idgroup));
    $resItem = $statement2->fetchAll(PDO::FETCH_OBJ);


    // GOING THROUGH THE DATA
    $idprodotto_old = -32767 ;
    $codice_old = null;
    $descrizione_old = null ;
    $tipologia_old = null ;
    $tipoprezzo_old = null ;
    $prezzo_old = null ;
    $quantita_arr = $elenco_soci ;
  
//    print_r($quantita_arr);die;
    
    foreach ($resItem AS $item) {

        $idprodotto = $item->idprodotto;
        $codice = $item->codice;
        $descrizione = $item->descrizione;
        $tipoprezzo = $item->tipoprezzo;
        $prezzo = $item->prezzo;
        $idsocio = $item->idsocio;
        $quantita = $item->quantita;

        if ($idprodotto!=$idprodotto_old && $idprodotto_old>0) {

    //      echo "FINE RIGA<br>";

          // costruisce la riga
          $row = "$descrizione_old;$ragione;$tipoprezzo_old;$prezzo_old;;" ;
          $sum = 0 ;
          foreach ($quantita_arr as $qta) {
            $row .= "$qta;" ; 
            $sum += $qta ;
          }
          $row .= $sum ;

          // stampa la riga
          echo "$row\n" ;

          // re-inzializza l'array delle quantit�
          $quantita_arr = $elenco_soci ;

        }
    //      echo "prodotto=>$idprodotto socio=>$idsocio quantita=>$quantita <br>" ;

        $quantita_arr[$idsocio] = $quantita ;

        $codice_old = $codice;
        $idprodotto_old = $idprodotto;
        $descrizione_old = str_pad($codice_old, 5, "*", STR_PAD_RIGHT) . $descrizione;
        $tipoprezzo_old = $tipoprezzo;
        $prezzo_old = $prezzo;
  	  
    }

    // costruisce e stampa l'ultima riga
    $row = "$descrizione_old;$ragione;$tipoprezzo_old;$prezzo_old;;" ;
    $sum = 0 ;
    foreach ($quantita_arr as $qta) {
      $row .= "$qta;" ; 
      $sum += $qta ;
    }
    $row .= $sum ;
    echo "$row\n" ;

    // stampa i subtotali (?)
    $row = ";;;;SUBTOTALE;" ;
    $sum = 0 ;
    foreach ($elenco_totali as $totale) {
      $row .= round($totale,2).";" ;
      $sum += $totale ;
    }
    $row .= round($sum,2);
    echo "$row\n" ;

    // stampa i subtot. corr. (?)
    $row = ";;;;SUBTOT. CORR.;" ;
    $sum = 0 ;
    foreach ($elenco_totali as $totale) {
      $row .= round($totale,2).";" ;
      $sum += $totale ;
    }
    $row .= round($sum,2);
    echo "$row\n" ;

    // stampa le spese acc. (?)
    $row = ";;;;SPESE ACC.;" ;
    $sum = 0 ;
    foreach ($elenco_totali as $totale) {
      $spese_acc = 0 ;
      $row .= round($spese_acc,2).";" ;
      $sum += $spese_acc ;
    }
    $row .= round($sum,2);
    echo "$row\n" ;

    // stampa i totali
    $row = ";;;;TOTALE;" ;
    $sum = 0 ;
    foreach ($elenco_totali as $totale) {
      $row .= round($totale,2).";" ;
      $sum += $totale ;
    }
    $row .= round($sum,2);
    echo "$row\n" ;
